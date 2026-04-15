<?php

namespace Kokonotsuba\post;

use Kokonotsuba\board\board;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\post\deletion\postDeletionService;
use Kokonotsuba\request\request;
use Kokonotsuba\thread\threadRepository;

use function Puchiko\strings\generateUid;

/** Service for creating, retrieving, and soft-deleting posts within threads. */
class postService {
	use TransactionalTrait;

	public function __construct(
		private readonly postRepository $postRepository, 
		private readonly transactionManager $transactionManager, 
		private readonly threadRepository $threadRepository,
		private readonly deletedPostsService $deletedPostsService,
		private readonly request $request,
		private readonly postDeletionService $postDeletionService) {}

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
	public function removePosts(array|int $posts, ?int $accountId = 0): void {
		$this->postDeletionService->removePosts($posts, $accountId);
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