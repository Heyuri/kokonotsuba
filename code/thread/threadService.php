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
		$this->allowedOrderFields = ['post_op_number', 'post_op_post_uid', 'last_bump_time', 'last_reply_time', 'thread_created_time', 'insert_id', 'post_uid', 'number_of_posts'];
	}


	/**
		* Fetch a thread and include only the last X replies.
		*
		* @param string $thread_uid				The UID of the thread to fetch.
		* @param bool $adminMode					Whether admin mode is enabled (affects visibility of deleted posts).
		* @param int $previewCount				How many posts should be included in the preview result.
		* @param int $amountOfRepliesToRender	Number of latest replies to include (OP not counted).
		*
		* @return array|false						Thread data structure or false if not found.
		*/
	public function getThreadLastReplies(
		string $thread_uid,
		bool $adminMode,
		int $previewCount,
		int $amountOfRepliesToRender
	): array|false {
		return $this->getThreadByUidInternal(
			$thread_uid,
			$adminMode,
			$previewCount,
			$amountOfRepliesToRender,
			null,
			null
		);
	}

	/**
		* Fetch a thread using pagination.
		*
		* @param string $thread_uid		The UID of the thread to fetch.
		* @param bool $adminMode			Whether admin mode is enabled (affects visibility of deleted posts).
		* @param int $previewCount		How many posts should be included in the preview result.
		* @param int $repliesPerPage		How many replies should be shown per page.
		* @param int $page				The page index to load (0-based external, automatically offset internally).
		*
		* @return array|false				Thread data structure or false if not found.
		*/
	public function getThreadPaged(
		string $thread_uid,
		bool $adminMode,
		int $previewCount,
		int $repliesPerPage,
		int $page
	): array|false {
		return $this->getThreadByUidInternal(
			$thread_uid,
			$adminMode,
			$previewCount,
			null,
			$repliesPerPage,
			$page
		);
	}

	/**
		* Fetch a thread with all replies (no limits or pagination).
		*
		* @param string $thread_uid		The UID of the thread to fetch.
		* @param bool $adminMode			Whether admin mode is enabled (affects visibility of deleted posts).
		* @param int $previewCount		How many posts should be included in the preview result.
		*
		* @return array|false				Thread data structure or false if not found.
		*/
	public function getThreadAllReplies(
		string $thread_uid,
		bool $adminMode,
		int $previewCount
	): array|false {
		return $this->getThreadByUidInternal(
			$thread_uid,
			$adminMode,
			$previewCount,
			null,
			null,
			null
		);
	}


	private function getThreadByUidInternal(
		string $thread_uid, 
		bool $adminMode = false, 
		int $previewCount = 5, 
		?int $amountOfRepliesToRender = 50, 
		?int $repliesPerPage = 500,
		?int $page = 0 
	): array|false {
		// get thread meta data
		$threadMeta = $this->threadRepository->getThreadByUID($thread_uid, $adminMode);

		// return false if thread data is falsey
		if (!$threadMeta) {
			return false;
		}
	

		// if the reply amount parameter is set then fetch last X amount of posts
		if($amountOfRepliesToRender) {
			$posts = $this->threadRepository->getPostsForThreads([$thread_uid], $amountOfRepliesToRender, $adminMode);
		}
		// otherwise if paged results are fetched then fetch paged results
		else if (!is_null($page)) {
			$posts = $this->threadRepository->getPostsFromThread($thread_uid, $adminMode, $repliesPerPage, $page * $repliesPerPage);
		}
		// no parameters set - fetch all replies
		else {
			$posts = $this->threadRepository->getAllPostsFromThread($thread_uid, $adminMode);
		}

		// group posts by thread
		$groupedPosts = $this->groupPostsByThread($posts);

		return $this->buildPreviewResults([$threadMeta], $groupedPosts, $previewCount)[0] ?? false;
	}

	public function getThreadPreviewsFromBoard(board $board, int $previewCount, int $amount = 0, int $offset = 0, bool $adminMode = false, string $orderBy = 'last_bump_time', bool $isDescending = true): array {
		$boardUID = $board->getBoardUID();

		$amount = max(0, $amount);
		$offset = max(0, $offset);

		if (!in_array($orderBy, $this->allowedOrderFields, true)) {
			$orderBy = 'last_bump_time';
		}

		$threads = $this->threadRepository->getThreadsFromBoard(
			$boardUID, 
			$amount, 
			$offset, 
			$orderBy, 
			$isDescending ? 'DESC' : 'ASC', 
			$adminMode
		);

		if (empty($threads)) return [];

		$threadUIDs = array_column($threads, 'thread_uid');
		
		$postRows = $this->threadRepository->getPostsForThreads($threadUIDs, $previewCount, $adminMode);
		$postsByThread = $this->groupPostsByThread($postRows);

		return $this->buildPreviewResults($threads, $postsByThread, $previewCount);
	}

	private function groupPostsByThread(array $postRows): array {
		$postsByThread = [];
		foreach ($postRows as $post) {
			$postsByThread[$post['thread_uid']][] = $post;
		}
		return $postsByThread;
	}

	private function buildPreviewResults(array $threads, array $postsByThread, ?int $previewCount): array {
		$result = [];
		foreach ($threads as $thread) {
			$threadUID = $thread['thread_uid'];
			$previewPosts = $postsByThread[$threadUID] ?? [];
			
			// get total posts
			$totalPosts = $thread['number_of_posts'];

			// if theres a preview limit then generate hidden amount
			if($previewCount) {
				$omittedCount = max(0, $totalPosts - $previewCount - 1);
			}
			// otherwise leave null
			else {
				$omittedCount = null;
			}

			$result[] = [
				'thread' => $thread,
				'post_uids' => array_column($previewPosts, 'post_uid'),
				'posts' => $previewPosts,
				'hidden_reply_count' => $omittedCount,
				'number_of_posts' => $totalPosts,
				'thread_uid' => $threadUID
			];
		}
		return $result;
	}

	public function getFilteredThreads(int $previewCount, int $amount, int $offset = 0, array $filters = [], bool $includeDeleted = false, string $order = 'last_bump_time'): array {
		$threads = $this->threadRepository->fetchFilteredThreads($filters, $order, $amount, $offset, $includeDeleted);

		if (empty($threads)) return [];

		$threadUIDs = array_column($threads, 'thread_uid');

		// get posts from thread
		$allPosts = $this->threadRepository->getPostsForThreads($threadUIDs, $previewCount, $includeDeleted);
		
		// get post counts
		$postsByThread = $this->groupPostsByThread($allPosts);

		$result = $this->buildPreviewResults($threads, $postsByThread, $previewCount);

		return $result;
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
			$posts = $this->threadRepository->getAllPostsFromThread($thread_uid, true);
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
			$posts = $this->threadRepository->getAllPostsFromThread($originalThreadUid, true);
						
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

	public function getPageOfThread(string $threadUid, int $threadsPerPage): int {
		// run repository method to get the page of the thread
		$threadPage = $this->threadRepository->getPageOfThread($threadUid, $threadsPerPage);
	
		// then return int (default to 0 if falsey/null)
		return $threadPage ?? 0;
	}
	
	public function getThreadData(string $threadUid, bool $includeDeleted = false): array|false {
		// get thread by uid
		$threadData = $this->threadRepository->getThreadByUid($threadUid, $includeDeleted);

		// then return the result
		return $threadData;
	}
}