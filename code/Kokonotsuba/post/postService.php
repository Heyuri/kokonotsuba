<?php

namespace Kokonotsuba\post;

use Kokonotsuba\board\board;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\request\request;
use Kokonotsuba\thread\Thread;
use Kokonotsuba\thread\threadRepository;

use function Kokonotsuba\libraries\getBoardsByUIDs;
use function Puchiko\strings\generateUid;

/** Service for creating, retrieving, and soft-deleting posts within threads. */
class postService {
	public function __construct(
		private readonly postRepository $postRepository, 
		private readonly transactionManager $transactionManager, 
		private readonly threadRepository $threadRepository,
		private readonly deletedPostsService $deletedPostsService,
		private readonly request $request) {}

	/**
	 * Fetch multiple posts by their UIDs, with merged attachment rows.
	 *
	 * @param int[] $postUids Array of post UIDs.
	 * @return array|false Array of merged post data arrays, or false if none found.
	 */
	public function getPostsByUids(array $postUids): false|array {
		$postsFromList = $this->postRepository->getPostsByUids($postUids);

		return $postsFromList;
	}

	/**
	 * Fetch all posts belonging to the given thread UIDs.
	 *
	 * @param string[] $threadUids     Array of thread UIDs.
	 * @param bool     $includeDeleted Whether to include soft-deleted posts.
	 * @return array|null Array of merged post data arrays, or null if none found.
	 */
	public function getPostsByThreadUIDs(array $threadUids, bool $includeDeleted = false): ?array {
		if(empty($threadUids)) return [];

		$postsFromList = $this->postRepository->getPostsByThreadUIDs($threadUids, $includeDeleted);

		return $postsFromList;
	}

	/**
	 * Insert a post into its thread (or create a new thread if this is an OP),
	 * then bump or update the thread's last-reply time.
	 *
	 * @param board          $board         The board the post belongs to.
	 * @param postRegistData $postRegistData Post registration DTO.
	 * @param int            $postUID        Pre-reserved post UID.
	 * @return void
	 */
	public function addPostToThread(
		board $board, 
		postRegistData $postRegistData,
		int $postUID 
	): void {
		$boardUID = $board->getBoardUID();
		$root = gmdate('Y-m-d H:i:s');
		$isThread = false;

		$threadUidFromUrl = $postRegistData->getThreadUidFromUrl();

		if (!$threadUidFromUrl) {
			$postRegistData->setThreadUIDFromUrl(generateUid());

			// get the updated property
			$threadUidFromUrl = $postRegistData->getThreadUidFromUrl();

			$this->threadRepository->addThread($boardUID, $postUID, $threadUidFromUrl, $postRegistData->getNo());
			
			$isThread = true;
		}

		if (!$isThread) {
			$maxPosition = $this->postRepository->getMaxPostPosition($threadUidFromUrl);
			$postRegistData->setPostPosition($maxPosition !== null ? $maxPosition + 1 : 1);
		} else {
			$postRegistData->setPostPosition(0);
		}

		$params = $postRegistData->toParams($boardUID, $root); // convert DTO to SQL params

		$this->postRepository->insertPost($params);

		if ($postRegistData->getAgeru() || $isThread) {
			$this->threadRepository->bumpThread($postRegistData->getThreadUIDFromUrl());
		} else {
			$this->threadRepository->updateThreadLastReplyTime($postRegistData->getThreadUIDFromUrl());
		}
	}

