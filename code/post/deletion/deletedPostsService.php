<?php

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class deletedPostsService {
	public function __construct(
		private transactionManager $transactionManager,
		private readonly deletedPostsRepository $deletedPostsRepository,
		private readonly fileService $fileService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly postRepository $postRepository,
		private readonly threadRepository $threadRepository
	) {}

	public function purgeDeletedPostsByAccountId(int $accountId): void {
		// get the post uids
		$postUids = $this->getAllDeletedUidsByAccountId($accountId);

		// purge files from file system
		$this->fileService->purgeAttachmentsFromPurgatory($postUids);

		// purge the data from database
		$this->deletedPostsRepository->purgeDeletedPostsByAccountId($accountId);

		// generate purge all action string
		$actionString = "Purged all posts they've deleted.";

		// log it to the database
		$this->logAction($actionString, GLOBAL_BOARD_UID);
	}

	private function getAllDeletedUidsByAccountId(int $accountId): ?array {
		// get the post uids from the table
		$postUids = $this->deletedPostsRepository->getAllPostUidsFromAccountId($accountId);

		// return post uids
		return $this->returnOrNull($postUids);
	}

	public function restorePost(int $deletedPostId, int $accountId): void {
		// get the post data from the associated deleted posts row
		$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);
		
		// check if the post was a reply to a deleted thread
		$isByProxy = $this->checkIfPostIsProxyDeleted($postData);

		// return early if by proxy
		if($isByProxy) {
			return;
		}
		
		// whether its an op or not
		$isOp = $postData['is_op'] ?? 0;

		// if its a thread then restore all posts in it
		if($isOp) {
			$this->restoreThread($postData, $accountId);
		} 
		// restore singular reply
		else {
			$this->restoreReply($postData, $deletedPostId, $accountId);
		}
	}

	private function restoreThread(array $opPostData, int $accountId): void {
		// thread uid of the thread
		$threadUid = $opPostData['thread_uid'];

		// get attachments from the thread in order to restore them
		$threadAttachments = $this->fileService->getAttachmentsForThread($threadUid);

		// check if the attachments exist
		if(!empty($threadAttachments)) {
			// then restore 'em
			$this->fileService->restoreAttachmentsFromPurgatory($threadAttachments);
		}

		// restore the thread data
		$this->deletedPostsRepository->restorePostsByThreadUid($threadUid, $accountId);

		// generate the logging string
		$restoreActionString = $this->generateActionLoggingString($opPostData['no'], false, true);

		// Log the restore action to the logging table
		$this->logAction($restoreActionString, $opPostData['boardUID']);
	}

	private function restoreReply(array $postData, int $deletedPostId, int $accountId): void {
		// post uid of the post
		$postUid = $postData['post_uid'];
		
		// get the reply attachments
		$postAttachments = $this->fileService->getAttachmentsForPost($postUid);

		// restore the attachments for the reply
		if(!empty($postAttachments)) {
			// then restore 'em
			$this->fileService->restoreAttachmentsFromPurgatory($postAttachments);
		}

		// restore the reply
		$this->deletedPostsRepository->restorePostData($deletedPostId, $accountId);

		// thread_uid of the post
		$threadUid = $postData['thread_uid'];
		
		// now update the thread's bump order
		$this->threadRepository->bumpThread($threadUid);

		// generate the logging string
		$restoreActionString = $this->generateActionLoggingString($postData['no'], false, false);

		// Log the restore action to the logging table
		$this->logAction($restoreActionString, $postData['boardUID']);
	}

	public function purgePost(int $deletedPostId, bool $logAction = true): void {
		// get the post data from the associated deleted posts row
		$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);
		
		// check if the post was a reply to a deleted thread
		$isByProxy = $this->checkIfPostIsProxyDeleted($postData);

		// return early if by proxy
		if($isByProxy) {
			return;
		}

		// whether its an op or not
		$isOp = $postData['is_op'] ?? 0;

		// if its a thread then purge the thread
		if($isOp) {
			$this->purgeThread($postData, $logAction);
		} 
		// purge singular reply
		else {
			$this->purgeReply($postData, $deletedPostId, $logAction);
		}
	}

	private function purgePostsFromList(array $deletedPostIdList, bool $logAction = true): void {
		// There could be a query to do it all in one swoop but there's some things that need fine-grained logic (a la OP vs reply) which is tricky to recreate with a single query
		// will look into re-implementing a different way later
		
		// loop through IDs and purge the post
		foreach($deletedPostIdList as $id) {
			// purge the post
			$this->purgePost($id, $logAction);
		}
	}

	private function checkIfPostIsProxyDeleted(array $post): bool {
		// if the post has bee deleted by proxy
		$byProxy = $post['by_proxy'] ?? 0;

		// don't do anything if its trying to restore a post thats deleted by-proxy
		// otherwise, there will be unexpected behavior (potentially)
		if($byProxy) {
			return true;
		} 
		// this post was not deleted by proxy,
		// good!
		else {
			return false;
		}
	}

	private function purgeThread(array $opPostData, bool $logAction = true): void {
		// thread uid
		$threadUid = $opPostData['thread_uid'];

		// get all attachments from the thread
		$threadAttachments = $this->fileService->getAttachmentsForThread($threadUid);

		// check if the attachments exist
		if(!empty($threadAttachments)) {
			// then purge 'em
			$this->fileService->purgeAttachmentsFromPurgatory($threadAttachments);
		}

		// then purge the thread data (including the posts)
		$this->threadRepository->deleteThreadByUID($threadUid);

		if($logAction) {
			// generate the logging string
			$purgeActionString = $this->generateActionLoggingString($opPostData['no'], true, true);

			// Log the purge action to the logging table
			$this->logAction($purgeActionString, $opPostData['boardUID']);
		}
	}

	private function purgeReply(array $replyPostData, int $deletedPostId, bool $logAction = true): void {
		// post uid of the post
		$postUid = $replyPostData['post_uid'];

		// get all attachments for the post
		$postAttachments = $this->fileService->getAttachmentsForPost($postUid);

		// purge attachment if exists
		if(!empty($postAttachments)) {
			// purge attachment
			$this->fileService->purgeAttachmentsFromPurgatory($postAttachments);
		}

		// purge the post data from the database
		// this will also delete the post from the posts table rather than hiding it
		$this->deletedPostsRepository->purgeDeletedPostById($deletedPostId);

		if($logAction) {
			// generate the logging string
			$purgeActionString = $this->generateActionLoggingString($replyPostData['no'], true, false);

			// Log the purge action to the logging table
			$this->logAction($purgeActionString, $replyPostData['boardUID']);
		}
	}

	private function generateActionLoggingString(int $no, bool $isPurge, bool $isThread): string {
		// post type
		$postType = '';

		// if it's an OP then its a whole thread
		if($isThread) {
			$postType = 'thread';
		}
		// if its not an OP then its a reply (post)
		else {
			$postType = 'post';
		}

		// action type
		$actionType = '';

		// purge
		if($isPurge) {
			$actionType = 'Purged';
		}
		// restore
		else {
			$actionType = 'Restored';
		}


		// generate purge action for logger
		$actionString = "$actionType $postType No.$no";

		// return the result
		return $actionString;
	}

	public function purgeFileOnly(int $deletedPostId, ?int $deletedBy ): void {
		// get post data and attachments for file-only purge
		[$postData, $attachments] = $this->getPostDataAndAttachments($deletedPostId);

		// if there's no attachments then this isn't a file-only purge
		if(!$attachments) {
			// throw exception
			throw new BoardException("This post doesn't have any attachments!");
		}

		// flag the post as no longer deleted - special case
		$this->deletedPostsRepository->restorePostData($deletedPostId, $deletedBy);

		// now purge the attachments
		// check again just in case
		if(!empty($attachments)) {
			// purge attachment
			$this->fileService->purgeAttachmentsFromPurgatory($attachments);
		}

		// generate the logging string for the file purge
		$purgeActionString = $this->generateFilePurgeLoggingString($postData['no']);

		// Log the purge action to the logging table
		$this->logAction($purgeActionString, $postData['boardUID']);
	}

	private function generateFilePurgeLoggingString(int $no): string {
		// generate file purge action for logger
		$actionString = "Purged file from post No.$no from system";

		// return the result
		return $actionString;
	}
	
	private function logAction(string $actionString, int $boardUid): void {
		// log the action
		$this->actionLoggerService->logAction($actionString, $boardUid);
	}

