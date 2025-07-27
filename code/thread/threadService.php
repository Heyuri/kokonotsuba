<?php

class threadService {
	private array $allowedOrderFields;

	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly threadRepository $threadRepository,
		private readonly postRepository $postRepository,
		private readonly postService $postService,
		private readonly attachmentService $attachmentService,
		private readonly transactionManager $transactionManager,
		private readonly string $threadTable,
		private readonly string $postTable,
	) {
		$this->allowedOrderFields = ['last_bump_time', 'last_reply_time', 'insert_id', 'post_uid'];
	}

	public function getThreadByUID($thread_uid): array|false {
		$threadMeta = $this->threadRepository->getThreadByUID($thread_uid);

		if (!$threadMeta) {
			return false;
		}
	
		$posts = $this->threadRepository->getPostsFromThread($thread_uid);
	
		$postUIDs = array_column($posts, 'post_uid');
		$totalPosts = count($postUIDs);
		$previewCount = $this->config['RE_DEF'] ?? 5;  // fallback if RE_DEF missing
		$hiddenReplyCount = max(0, $totalPosts - $previewCount);
	
		return [
			'thread' => $threadMeta,
			'posts' => $posts,
			'post_uids' => $postUIDs,
			'hidden_reply_count' => $hiddenReplyCount,
			'thread_uid' => $threadMeta['thread_uid'],
		];
	}

	public function getFilteredThreads(int $previewCount, int $amount, int $offset = 0, array $filters = [], string $order = 'last_bump_time'): array {
		$threads = $this->threadRepository->fetchFilteredThreads($filters, $order, $amount, $offset);

		if (empty($threads)) return [];

		$threadUIDs = array_column($threads, 'thread_uid');
		$allPosts = $this->postService->getPostsByThreadUIDs($threadUIDs);
		$postsByThread = $this->groupPostsByThread($allPosts);

		$result = [];

		foreach ($threads as $thread) {
			$threadUID = $thread['thread_uid'];
			$threadPosts = $postsByThread[$threadUID] ?? [];

			$preview = $this->buildThreadPreview($threadPosts, $previewCount);

			$result[] = array_merge($preview, [
				'thread' => $thread,
				'thread_uid' => $threadUID
			]);
		}

		return $result;
	}

	private function groupPostsByThread(array $posts): array {
		$grouped = [];
		foreach ($posts as $post) {
			$grouped[$post['thread_uid']][] = $post;
		}
		return $grouped;
	}

	private function buildThreadPreview(array $posts, int $previewCount): array {
		$totalPosts = count($posts);
		$opPost = array_filter($posts, fn($p) => $p['is_op']);
		$nonOpPosts = array_filter($posts, fn($p) => !$p['is_op']);

		$previewPosts = array_merge(
			$opPost,
			array_slice($nonOpPosts, max(0, count($nonOpPosts) - $previewCount))
		);

		return [
			'posts' => $previewPosts,
			'post_uids' => array_column($previewPosts, 'post_uid'),
			'hidden_reply_count' => max(0, $totalPosts - $previewCount - 1),
		];
	}

	public function getThreadListFromBoard(
		board $board,
		int $start = 0,
		int $amount = 0,
		bool $isDESC = true,
		string $orderBy = 'last_bump_time'): array {

		// Validate orderBy to prevent SQL injection
		if (!in_array($orderBy, $this->allowedOrderFields, true)) {
			$orderBy = 'last_bump_time';
		}

		// Validate direction
		$direction = $isDESC ? 'DESC' : 'ASC';

		// Sanitize pagination params
		$start = max(0, $start);
		$amount = max(0, $amount);

		// Delegate DB query to repository
		return $this->threadRepository->fetchThreadUIDsByBoard(
			$board->getBoardUID(),
			$start,
			$amount,
			$orderBy,
			$direction
		);
	}



	public function moveThreadAndUpdate($thread_uid, $destinationBoard) {
		$this->transactionManager->run(function () use (
			$thread_uid,
			$destinationBoard
		) {
			$posts = $this->threadRepository->getPostsFromThread($thread_uid);
			if (empty($posts)) {
				throw new Exception("No posts found for thread UID: $thread_uid");
			}

			$lastPostNumber = $destinationBoard->getLastPostNoFromBoard();
			$postNumberMapping = [];
			$newThreadPostNumber = -1;
			$boardUID = $destinationBoard->getBoardUID();

			foreach ($posts as $key => $post) {
				$oldPostNumber = $post['no'];
				$newPostNumber = ++$lastPostNumber;
				$postNumberMapping[$oldPostNumber] = $newPostNumber;

				$updatedCom = $this->updateQuoteReferences($post['com'], $postNumberMapping);

				$this->threadRepository->updatePostForBoardMove(
					$post['post_uid'],
					$newPostNumber,
					$boardUID,
					$updatedCom
				);

				$destinationBoard->incrementBoardPostNumber();

				if ($key === 0) {
					$newThreadPostNumber = $newPostNumber;
				}
			}

			$this->threadRepository->updateThreadForBoardMove(
				$thread_uid,
				$boardUID,
				$newThreadPostNumber
			);

			$this->transactionManager->commit();
		});
	}

	public function copyThreadAndPosts($originalThreadUid, $destinationBoard): array {
		$moveData = [];
		
		$this->transactionManager->run(function () use (
			$originalThreadUid,
			$destinationBoard,
			&$moveData
		) {
			$posts = $this->threadRepository->getPostsFromThread($originalThreadUid);
			if (empty($posts)) {
				throw new Exception("No posts found for thread UID: $originalThreadUid");
			}

			$newThreadUid    = generateUid();
			$boardUID        = $destinationBoard->getBoardUID();
			$lastPostNo      = $destinationBoard->getLastPostNoFromBoard();
			$postNumberMapping = [];
			$postUidMapping    = [];
			$newPostsData      = [];

			$newOpPostNumber = $lastPostNo + 1;

			$this->threadRepository->insertThread($newThreadUid, $newOpPostNumber, $boardUID);

			foreach ($posts as $post) {
				$newPostNumber = ++$lastPostNo;
				$postNumberMapping[$post['no']] = $newPostNumber;
				$destinationBoard->incrementBoardPostNumber();

				$newPost = $this->mapPostData($post, $boardUID, $newPostNumber, $newThreadUid);
				$newPost['_original_uid'] = $post['post_uid'];
				$newPostsData[] = $newPost;
			}

			foreach ($newPostsData as &$postData) {
				$postData['com'] = $this->updateQuoteReferences($postData['com'], $postNumberMapping);
			}
			unset($postData);
			
			$opPostUid = -1;
			foreach ($newPostsData as $i => $postData) {
				$originalUid = $postData['_original_uid'];
				unset($postData['_original_uid']);

				$this->postRepository->insertPost($postData);
				$newPostUid = $this->postRepository->getLastInsertPostUid(); // Fetch the auto-incremented UID
				
				$postUidMapping[$originalUid] = $newPostUid;

				if ($i === 0) {
					$opPostUid = $newPostUid;
				}
			}

			$this->threadRepository->updateThreadOpPostUid($newThreadUid, $opPostUid);

			$moveData = [
				'threadUid'   => $newThreadUid,
				'postUidMap'  => $postUidMapping
			];
		});

		return $moveData;
	}

	private function mapPostData($post, $boardUID, $newPostNumber, $newThreadUid) {
		return [
			'no'			=> $newPostNumber,
			'boardUID'		=> $boardUID,
			'thread_uid'	=> $newThreadUid,
			'post_position' => $post['post_position'],
			'is_op'			=> $post['is_op'],
			'root'			=> $post['root'],
			'time'			=> $post['time'],
			'md5chksum'		=> $post['md5chksum'],
			'category'		=> $post['category'],
			'tim'			=> $post['tim'],
			'fname'			=> $post['fname'],
			'ext'			=> $post['ext'],
			'imgw'			=> $post['imgw'],
			'imgh'			=> $post['imgh'],
			'imgsize'		=> $post['imgsize'],
			'tw'			=> $post['tw'],
			'th'			=> $post['th'],
			'pwd'			=> $post['pwd'],
			'now'			=> $post['now'],
			'name'			=> $post['name'],
			'tripcode'		=> $post['tripcode'],
			'secure_tripcode' => $post['secure_tripcode'],
			'capcode'		=> $post['capcode'],
			'email'			=> $post['email'],
			'sub'			=> $post['sub'],
			'com'			=> $post['com'],
			'host'			=> $post['host'],
			'status'		=> $post['status']
		];
	}
	
	private function updateQuoteReferences($comment, $postNumberMapping) {
		return preg_replace_callback('/&gt;&gt;(\d+)/', function ($matches) use ($postNumberMapping) {
			$oldQuote = $matches[1];
			return isset($postNumberMapping[$oldQuote]) ? '&gt;&gt;' . $postNumberMapping[$oldQuote] : $matches[0];
		}, $comment);
	}

	public function deleteThreads(array $threadUidList): void {
		$this->attachmentService->removeAttachmentsFromThreads($threadUidList);

		$this->threadRepository->deleteThreadsByUidList($threadUidList);
	}

	public function pruneByAmount(array $threadUidList, int $maxThreadAmount): ?array {
		$threadsToPrune = $this->getThreadAmountToPrune($threadUidList, $maxThreadAmount);

		if(empty($threadsToPrune)) {
			return [];
		}

		$this->deleteThreads($threadsToPrune);

		return $threadsToPrune;
	}

	private function getThreadAmountToPrune(array $threadUidList, int $maxThreadAmount): ?array {
		$amountOfThreads = count($threadUidList);

		if ($amountOfThreads <= $maxThreadAmount) {
			return [];
		}

		// If threads are in newest-to-oldest order, reverse to prune the oldest ones
		$threadUidList = array_reverse($threadUidList);

		$threadsToPrune = array_slice(
			$threadUidList,
			0,
			$amountOfThreads - $maxThreadAmount
		);

		return $threadsToPrune;
	}

	
}