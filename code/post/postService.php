<?php

class postService {
	public function __construct(
		private readonly postRepository $postRepository, 
		private readonly transactionManager $transactionManager, 
		private readonly threadRepository $threadRepository,
		private readonly deletedPostsService $deletedPostsService) {}

	public function getPostsByUids(array $postUids): false|array {
		$postsFromList = $this->postRepository->getPostsByUids($postUids);

		return $postsFromList;
	}

	public function getPostsByThreadUIDs(array $threadUids, bool $includeDeleted = false): ?array {
		if(empty($threadUids)) return [];

		$postsFromList = $this->postRepository->getPostsByThreadUIDs($threadUids, $includeDeleted);

		return $postsFromList;
	}

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

	/* Delete post */
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
			$boardUIDs = array_unique(array_column($postsData, 'boardUID'));

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
				if($row['open_flag'] === 1) {
					continue;
				}

				// add deletion row
				$deletionRows[] = [
					'thread_uid' => $row['thread_uid'],
					'board' => $boardMap[$row['boardUID']],
				];
			}

			$threadUIDs = $this->postRepository->getThreadUIDsByPostUIDs($posts);

			$this->deletedPostsService->flagPostsAsDeleted($postsData, $accountId);

			if (!is_array($threadUIDs)) $threadUIDs = [$threadUIDs];

			foreach ($deletionRows as $deletionRow) {
				$threadUID = $deletionRow['thread_uid'];
				$board = $deletionRow['board'];

				// get posts from the associated thread uid
				$replies = $this->threadRepository->getAllPostsFromThread($threadUID, true);

				// skip post early if there are no posts/replies
				if(is_null($replies) || $replies === false) {
					continue;
				}

				// remove sage replies so the bump restoration only takes into account posts that caused a bump
				// also remove deleted replies so it reflects what the user can see
				$replies = $this->removeSagedAndDeletedReplies($replies);

				$newReplyData = end($replies);

				if (!$newReplyData) {
					continue;
				} else {
					$opPostCheck = $this->postRepository->getOpeningPostFromThread($threadUID);
					$threadData = $this->threadRepository->getThreadByUid($threadUID);

					$suppressBump = false;
					if ($opPostCheck) {
						$status = new FlagHelper($opPostCheck['status']);
						$threadCreatedTime = strtotime($threadData['thread_created_time']);
						
						$maxAgeLimit = $board->getConfigValue('MAX_AGE_TIME');

						$threadExpired = ($maxAgeLimit && ($_SERVER['REQUEST_TIME'] - $threadCreatedTime > ($maxAgeLimit * 60 * 60)));

						if ($status->value('as') || $threadExpired) {
							$suppressBump = true;
						}
					}

					if ($suppressBump) {
						$this->threadRepository->updateThreadReplyTime($threadUID, $newReplyData['root']);
					} else {
						$this->threadRepository->updateThreadBumpAndReplyTime($threadUID, $newReplyData['root']);
					}
				}
			}

		});
	}

	private function removeSagedAndDeletedReplies(array $threadReplies): array {
		$replies = [];
		foreach($threadReplies as $reply) {
			// get post email
			$email = $reply['email'];

			// if the email contains sage, then its a sage post
			$isSage = str_contains($email, 'sage');

			// if the post is flagged as deleted, its deleted
			$isDeleted = $reply['open_flag'] ?? 0;

			// if its a sage or a deleted post, continue
			if($isSage || $isDeleted) {
				continue;
			}

			$replies[] = $reply;
		}

		return $replies;
	}
}