/*	The following can possibly be salvaged later
	
	public function purgePostsFromList(array $deletedPostsList): void {
		// get the post data from the list
		$posts = $this->deletedPostsRepository->getPostsByIdList($deletedPostsList);
		//echo '<pre>'; print_r($posts); exit;
		// filter specifically for the post uids
		$postUidList = array_column($posts, 'post_uid');

		// get the attachments
		$attachments = $this->fileService->getAttachmentsFromPostUids($postUidList);

		// purge all attachments from list
		// now purge the attachments
		// check again just in case
		if(!empty($attachments)) {
			// purge attachment
			$this->fileService->purgeAttachmentsFromPurgatory($attachments);
		}

		// purge the posts in the list
		$this->deletedPostsRepository->purgeDeletedPostsFromList($deletedPostsList);

		// log the list purging action to the logging table
		$this->logMultiplePurges($posts);
	}

	private function logMultiplePurges(array $postList): void {
		// loop through and log each purge
		foreach($postList as $post) {
			// get the post number
			$no = $post['no'];

			// board uid of the post
			$boardUid = $post['boardUID'];

			// whether its an op
			$isOp = $post['is_op'];

			// purge string for log
			$purgeActionString = $this->generateActionLoggingString($no, true, $isOp);

			// log the purge
			$this->logAction($purgeActionString, $boardUid);
		}
	}*/

	private function getPaginationParams(int $page, int $entriesPerPage): array {
		$page = max($page, 0);
		$entriesPerPage = max($entriesPerPage, 0);
		$offset = $page * $entriesPerPage;

		return [$entriesPerPage, $offset];
	}

	private function returnOrNull($result): mixed {
		return $result === false ? null : $result;
	}

	public function getDeletedPosts(int $page, int $entriesPerPage): ?array {
		// Fetch deleted posts for the given page range
		$deletedPosts = $this->getPagedEntries($page, $entriesPerPage);

		// Return the result array or null if empty
		return $deletedPosts;
	}

	public function getRestoredPosts(int $page, int $entriesPerPage): ?array {
		// get paged restored posts
		$restoredPosts = $this->getPagedEntries($page, $entriesPerPage, true);

		// return the results
		return $restoredPosts;
	}
	
	public function getDeletedPostsByAccount(int $accountId, int $page, int $entriesPerPage): ?array {
		// Fetch deleted posts for a specific account ID within the given page range
		$deletedPosts = $this->getPagedEntries($page, $entriesPerPage, false, $accountId);
		
		// return the deleted posts
		return $deletedPosts;
	}

	public function getRestoredPostsByAccount(int $accountId, int $page, int $entriesPerPage): ?array {
		// fetch the restored posts for a specific account by page
		$restoredPosts = $this->getPagedEntries($page, $entriesPerPage, true, $accountId);

		// return restored posts
		return $restoredPosts;
	}

	private function getPagedEntries(int $page, int $entriesPerPage, bool $restoredPostsOnly = false, ?int $accountId = null): ?array {
		// Calculate pagination values (LIMIT and OFFSET)
		[$pageAmount, $pageOffset] = $this->getPaginationParams($page, $entriesPerPage);

		// Fetch entries for the given page range
		$entries = $this->deletedPostsRepository->getPagedEntries($pageAmount, $pageOffset, 'id', 'DESC', $restoredPostsOnly, $accountId);
	
		// Return the result array or null if empty
		return $this->returnOrNull($entries);
	}

	public function getDeletedPostRowById(int $deletedPostId): ?array {
		// get the single row from the db
		$deletedPostRow = $this->deletedPostsRepository->getDeletedPostRowById($deletedPostId);
		
		// doesn't exist, return null
		if(!$deletedPostRow) {
			return null;
		} else {
			// return the data if it exists, return null if not
			return $deletedPostRow;
		}
	}

	public function getTotalAmountOfDeletedPosts(): int {
		// get the total amount of deleted posts stored in the table
		$totalDeletedPosts = $this->deletedPostsRepository->getTotalAmount();

		// return the amount
		return $totalDeletedPosts;
	}

	public function getTotalAmountOfRestoredPosts(): int {
		// get the total amount of restored posts stored in the table
		$totalAmount = $this->deletedPostsRepository->getTotalAmount(null, true);

		// return the amount
		return $totalAmount;
	}

	public function getTotalAmountOfDeletedPostsFromAccountId(int $accountId): int {
		// get the total amount of deleleted posts 
		$totalAmount = $this->deletedPostsRepository->getTotalAmount($accountId);
	
		// return result
		return $totalAmount;
	}

	public function getTotalAmountOfRestoredPostsFromAccountId(int $accountId): int {
		// get the total amount of restored posts by account id
		$totalAmount = $this->deletedPostsRepository->getTotalAmount($accountId, true);
	
		// return result
		return $totalAmount;
	}

	public function authenticateDeletedPost(int $deletedPostId, int $accountId): bool {
		// check the database if the row exists and was deleted by the user
		$rowExists = $this->deletedPostsRepository->deletedPostExistsByAccountId($deletedPostId, $accountId);

		// return result
		return $rowExists;
	}

	public function flagPostsAsDeleted(array $posts, ?int $deletedBy): void {
		// get all posts from the thread if any of the posts are thread OPs
		$threadPosts = $this->getThreadsFromOPs($posts);

		// then merge 'em
		$mergedPosts = array_merge($threadPosts, $posts);

		// also remove any duplicates from $posts
		$mergedPosts = $this->removeDuplicatesByPostUid($mergedPosts);

		// mark the post sas deleted
		// its ONLY done for the posts orginally selected so thread replies dont show up as spearate entries in the mod page
		$this->deleteMultiplePosts($posts, $deletedBy);
		
		// take replies gotten through thread posts
		// get all posts where `is_op` is 0 or null
		$replyPosts = $this->extractReplies($threadPosts);

		// make sure none were included in posts
		$exclusiveReplyPosts = $this->removeOverlap($replyPosts, $posts);

		// now mark replies as deleted by proxy
		$this->deleteMultiplePosts($exclusiveReplyPosts, $deletedBy, false, true);

		// mark attachments as deleted
		// do file operations afterwards just in case the deletePost call fails
		$this->deleteAttachments($mergedPosts);
	}

	private function getThreadsFromOPs(array $posts): ?array {
		// filter for OP posts
		$openingPosts = array_filter($posts, function($item) {
		    return array_key_exists('is_op', $item) && $item['is_op'];
		});

		// get their thread uids
		$threadUids = array_column($openingPosts, 'thread_uid');

		// then fetch posts from those threads
		$threadPosts = $this->postRepository->getPostsByThreadUIDs($threadUids);

		// return thread posts
		return $threadPosts;
	}

	private function removeDuplicatesByPostUid(array $posts): array {
		$seen = [];
		$out = [];

		foreach ($posts as $row) {
			if (!is_array($row) || !array_key_exists('post_uid', $row)) {
				continue; // skip rows without post_uid
			}
			$key = (string)$row['post_uid'];
			if ($key === '' || $key === '0') {
				continue; // skip empty/invalid ids (optional)
			}
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$out[] = $row; // keep first occurrence
			}
		}

		return $out;
	}

	private function extractReplies(array $posts): array {
		$result = [];

		foreach ($posts as $post) {
			$isOp = $post['is_op'] ?? null;

			if ($isOp === 0 || $isOp === '0' || $isOp === null) {
				$result[] = $post;
			}
		}

		return $result;
	}

	private function removeOverlap(array $a, array $b): array {
		// collect all post_uid values from $b
		$bUids = array_column($b, 'post_uid');
		$bUids = array_map('strval', $bUids); // normalize to string

		$result = [];
		foreach ($a as $row) {
			$key = isset($row['post_uid']) ? (string)$row['post_uid'] : null;

			if ($key !== null && !in_array($key, $bUids, true)) {
				$result[] = $row;
			}
		}

		return $result;
	}

	private function deleteAttachments(array $posts): void {
		// add attachments to the fiels table and mark them as hidden (deleted)
		// in the future we'll only need to mark them as deleted instead of adding new rows
		$this->addDeletedAttachments($posts);

		// post uids
		$postUids = array_column($posts, 'post_uid');

		// now get the attachments from the post uids
		$attachments = $this->fileService->getAttachmentsFromPostUids($postUids);

		if(!empty($attachments)) {
			// move the attachment itself
			$this->fileService->moveFilesToPurgatory($attachments);
		}
	}

	private function addDeletedAttachments(array $posts): void {
		// loop through and insert new files to table (as hidden)
		foreach($posts as $post) {
			// it doesn't have an attachment - skip
			if(empty($post['ext'])) {
				continue;
			}

			// add deleted attachment + thumbnail
			// continue loop if the adding failed
			if(!$this->addDeletedAttach($post)) {
				continue;
			}
		}
	}

	private function addDeletedAttach(array $post): bool {
		// board uid
		$boardUid = $post['boardUID'];
		
		// get the board
		$board = searchBoardArrayForBoard($boardUid);

		// get the main file directory
		$boardMainUploadDirectory = $board->getBoardUploadedFilesDirectory() . $board->getConfigValue('IMG_DIR');

		// attachment-related data from the post row
		$postUid = $post['post_uid'];
		$extension = $post['ext'];
		$fileName = $post['fname'];
		$storedFileName = $post['tim'];
		$md5Hash = $post['md5chksum'];
		$width = $post['imgw'];
		$height = $post['imgh'];
		$thumbWidth = $post['tw'];
		$thumbHeight = $post['th'];
		$fileSize = $post['imgsize'];
		$mimeType = '';

		// since the file is deleted - make sure its hidden
		$isHidden = true; 

		// remove the beginning full stop in ext
		$extension = substr($extension, 1);

		// extract digits from the fimgsize and cast it to an integer
		$fileSize = (int) preg_replace('/\D/', '', $fileSize);

		// file path + filename
		$filePath = $boardMainUploadDirectory . $storedFileName . '.' . $extension;

		// if the file doesn't exist then skip
		if(!file_exists($filePath)) {
			return false;
		}

		// then add the file to the row - with is_hidden set to true
		$this->fileService->addFile(
			$postUid,
			$fileName,
			$storedFileName,
			$extension,
			$md5Hash,
			$width,
			$height,
			$thumbWidth,
			$thumbHeight,
			$fileSize,
			$mimeType,
			$isHidden,
			false
		);

		// succeeded
		return true;
	}

	private function deleteMultiplePosts(array $posts, ?int $deletedBy , bool $fileOnly = false, bool $byProxy = false): void {
		// loop through posts and delete each one
		foreach($posts as $p) {
			// mark post as deletd
			$this->deletePost($p, $deletedBy, $fileOnly, $byProxy);
		}
	}

	private function deletePost(array $post, ?int $deletedBy , bool $fileOnly = false, bool $byProxy = false): void {
		// the post uid
		$postUid = $post['post_uid'];

		// add a new row to the deleted posts table
		// this will automatically mark the post as deleted and make it hidden from regular users
		$this->deletedPostsRepository->insertDeletedPostEntry($postUid, $deletedBy, $fileOnly, $byProxy);
	}

	public function updateNote(int $deletedPostId, string $note): void {
		// update the note for that deleted post
		$this->deletedPostsRepository->updateDeletedPostNoteById($deletedPostId, $note);

		// get the post data from the associated deleted posts row
		$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);

		// board uid of the post
		$boardUid = $postData['boardUID'];

		// post number
		$no = $postData['no'];

		// log adding the note
		$this->actionLoggerService->logAction("Updated note on post No.$no", $boardUid);
	}

	public function deleteFilesFromPosts(array $posts, ?int $deletedBy): void {
		// mark posts as file-only deleted
		$this->deleteMultiplePosts($posts, $deletedBy, true, false);

		// add the attachments
		$this->addDeletedAttachments($posts);

		// get the post uids
		$postUids = array_column($posts, 'post_uid');

		// now get the newly inserted attachments
		$attachments = $this->fileService->getAttachmentsFromPostUids($postUids);

		if(!empty($attachments)) {
			// move the attachment itself
			$this->fileService->moveFilesToPurgatory($attachments);
		}
	}

	private function getPostDataAndAttachments(int $deletedPostId): array {
		// get the post data from the associated deleted posts row
		$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);

		// post uid of the post
		$postUid = $postData['post_uid'];

		// get attachments for the post
		$attachments = $this->fileService->getAttachmentsForPost($postUid);

		// return the pairs
		return [$postData, $attachments];
	}

	public function getBoardUidByDeletedPostId(int $deletedPostId): ?int {
		// fetch board uid from database based on the deleted id
		$boardUid = $this->deletedPostsRepository->getBoardUidByDeletedPostId($deletedPostId);

		// return board uid
		return $this->returnOrNull($boardUid);
	}

	public function getDeletedPostRowByPostUid(int $postUid): ?array {
		// fetch the row by post uid
		$deletedPost = $this->deletedPostsRepository->getDeletedPostRowByPostUid($postUid);

		// return row
		return $this->returnOrNull($deletedPost);
	}

	public function pruneExpiredPosts(int $timeLimit): void {
		// get IDs from entires that are older than the time limit
		$prunedEntryIDs = $this->deletedPostsRepository->getExpiredEntryIDs($timeLimit);

		// return early if there were none
		if(!$prunedEntryIDs) {
			return;
		}

		// purge those posts from the ID list
		$this->purgePostsFromList($prunedEntryIDs, false);
	}

	public function removeEntry(int $deletedPostId): void {
		// run the method to delete the entry from the database
		$this->deletedPostsRepository->removeRowById($deletedPostId);
	}
}