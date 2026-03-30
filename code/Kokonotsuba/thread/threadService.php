<?php

namespace Kokonotsuba\thread;

use Exception;
use Kokonotsuba\board\board;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\post\attachment\fileService;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\post\postService;

use function Kokonotsuba\libraries\getAttachmentsFromPosts;
use function Puchiko\strings\generateUid;

/** Service for fetching, moving, copying, and pruning threads with their associated posts and attachments. */
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


	/**
	 * Internal implementation used by getThreadLastReplies, getThreadPaged, and getThreadAllReplies.
	 * Fetches thread metadata and its posts, then returns a structured preview result.
	 *
	 * @param string   $thread_uid              Thread UID to fetch.
	 * @param bool     $adminMode               Whether to show deleted posts.
	 * @param int      $previewCount            Max posts included in the preview result.
	 * @param int|null $amountOfRepliesToRender  If set, fetch only the last N replies.
	 * @param int|null $repliesPerPage           If set (with $page), fetch a paginated slice.
	 * @param int|null $page                     Page index (0-based) for paginated fetch.
	 * @return array|false Preview result array, or false if the thread does not exist.
	 */
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

		// initialize groupedPosts as an empty array to prevent groupPostsByThread from logging an error.
		// this never happens on normal kokonotsuba databases but in cases of irregular databases or
		// some kind of corruption its important to stop it from spewing a shit ton of errors
		if(!$posts) {
			$groupedPosts = [];
		}
		// group posts if the posts array isn't falsey since its (likely) all good
		else {
			// group posts by thread
			$groupedPosts = $this->groupPostsByThread($posts);
		}
		return $this->buildPreviewResults([$threadMeta], $groupedPosts, $previewCount)[0] ?? false;
	}

	/**
	 * Fetch paginated thread previews for a board, each with N latest posts.
	 *
	 * @param board  $board         Board object.
	 * @param int    $previewCount  Max posts (including OP) to include in each preview.
	 * @param int    $amount        Number of threads to return (0 = all).
	 * @param int    $offset        Pagination offset.
	 * @param bool   $adminMode     Whether to include deleted posts.
	 * @param string $orderBy       Field to sort threads by.
	 * @param bool   $isDescending  Sort direction.
	 * @return array Array of thread preview structures.
	 */
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

		$threadUIDs = array_map(fn($t) => $t->getUid(), $threads);
		
		$postRows = $this->threadRepository->getPostsForThreads($threadUIDs, $previewCount, $adminMode);
		$postsByThread = $this->groupPostsByThread($postRows);

		return $this->buildPreviewResults($threads, $postsByThread, $previewCount);
	}

	/**
	 * Group a flat array of post rows by their thread_uid key.
	 *
	 * @param array $postRows Flat array of post data rows.
	 * @return array Map of thread_uid => array of post rows.
	 */
	private function groupPostsByThread(array $postRows): array {
		$postsByThread = [];
		foreach ($postRows as $post) {
			$postsByThread[$post->getThreadUid()][] = $post;
		}
		return $postsByThread;
	}

	/**
	 * Combine thread metadata rows with their grouped post rows into a structured preview array.
	 *
	 * @param array    $threads       Array of thread metadata rows.
	 * @param array    $postsByThread Map of thread_uid => array of post rows.
	 * @param int|null $previewCount  Preview post limit (null = no limit).
	 * @return array Array of thread preview structures.
	 */
	private function buildPreviewResults(array $threads, array $postsByThread, ?int $previewCount): array {
		$result = [];
		foreach ($threads as $thread) {
			$threadUID = $thread->getUid();
			$previewPosts = $postsByThread[$threadUID] ?? [];
			
			// get total posts
			$totalPosts = $thread->getPostCount();

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
				'post_uids' => array_map(fn($p) => $p->getUid(), $previewPosts),
				'posts' => $previewPosts,
				'hidden_reply_count' => $omittedCount,
				'number_of_posts' => $totalPosts,
				'thread_uid' => $threadUID
			];
		}
		return $result;
	}

	/**
	 * Fetch a filtered, paginated list of thread previews, each with N latest posts.
	 *
	 * @param int    $previewCount   Max posts (including OP) to include in each preview.
	 * @param int    $amount         Number of threads to return.
	 * @param int    $offset         Pagination offset.
	 * @param array  $filters        Optional filter criteria passed to the repository.
	 * @param bool   $includeDeleted Whether to include deleted threads/posts.
	 * @param string $order          Field to sort threads by.
	 * @return array Array of thread preview structures.
	 */
	public function getFilteredThreads(int $previewCount, int $amount, int $offset = 0, array $filters = [], bool $includeDeleted = false, string $order = 'last_bump_time'): array {
		$threads = $this->threadRepository->fetchFilteredThreads($filters, $order, $amount, $offset, $includeDeleted);

		if (empty($threads)) return [];

		$threadUIDs = array_map(fn($t) => $t->getUid(), $threads);

		// get posts from thread
		$allPosts = $this->threadRepository->getPostsForThreads($threadUIDs, $previewCount, $includeDeleted);
		
		// get post counts
		$postsByThread = $this->groupPostsByThread($allPosts);

		$result = $this->buildPreviewResults($threads, $postsByThread, $previewCount);

		return $result;
	}

	/**
	 * Fetch a paginated list of thread UIDs for the given board.
	 *
	 * @param board  $board    Board object.
	 * @param int    $start    Pagination offset (number of threads to skip).
	 * @param int    $amount   Number of thread UIDs to return (0 = all).
	 * @param bool   $isDESC   True for descending, false for ascending.
	 * @param string $orderBy  Field to sort threads by.
	 * @return array Flat array of thread UIDs.
	 */
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

	/**
	 * Move all posts in a thread to a different board, reassigning post numbers and updating quote links.
	 * Runs inside a database transaction.
	 *
	 * @param mixed $thread_uid        UID of the thread to move.
	 * @param mixed $destinationBoard  Destination board object.
	 * @return void
	 */
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
				$oldPostNumber = $post->getNumber();
				$newPostNumber = ++$lastPostNumber;
				$postNumberMapping[$oldPostNumber] = $newPostNumber;

				$updatedCom = $this->updateQuoteReferences($post->getComment(), $postNumberMapping);

				$this->threadRepository->updatePostForBoardMove(
					$post->getUid(),
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

	/**
	 * Copy a thread and all its posts to another board as a new thread.
	 * Runs inside a database transaction; returns mapping data used by callers for finalising the copy.
	 *
	 * @param mixed $originalThreadUid  UID of the source thread.
	 * @param mixed $destinationBoard   Destination board object.
	 * @return array Map containing 'threadUid', 'postUidMap', and 'fileIdMapping'.
	 */
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
				/** @var Post $post */
				$newPostNumber = ++$lastPostNo;
				$postNumberMapping[$post->getNumber()] = $newPostNumber;
				$destinationBoard->incrementBoardPostNumber();

				$newPost = $this->mapPostData($post, $boardUID, $newPostNumber, $newThreadUid);
				$newPost['_original_uid'] = $post->getUid();
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

	/**
	 * Build the data array for a new post based on an original post being copied.
	 *
	 * @param Post   $post           Original post object.
	 * @param mixed  $boardUID       Destination board UID.
	 * @param int    $newPostNumber  New post number in the destination board.
	 * @param string $newThreadUid   UID of the new thread.
	 * @return array New post data array ready for insertion.
	 */
	private function mapPostData(Post $post, $boardUID, $newPostNumber, $newThreadUid) {
		return [
			'no'			=> $newPostNumber,
			'poster_hash'	=> $post->getPosterHash(),
			'boardUID'		=> $boardUID,
			'thread_uid'	=> $newThreadUid,
			'post_position' => $post->getPostPosition(),
			'is_op'			=> $post->isOp(),
			'root'			=> $post->getRoot(),
			'category'		=> $post->getCategory(),
			'pwd'			=> $post->getPassword(),
			'now'			=> $post->getTimestamp(),
			'name'			=> $post->getName(),
			'tripcode'		=> $post->getTripcode(),
			'secure_tripcode' => $post->getSecureTripcode(),
			'capcode'		=> $post->getCapcode(),
			'email'			=> $post->getEmail(),
			'sub'			=> $post->getSubject(),
			'com'			=> $post->getComment(),
			'host'			=> $post->getIp(),
			'status'		=> $post->getStatus()
		];
	}
	
	/**
	 * Rewrite >>postNo quote references in a comment using a mapping of old to new post numbers.
	 *
	 * @param string $comment            Post comment HTML.
	 * @param array  $postNumberMapping  Map of old post number => new post number.
	 * @return string Updated comment.
	 */
	private function updateQuoteReferences($comment, $postNumberMapping) {
		return preg_replace_callback('/&gt;&gt;(\d+)/', function ($matches) use ($postNumberMapping) {
			$oldQuote = $matches[1];
			return isset($postNumberMapping[$oldQuote]) ? '&gt;&gt;' . $postNumberMapping[$oldQuote] : $matches[0];
		}, $comment);
	}

	/**
	 * Copy file attachment records from original posts to copied posts, returning an old=>new file ID mapping.
	 *
	 * @param array $attachments    Attachment data arrays from the original thread.
	 * @param array $postUidMapping Map of old post UID => new post UID.
	 * @return array Map of old file ID => new file ID.
	 */
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

	/**
	 * Copy deletion entries from original posts to their copied counterparts, preserving soft-delete state.
	 *
	 * @param array $postUidMapping Map of old post UID => new post UID.
	 * @param array $fileIdMapping  Map of old file ID => new file ID.
	 * @return void
	 */
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

	/**
	 * Delete the oldest threads in the list that exceed the maximum thread limit.
	 *
	 * @param string[] $threadUidList     Ordered array of thread UIDs (newest-first).
	 * @param int      $maxThreadAmount   Maximum number of threads to retain.
	 * @return array|null Thread UIDs that were pruned, or empty array if none.
	 */
	public function pruneByAmount(array $threadUidList, int $maxThreadAmount, int $chunkSize = 50): ?array {
		// slice array to filter amount threads that are over the max thread amount limit.
		// Threads are ordered on last bump time
		$threadsToPrune = $this->getThreadAmountToPrune($threadUidList, $maxThreadAmount);

		// no threads to prune
		// so dont bother and return an empty array
		if(empty($threadsToPrune)) {
			return [];
		}

		// process in chunks to avoid running out of memory on large boards
		$chunks = array_chunk($threadsToPrune, $chunkSize);

		foreach ($chunks as $chunk) {
			$postUids = $this->threadRepository->getOpPostUidsFromThreads($chunk);
			$this->postService->removePosts($postUids);
		}

		// return the deleted thread uids
		return $threadsToPrune;
	}

	/**
	 * Return the subset of thread UIDs that exceed the maximum, with the oldest first.
	 *
	 * @param string[] $threadUidList    Ordered array of thread UIDs.
	 * @param int      $maxThreadAmount  Maximum number of threads to keep.
	 * @return array|null Thread UIDs to prune.
	 */
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

	/**
	 * Return the 1-based page number that the given thread appears on.
	 *
	 * @param string $threadUid      Thread UID.
	 * @param int    $threadsPerPage Number of threads per page.
	 * @return int Page number (defaults to 0 if not found).
	 */
	public function getPageOfThread(string $threadUid, int $threadsPerPage): int {
		// run repository method to get the page of the thread
		$threadPage = $this->threadRepository->getPageOfThread($threadUid, $threadsPerPage);
	
		// then return int (default to 0 if falsey/null)
		return $threadPage ?? 0;
	}
	
	/**
	 * Fetch the raw thread data row by thread UID.
	 *
	 * @param string $threadUid      Thread UID.
	 * @param bool   $includeDeleted Whether to return the thread if its OP is deleted.
	 * @return Thread|false Thread object, or false if not found.
	 */
	public function getThreadData(string $threadUid, bool $includeDeleted = false): Thread|false {
		// get thread by uid
		$threadData = $this->threadRepository->getThreadByUid($threadUid, $includeDeleted);

		// then return the result
		return $threadData;
	}
}