<?php

class postService {
	private array $allowedOrderFields;

	public function __construct(
		private readonly postRepository $postRepository, 
		private readonly transactionManager $transactionManager, 
		private readonly threadRepository $threadRepository,
		private readonly attachmentService $attachmentService,
		private readonly deletedPostsService $deletedPostsService) {
		$this->allowedOrderFields = ['post_uid', 'root', 'no', 'tim', 'time'];
	}

	public function getPostsByUids(array $postUids): ?array {
		$postUidList = $this->sanitizeAndImplodeIDs($postUids);

		$postsFromList = $this->postRepository->getPostsByUids($postUidList);

		return $postsFromList;
	}

	public function getPostsByThreadUIDs(array $threadUids): ?array {
		if(empty($threadUids)) return [];

		$postsFromList = $this->postRepository->getPostsByThreadUIDs($threadUids);

		return $postsFromList;
	}

	private function sanitizeAndImplodeIDs(array $list): array {
		// filter for ints
		$list = array_filter($list, function($item) {
			return is_int($item);
		});
		
		return $list;
	}

		/* Check if the post is successive (rate limiting mechanism) */
	public function isSuccessivePost($board, $timestamp, $pass, $passcookie, $host, $isupload) {
		$config = $board->loadBoardConfig();

		$timeLimit = $timestamp - $config['RENZOKU'];
		$timeLimitUpload = $timestamp - $config['RENZOKU2'];

		$recentPosts = $this->postRepository->fetchRecentPosts($timeLimit, $isupload ? $timeLimitUpload : null);

		foreach ($recentPosts as $post) {
			if ($host === $post['host'] || $pass === $post['pwd'] || $passcookie === $post['pwd']) {
				return true; // Post is successive
			}
		}

		return false; // Not a successive post
	}

	
	public function addPostToThread(board $board, postRegistData $postRegistData): void {
		$boardUID = $board->getBoardUID();
		$postUID = $this->postRepository->getNextPostUid();  // this would also need refactoring
		$time = (int)substr($postRegistData->getTim(), 0, -3);
		$root = gmdate('Y-m-d H:i:s');
		$isThread = false;

		$board->incrementBoardPostNumber();

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

		$params = $postRegistData->toParams($boardUID, $root, $time, $isThread); // convert DTO to SQL params

		$this->postRepository->insertPost($params);

		if ($postRegistData->getAgeru() || $isThread) {
			$this->threadRepository->bumpThread($postRegistData->getThreadUIDFromUrl());
		} else {
			$this->threadRepository->updateThreadLastReplyTime($postRegistData->getThreadUIDFromUrl());
		}
	}

	/* Delete post */
	public function removePosts($posts, int $accountId = 0): void {
		if (count($posts) == 0) return;
		if (!is_array($posts)) {
			$posts = [$posts];
		}

		$posts = array_filter($posts, function($value) {
			return filter_var($value, FILTER_VALIDATE_INT) !== false;
		});

		$this->transactionManager->run(function () use ($posts, $accountId) {
			$postsData = $this->postRepository->getPostsByUids($posts);

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

				$replies = $this->threadRepository->getPostsFromThread($threadUID);

				// remove sage replies so the bump restoration only takes into account posts that caused a bump
				// also remove deleted replies so it reflects what the user can see
				$replies = $this->removeSagedAndDeletedReplies($replies);

				$newReplyData = end($replies);

				if (!$newReplyData) {
					return;
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

	public function getThreadPreviewsFromBoard(board $board, int $previewCount, int $amount = 0, int $offset = 0, bool $adminMode = false): array {
		$boardUID = $board->getBoardUID();

		$amount = max(0, $amount);
		$offset = max(0, $offset);

		$threads = $this->threadRepository->getThreadsFromBoard($boardUID, $amount, $offset, 'last_bump_time', 'DESC', $adminMode);

		if (empty($threads)) return [];

		$threadUIDs = array_column($threads, 'thread_uid');
		$postRows = $this->threadRepository->getPostsForThreads($threadUIDs, $adminMode);
		$postsByThread = $this->groupPostsByThread($postRows);

		return $this->buildPreviewResults($threads, $postsByThread, $previewCount);
	}


	public function getThreadsWithAllRepliesFromBoard(
		board $board,
		int $amount = 0,
		int $offset = 0,
		string $orderBy = 'last_bump_time',
		bool $desc = true,
	): array {
		$boardUID = $board->getBoardUID();

		$amount = max(0, $amount);
		$offset = max(0, $offset);

		$direction = $desc ? 'DESC' : 'ASC';
		$threads = $this->threadRepository->getThreadsFromBoard($boardUID, $amount, $offset, $orderBy, $direction);
		if (empty($threads)) return [];

		$threadUIDs = array_column($threads, 'thread_uid');
		$postRows = $this->getPostsByThreadUIDs($threadUIDs);
		$postsByThread = $this->groupPostsByThread($postRows);

		return $this->buildFullResults($threads, $postsByThread);
	}

	private function groupPostsByThread(array $postRows): array {
		$postsByThread = [];
		foreach ($postRows as $post) {
			$postsByThread[$post['thread_uid']][] = $post;
		}
		return $postsByThread;
	}

	private function buildPreviewResults(array $threads, array $postsByThread, int $previewCount): array {
		$result = [];
		foreach ($threads as $thread) {
			$threadUID = $thread['thread_uid'];
			$allPosts = $postsByThread[$threadUID] ?? [];

			$totalPosts = count($allPosts);
			$omittedCount = max(0, $totalPosts - $previewCount - 1);

			$opPost = array_filter($allPosts, fn($p) => $p['is_op']);
			$nonOpPosts = array_filter($allPosts, fn($p) => !$p['is_op']);
			$previewPosts = array_merge(
				$opPost,
				array_slice($nonOpPosts, max(0, count($nonOpPosts) - $previewCount))
			);

			$result[] = [
				'thread' => $thread,
				'post_uids' => array_column($previewPosts, 'post_uid'),
				'posts' => $previewPosts,
				'hidden_reply_count' => $omittedCount,
				'thread_uid' => $threadUID
			];
		}
		return $result;
	}

	private function buildFullResults(array $threads, array $postsByThread): array {
		$result = [];
		foreach ($threads as $thread) {
			$threadUID = $thread['thread_uid'];
			$allPosts = $postsByThread[$threadUID] ?? [];

			$result[] = [
				'thread' => $thread,
				'post_uids' => array_column($allPosts, 'post_uid'),
				'posts' => $allPosts,
				'thread_uid' => $threadUID
			];
		}
		return $result;
	}



}