	/**
	 * Soft-delete one or more posts, flag their attachments for purgatory,
	 * and restore bump ordering on affected threads.
	 *
	 * @param array|int $posts     Post UID(s) or array of post UIDs to delete.
	 * @param int|null  $accountId Account ID performing the deletion, or 0 for anonymous.
	 * @return void
	 */
	public function removePosts($posts, ?int $accountId = 0): void {
		if (count($posts) == 0) return;
		if (!is_array($posts)) {
			$posts = [$posts];
		}

		$posts = array_filter($posts, function($value) {
			return filter_var($value, FILTER_VALIDATE_INT) !== false;
		});

		$this->transactionManager->run(function () use ($posts, $accountId) {
			$postsData = $this->postRepository->getPostsByUids($posts);

			// return early if posts data is null for whatever reason
			if(!$postsData) {
				return;
			}

			// Extract unique boardUIDs from the result
			$boardUIDs = array_unique(array_map(fn($p) => $p->getBoardUID(), $postsData));

			// Fetch boards using the unique UIDs
			$boards = getBoardsByUIDs($boardUIDs);

			// Create a board map: boardUID => board
			$boardMap = [];
			foreach ($boards as $board) {
				$boardMap[$board->getBoardUID()] = $board;
			}

			// Build deletionRows array with board mapping
			$deletionRows = [];
			foreach ($postsData as $row) {
				// don't bother including it if the post is already deleted
				if($row->getOpenFlag() === 1) {
					continue;
				}

				// add deletion row
				$deletionRows[] = [
					'thread_uid' => $row->getThreadUid(),
					'board' => $boardMap[$row->getBoardUID()],
				];
			}

			$threadUIDs = $this->postRepository->getThreadUIDsByPostUIDs($posts);

			$this->deletedPostsService->flagPostsAsDeleted($postsData, $accountId);

			if (!is_array($threadUIDs)) $threadUIDs = [$threadUIDs];

			foreach ($deletionRows as $deletionRow) {
				$threadUID = $deletionRow['thread_uid'];
				$board = $deletionRow['board'];

				// get posts from the associated thread uid
				$replies = $this->threadRepository->getAllPostsFromThread($threadUID, false);

				// skip post early if there are no posts/replies
				if(is_null($replies) || $replies === false) {
					continue;
				}

				// remove sage replies so the bump restoration only takes into account posts that caused a bump
				$replies = $this->removeSagedReplies($replies);

				$newReplyData = end($replies);

				if (!$newReplyData) {
					continue;
				} else {
					$opPostCheck = $this->postRepository->getOpeningPostFromThread($threadUID);
					$threadData = $this->threadRepository->getThreadByUid($threadUID);

					$suppressBump = false;
					if ($opPostCheck && $threadData) {
						/** @var Thread $threadData */
						$status = new FlagHelper($opPostCheck['status']);
						$threadCreatedTime = strtotime($threadData->getCreatedTime());
						
						$maxAgeLimit = $board->getConfigValue('MAX_AGE_TIME');

						$threadExpired = ($maxAgeLimit && ($this->request->getRequestTime() - $threadCreatedTime > ($maxAgeLimit * 60 * 60)));

						if ($status->value('as') || $threadExpired) {
							$suppressBump = true;
						}
					}

					if ($suppressBump) {
						$this->threadRepository->updateThreadReplyTime($threadUID, $newReplyData->getRoot());
					} else {
						$this->threadRepository->updateThreadBumpAndReplyTime($threadUID, $newReplyData->getRoot());
					}
				}
			}

		});
	}

	private function removeSagedReplies(array $threadReplies): array {
		$replies = [];
		foreach($threadReplies as $reply) {
			// get post email
			$email = $reply['email'];

			// if the email contains sage, then its a sage post
			$isSage = str_contains($email, 'sage');

			// if its a sage, continue
			if($isSage) {
				continue;
			}

			$replies[] = $reply;
		}

		return $replies;
	}

	/**
	 * Return posts in the given comment window that have the same content as the given comment.
	 * Returns null if the comment matches the board default (so default-comment posts are never flagged as spam).
	 *
	 * @param string      $comment        Comment content to check.
	 * @param string|null $defaultComment Board's default comment to exclude from the check.
	 * @param int         $timeWindow     Look-back window in seconds.
	 * @return array|null Array of matching post UIDs, or null if none (or input equals default).
	 */
	public function getRepeatedPosts(string $comment, ?string $defaultComment, int $timeWindow): ?array {
		// if the comment equals the default comment then return null since it can't be spam
		if(strip_tags($comment) === $defaultComment) {
			return null;
		}
		
		// fetch post uids of posts with the same comment
		$repeatedPosts = $this->postRepository->getRepeatedPosts($comment, $defaultComment, $timeWindow);
	
		// return the posts
		return $repeatedPosts;
	}

	/**
	 * Return the board UIDs for the given post UIDs.
	 *
	 * @param int[] $postUids Array of post UIDs.
	 * @return array|false Flat array of board UIDs, or false if none found.
	 */
	public function getBoardUidsFromPostUids(array $postUids): false|array {
		return $this->postRepository->getBoardUidsFromPostUids($postUids);
	}
}