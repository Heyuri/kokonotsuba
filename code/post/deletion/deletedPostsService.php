<?php

class deletedPostsService {
	public function __construct(
		private transactionManager $transactionManager,
		private readonly deletedPostsRepository $deletedPostsRepository,
		private readonly fileService $fileService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly postRepository $postRepository,
		private readonly threadRepository $threadRepository
	) {}

	public function restorePost(int $deletedPostId, int $accountId): void {
		// run transaction
		$this->transactionManager->run(function() use($deletedPostId, $accountId) {
			// get the post data from the associated deleted posts row
			$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);
			
			// return early if postData is null for whatever reason
			if(!$postData) {
				return;
			}

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
		});
	}

	private function restoreThread(array $opPostData, int $accountId): void {
		// thread uid of the thread
		$threadUid = $opPostData['thread_uid'];

		// get attachments from the thread in order to restore them
		$threadAttachments = $this->fileService->getAttachmentsForThread($threadUid, true);

		// check if the attachments exist
		if(!empty($threadAttachments)) {
			// then restore 'em
			$this->fileService->restoreAttachmentsFromPurgatory($threadAttachments);
		}

		// restore the thread data
		// this will only restore replies that were deleted by proxy
		// replies deleted before the thread was deleted will stay deleted until manually restore
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

	public function restoreAttachment(int $deletedPostId, int $accountId): void {
		// get post data
		$deletedPostEntry = $this->deletedPostsRepository->getDeletedPostRowById($deletedPostId);

		// get file ID
		$fileId = $deletedPostEntry['file_id'] ?? null;

		if(!$fileId) {
			throw new BoardException(_T('no_attachment_ever'));
		}

		// get attachment array by file ID
		$fileData = $deletedPostEntry['attachments'][$fileId] ?? false;

		// return early if the attachment isn't found
		if(!$fileData) {
			throw new BoardException(_T('no_attachment_ever'));
		}

		// fetch the attachment from db
		$attachment = constructAttachment(
			$fileData['fileId'],
			$fileData['postUid'],
			$fileData['boardUID'],
			$fileData['fileName'],
			$fileData['storedFileName'],
			$fileData['fileExtension'],
			$fileData['fileMd5'],
			$fileData['fileWidth'],
			$fileData['fileHeight'],
			$fileData['thumbWidth'],
			$fileData['thumbHeight'],
			$fileData['fileSize'],
			$fileData['mimeType'],
			(bool)$fileData['isHidden'],
			$fileData['isDeleted'],
			$fileData['timestampAdded']
		);

		// move thread back to upload dir
		// also mark the file entry as restored
		$this->fileService->restoreAttachmentsFromPurgatory([$attachment]);

		// then mark the attachment data as restored
		$this->deletedPostsRepository->restorePostData($deletedPostId, $accountId);

		// Log the restore
		$this->logAction("Restored attachment $fileId on post No.{$deletedPostEntry['no']}", $deletedPostEntry['boardUID']);
	}

	public function purgePost(int $deletedPostId, bool $logAction = true): void {
		// run transaction
		$this->transactionManager->run(function() use($deletedPostId, $logAction) {
			// get the post data from the associated deleted posts row
			$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);
			
			// return early if postData is null for whatever reason
			if(!$postData) {
				return;
			}

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
		});
	}

	private function purgePostsFromList(array $deletedPostIdList, bool $attachmentsOnly = false): void {
		// There could be a query to do it all in one swoop but there's some things that need fine-grained logic (a la OP vs reply) which is tricky to recreate with a single query
		// will look into re-implementing a different way later
		
		// loop through IDs and purge the post
		foreach($deletedPostIdList as $id) {
			// if we're doing attachment-only pruning then only purge the attachment
			// leave the post intact
			if($attachmentsOnly) {
				$this->purgeAttachmentOnly($id);
			}
			// otherwise just purge the whole post
			else {
				// purge the post
				$this->purgePost($id, false);
			}
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

	public function purgeAttachmentOnly(int $deletedPostId): void {
		// run transaction
		$this->transactionManager->run(function() use($deletedPostId) {
			// get deletion row
			$row = $this->deletedPostsRepository->getDeletedPostRowById($deletedPostId);

			// get attachments data
			$attachmentsData = $row['attachments'];

			// get file id
			// this is the file id that will be used to select the attachment to purge
			$fileId = $row['file_id'];

			// the select attachment
			$fileData = $attachmentsData[$fileId] ?? false;

			// if there's no attachments then this isn't a file-only purge
			if(!$fileData) {
				// throw exception
				throw new BoardException(_T('attachment_not_found'));
			}

			// construct attachment object
			$attachmentToPurge = constructAttachment(
				$fileData['fileId'],
				$fileData['postUid'],
				$fileData['boardUID'],
				$fileData['fileName'],
				$fileData['storedFileName'],
				$fileData['fileExtension'],
				$fileData['fileMd5'],
				$fileData['fileWidth'],
				$fileData['fileHeight'],
				$fileData['thumbWidth'],
				$fileData['thumbHeight'],
				$fileData['fileSize'],
				$fileData['mimeType'],
				(bool)$fileData['isHidden'],
				$fileData['isDeleted'],
				$fileData['timestampAdded']
			);

			// delete the dp entry
			$this->deletedPostsRepository->removeRowById($deletedPostId);

			// purge attachment
			$this->fileService->purgeAttachmentsFromPurgatory([$attachmentToPurge]);

			// generate the logging string for the file purge
			$purgeActionString = $this->generateFilePurgeLoggingString($row['no']);

			// Log the purge action to the logging table
			$this->logAction($purgeActionString, $row['boardUID']);
		});

	}

	private function generateFilePurgeLoggingString(int $no): string {
		// generate file purge action for logger
		$actionString = "Purged attachment from post No.$no from system";

		// return the result
		return $actionString;
	}
	
	private function logAction(string $actionString, int $boardUid): void {
		// log the action
		$this->actionLoggerService->logAction($actionString, $boardUid);
	}

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
		
		return $this->returnOrNull($deletedPostRow);
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
		// as well as not already being deleted
		$replyPosts = $this->extractReplies($threadPosts);

		// make sure none were included in posts
		$exclusiveReplyPosts = $this->removeOverlap($replyPosts, $posts);

		// now mark replies as deleted by proxy
		$this->deleteMultiplePosts($exclusiveReplyPosts, $deletedBy, false, true);

		// mark attachments as deleted
		// do file operations afterwards just in case the deletePost call fails
		$this->deleteAttachments($mergedPosts);
	}

	private function getThreadsFromOPs(array $posts): array {
		// filter for OP posts
		$openingPosts = array_filter($posts, function($item) {
			return array_key_exists('is_op', $item) && $item['is_op'];
		});

		// get their thread uids
		$threadUids = array_column($openingPosts, 'thread_uid');

		// then fetch posts from those threads
		$threadPosts = $this->postRepository->getPostsByThreadUIDs($threadUids);

		// return empty array if false
		if(!$threadPosts) {
			return [];
		}
		else {
			// return thread posts
			return $threadPosts;
		}
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
			// Determine whether this post is an OP post (thread starter)
			$isOp = $post['is_op'] ?? null;

			// Check if the post is already deleted:
			// open_flag must be non-empty AND by_proxy must be 0 or null
			// (meaning it wasn’t deleted by a proxy process)
			$isAlreadyDeleted = !empty($post['open_flag']) 
								&& ($post['by_proxy'] === 0 || is_null($post['by_proxy']));

			// A reply is any post that is not an OP.
			// OP can be represented as 1, "1", or true depending on source,
			// so treat anything that is 0, "0", or null as a non-OP.
			$isNotOp = $isOp === 0 || $isOp === '0' || $isOp === null; 

			// Only include replies that are not already deleted
			if ($isNotOp && !$isAlreadyDeleted) {
				$result[] = $post;
			}
		}

		// Return the filtered list of replies
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
		// post uids
		$postUids = array_column($posts, 'post_uid');

		// now get the attachments from the post uids
		$attachments = $this->fileService->getAttachmentsFromPostUids($postUids);

		if(!empty($attachments)) {
			// move the attachment itself
			$this->fileService->moveFilesToPurgatory($attachments);
		}
	}

	private function deleteMultiplePosts(array $posts, ?int $deletedBy , bool $fileOnly = false, bool $byProxy = false): void {
		// loop through posts and delete each one
		foreach($posts as $p) {
			// mark post as deleted
			$this->deletePost($p, $deletedBy, $fileOnly, $byProxy);
		}
	}

	private function deletePost(array $post, ?int $deletedBy , bool $fileOnly = false, bool $byProxy = false): void {
		// the post uid
		$postUid = $post['post_uid'];

		// delete any pre-existing open entries in order to avoid conflicts
		// "open" meaning they aren't restored and are
		$this->deletedPostsRepository->removeOpenRows($postUid);

		// add a new row to the deleted posts table
		// this will automatically mark the post as deleted and make it hidden from regular users
		$this->deletedPostsRepository->insertDeletedPostEntry(
			$postUid, 
			$deletedBy,
			$fileOnly, 
			$byProxy,
		);
	}

	public function updateNote(int $deletedPostId, string $note): void {
		// run transaction
		$this->transactionManager->run(function() use($deletedPostId, $note) {
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
		});
	}

	public function deleteFilesFromPosts(array $attachments, ?int $deletedBy): void {
		$this->transactionManager->run(function() use ($attachments, $deletedBy) {
			// Construct file objects from the attachments array
			$attachmentsToMove = constructAttachmentsFromArray($attachments);

			// Insert deleted_posts rows for each attachment
			$this->insertDeletedPostRowsForFiles($attachmentsToMove, $deletedBy);

			// Move files to purgatory
			$this->fileService->moveFilesToPurgatory($attachmentsToMove);
		});
	}

	private function insertDeletedPostRowsForFiles(array $files, ?int $deletedBy): void {
		// Insert deleted_posts row for each attachment
		foreach ($files as $f) {
			$this->deletedPostsRepository->insertDeletedPostEntry(
				$f->getPostUid(),
				$deletedBy,
				true,       // file-only
				false,      // byProxy
				$f->getFileId()  // file_id
			);
		}
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
		// run transaction
		$this->transactionManager->run(function() use($timeLimit) {
			// get IDs from entires that are older than the time limit
			$prunedEntryIDs = $this->deletedPostsRepository->getExpiredEntryIDs($timeLimit);

			// return early if there were none
			if(!$prunedEntryIDs) {
				return;
			}

			// purge those posts from the ID list
			$this->purgePostsFromList($prunedEntryIDs);
			
			// now take care of attachment only deletions
			$prunedAttachmentIDs = $this->deletedPostsRepository->getExpiredEntryIDs($timeLimit, true);
		
			// then purge the files only
			$this->purgePostsFromList($prunedAttachmentIDs, true);
		});

	}

	public function removeEntry(int $deletedPostId): void {
		// run transaction
		$this->transactionManager->run(function() use($deletedPostId) {
			// run the method to delete the entry from the database
			$this->deletedPostsRepository->removeRowById($deletedPostId);
		});
	}

	public function copyDeletionEntries(
		array $hostPostUids, 
		array $newPostUids, 
		array $hostFileIDs, 
		array $newFileIDs
	): void {
		// copy the deletion entries - include the map so it knows which values to set the new entires to
		$this->deletedPostsRepository->copyDeletionEntries($hostPostUids, $newPostUids, $hostFileIDs, $newFileIDs);
	}
}