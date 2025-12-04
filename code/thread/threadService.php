<?php

class threadService {
	private array $allowedOrderFields;

	public function __construct(
		private threadRepository $threadRepository,
		private postRepository $postRepository,
		private postService $postService,
		private transactionManager $transactionManager,
		private deletedPostsService $deletedPostsService,
		private fileService $fileService,
	) {
		$this->allowedOrderFields = ['post_op_number', 'post_op_post_uid', 'last_bump_time', 'last_reply_time', 'insert_id', 'post_uid'];
	}

	public function getThreadByUID(string $thread_uid, bool $adminMode = false): array|false {
		$threadMeta = $this->threadRepository->getThreadByUID($thread_uid);

		if (!$threadMeta) {
			return false;
		}
	
		$posts = $this->threadRepository->getPostsFromThread($thread_uid, $adminMode);
	
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

	public function getFilteredThreads(int $previewCount, int $amount, int $offset = 0, array $filters = [], bool $includeDeleted = false, string $order = 'last_bump_time'): array {
		$threads = $this->threadRepository->fetchFilteredThreads($filters, $order, $amount, $offset, $includeDeleted);

		if (empty($threads)) return [];

		$threadUIDs = array_column($threads, 'thread_uid');
		$allPosts = $this->postService->getPostsByThreadUIDs($threadUIDs, $includeDeleted);
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
			$posts = $this->threadRepository->getPostsFromThread($thread_uid, true);
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
			// get all posts from the original thread
			$posts = $this->threadRepository->getPostsFromThread($originalThreadUid, true);
						
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

			// get attachments
			$attachments = getAttachmentsFromPosts($posts);
			
			// copy attachments and build file id mapping
			$fileIdMapping = $this->copyAttachmentsData($attachments, $postUidMapping);

			// go through and mark replies in the new thread that were deleted in the old one
			$this->markDeletedPosts($postUidMapping, $fileIdMapping);

			$moveData = [
				'threadUid'   => $newThreadUid,
				'postUidMap'  => $postUidMapping,
				'fileIdMapping' => $fileIdMapping,
			];
		});

		return $moveData;
	}

	private function mapPostData($post, $boardUID, $newPostNumber, $newThreadUid) {
		return [
			'no'			=> $newPostNumber,
			'poster_hash'	=> $post['poster_hash'],
			'boardUID'		=> $boardUID,
			'thread_uid'	=> $newThreadUid,
			'post_position' => $post['post_position'],
			'is_op'			=> $post['is_op'],
			'root'			=> $post['root'],
			'category'		=> $post['category'],
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

	private function copyAttachmentsData(array $attachments, array $postUidMapping): array {
		// init file id map
		$fileIdMapping = [];
		
		// get the next file id
		$nextFileId = $this->fileService->getNextId();

		// loop through attachments and add them - the only difference being between the original being the post uid
		foreach($attachments as $att) {
			// the post uid of the post we copied
			$oldPostUid = $att['postUid'];

			// Check if the old post uid exists in the mapping
			if (isset($postUidMapping[$oldPostUid])) {
				// the post uid of the new copied post
				$newPostUid = $postUidMapping[$oldPostUid];

				// then add the file
				$this->fileService->addFile(
					$newPostUid,
					$att['fileName'],
					$att['storedFileName'],
					$att['fileExtension'],
					$att['fileMd5'],
					$att['fileWidth'],
					$att['fileHeight'],
					$att['thumbWidth'],
					$att['thumbHeight'],
					$att['fileSize'],
					$att['mimeType'],
					$att['isHidden'],
					$att['isDeleted'],
				);

				// get original file id
				$oldFileId = $att['fileId'];

				// set fileId map entry
				// old file_id => new file_id
				$fileIdMapping[$oldFileId] = $nextFileId;

				// then increment the file id by 1
				$nextFileId++;
			} else {
				// Handle the case where the old post uid is not found in the mapping (optional)
				// You can log an error or take other actions depending on your needs
				error_log("Post UID {$oldPostUid} not found in mapping.");
			}
		}

		// then return the file id mapping so deleted attachments can be ported over
		return $fileIdMapping;
	}

	private function markDeletedPosts(array $postUidMapping, array $fileIdMapping): void {
		// get the post uids of the old posts
		$oldPostUids = array_keys($postUidMapping);

		// get the new post uids
		$newPostUids = array_values($postUidMapping);

		// get the old file IDs
		$oldFileIDs = array_keys($fileIdMapping);

		// get the new file IDs
		$newFileIDs = array_values($fileIdMapping);

		// then run the dp service method to copy over the old deletion entries
		$this->deletedPostsService->copyDeletionEntries(
			$oldPostUids, 
			$newPostUids, 
			$oldFileIDs, 
			$newFileIDs
		);
	}

	public function pruneByAmount(array $threadUidList, int $maxThreadAmount): ?array {
		// slice array to filter amount threads that are over the max thread amount limit.
		// Threads are ordered on last bump time
		$threadsToPrune = $this->getThreadAmountToPrune($threadUidList, $maxThreadAmount);

		// no threads to prune
		// so dont bother and return an empty array
		if(empty($threadsToPrune)) {
			return [];
		}

		// get all the opening posts
		$postUids = $this->threadRepository->getOpPostUidsFromThreads($threadsToPrune);

		// then delete them
		// use -1 to represent the system
		$this->postService->removePosts($postUids);

		// return the deleted thread uids
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