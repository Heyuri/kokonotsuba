<?php

namespace Kokonotsuba\post\deletion;

use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\attachment\fileService;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\thread\threadRepository;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\constructAttachment;
use function Kokonotsuba\libraries\constructAttachmentsFromArray;

/** Service for soft-deleting, restoring, and purging posts and attachments, with paged retrieval. */
class deletedPostsService {
	use TransactionalTrait;

	public function __construct(
		private transactionManager $transactionManager,
		private readonly deletedPostsRepository $deletedPostsRepository,
		private readonly fileService $fileService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly postRepository $postRepository,
		private readonly threadRepository $threadRepository
	) {}

	/**
	 * Restore a deleted post (or its entire thread if it is an OP) and its attachments.
	 *
	 * @param int $deletedPostId Deletion record ID.
	 * @param int $accountId     Account performing the restore.
	 * @return void
	 */
	public function restorePost(int $deletedPostId, int $accountId): void {
		// run transaction
		$this->inTransaction(function() use($deletedPostId, $accountId) {
			// get only the core columns needed for restore logic
			$deletedPost = $this->deletedPostsRepository->getDeletedPostCoreById($deletedPostId);
			
			// return early if deletedPost is null for whatever reason
			if(!$deletedPost) {
				return;
			}

			// check if the post was a reply to a deleted thread
			$isByProxy = $this->checkIfPostIsProxyDeleted($deletedPost);

			// return early if by proxy
			if($isByProxy) {
				return;
			}
			
			// whether its an op or not
			$isOp = $deletedPost->isOp();

			// if its a thread then restore all posts in it
			if($isOp) {
				$this->restoreThread($deletedPost, $accountId);
			} 
			// restore singular reply
			else {
				$this->restoreReply($deletedPost, $deletedPostId, $accountId);
			}
		});
	}

	private function restoreThread(DeletedPost $opPostData, int $accountId): void {
		// thread uid of the thread
		$threadUid = $opPostData->getThreadUid();

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
		$restoreActionString = $this->generateActionLoggingString($opPostData->getNumber(), false, true);

		// Log the restore action to the logging table
		$this->logAction($restoreActionString, $opPostData->getBoardUID());
	}

	private function restoreReply(DeletedPost $postData, int $deletedPostId, int $accountId): void {
		// post uid of the post
		$postUid = $postData->getUid();
		
		// get the reply attachments
		$postAttachments = $this->fileService->getAttachmentsForPost($postUid);

		// restore the attachments for the reply
		if(!empty($postAttachments)) {
			// then restore 'em
			$this->fileService->restoreAttachmentsFromPurgatory($postAttachments);
		}

		// restore the reply
		$this->deletedPostsRepository->restorePostData($deletedPostId, $accountId);

		// also restore any open file-only deletion entries for this post
		$this->deletedPostsRepository->restoreFileOnlyEntriesByPostUid($postUid, $accountId);

		// thread_uid of the post
		$threadUid = $postData->getThreadUid();
		
		// now update the thread's bump order
		$this->threadRepository->bumpThread($threadUid);

		// generate the logging string
		$restoreActionString = $this->generateActionLoggingString($postData->getNumber(), false, false);

		// Log the restore action to the logging table
		$this->logAction($restoreActionString, $postData->getBoardUID());
	}

	/**
	 * Restore a file-only deletion entry and move the attachment back from purgatory.
	 *
	 * @param int $deletedPostId Deletion record ID (file-only entry).
	 * @param int $accountId     Account performing the restore.
	 * @return void
	 * @throws BoardException If the file ID or attachment data is missing.
	 */
	public function restoreAttachment(int $deletedPostId, int $accountId): void {
		// get post data
		$deletedPostEntry = $this->deletedPostsRepository->getDeletedPostRowById($deletedPostId);

		// return early if postData is null for whatever reason
		if(!$deletedPostEntry) {
			return;
		}

		// get file ID
		$fileId = $deletedPostEntry->getFileId() ?? null;

		if(!$fileId) {
			throw new BoardException(_T('no_attachment_ever'));
		}

		// get attachment array by file ID
		$fileData = $deletedPostEntry->getAttachments()[$fileId] ?? false;

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
		$this->logAction("Restored attachment $fileId on post No.{$deletedPostEntry->getNumber()}", $deletedPostEntry->getBoardUID());
	}

	/**
	 * Permanently purge a deleted post (or entire thread if OP) and its files.
	 *
	 * @param int  $deletedPostId Deletion record ID.
	 * @param bool $logAction     Whether to log the purge action.
	 * @return void
	 */
	public function purgePost(int $deletedPostId, bool $logAction = true): void {
		// run transaction
		$this->inTransaction(function() use($deletedPostId, $logAction) {
			// get only the core columns needed for purge logic
			$deletedPost = $this->deletedPostsRepository->getDeletedPostCoreById($deletedPostId);
			
			// return early if deletedPost is null for whatever reason
			if(!$deletedPost) {
				return;
			}

			// check if the post was a reply to a deleted thread
			$isByProxy = $this->checkIfPostIsProxyDeleted($deletedPost);

			// return early if by proxy
			if($isByProxy) {
				return;
			}

			// whether its an op or not
			$isOp = $deletedPost->isOp();

			// if its a thread then purge the thread
			if($isOp) {
				$this->purgeThread($deletedPost, $logAction);
			} 
			// purge singular reply
			else {
				$this->purgeReply($deletedPost, $deletedPostId, $logAction);
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

	private function checkIfPostIsProxyDeleted(DeletedPost $post): bool {
		// if the post has bee deleted by proxy
		$byProxy = $post->isByProxy();

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

	private function purgeThread(DeletedPost $opPostData, bool $logAction = true): void {
		// thread uid
		$threadUid = $opPostData->getThreadUid();

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
			$purgeActionString = $this->generateActionLoggingString($opPostData->getNumber(), true, true);

			// Log the purge action to the logging table
			$this->logAction($purgeActionString, $opPostData->getBoardUID());
		}
	}

	private function purgeReply(DeletedPost $replyPostData, int $deletedPostId, bool $logAction = true): void {
		// post uid of the post
		$postUid = $replyPostData->getUid();

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
			$purgeActionString = $this->generateActionLoggingString($replyPostData->getNumber(), true, false);

			// Log the purge action to the logging table
			$this->logAction($purgeActionString, $replyPostData->getBoardUID());
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

	/**
	 * Permanently purge only the attachment from a file-only deletion entry.
	 *
	 * @param int $deletedPostId Deletion record ID (file-only entry).
	 * @return void
	 * @throws BoardException If the attachment data is missing.
	 */
	public function purgeAttachmentOnly(int $deletedPostId): void {
		// run transaction
		$this->inTransaction(function() use($deletedPostId) {
			// get deletion row
			$row = $this->deletedPostsRepository->getDeletedPostRowById($deletedPostId);

			if (!$row) {
				throw new BoardException(_T('attachment_not_found'));
			}

			// get attachments data
			$attachmentsData = $row->getAttachments();

			// get file id
			// this is the file id that will be used to select the attachment to purge
			$fileId = $row->getFileId();

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
			$purgeActionString = $this->generateFilePurgeLoggingString($row->getNumber());

			// Log the purge action to the logging table
			$this->logAction($purgeActionString, $row->getBoardUID());
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
		$page = max($page, 1);
		$entriesPerPage = max($entriesPerPage, 0);
		$offset = ($page - 1) * $entriesPerPage;

		return [$entriesPerPage, $offset];
	}

	private function returnOrNull($result): mixed {
		return $result === false ? null : $result;
	}

	/**
	 * Fetch a paged list of open (soft-deleted) posts.
	 *
	 * @param int   $page           Zero-based page number.
	 * @param int   $entriesPerPage Number of entries per page.
	 * @param array $filters        Additional filters (deleted_by_type, post_type, ip_address).
	 * @return array|null Array of deleted post entries, or null if none.
	 */
	public function getDeletedPosts(int $page, int $entriesPerPage, array $filters = []): ?array {
		// Fetch deleted posts for the given page range
		$deletedPosts = $this->getPagedEntries($page, $entriesPerPage, false, null, $filters);

		// Return the result array or null if empty
		return $deletedPosts;
	}

	/**
	 * Fetch a paged list of restored posts.
	 *
	 * @param int   $page           Zero-based page number.
	 * @param int   $entriesPerPage Number of entries per page.
	 * @param array $filters        Additional filters (deleted_by_type, post_type, ip_address).
	 * @return array|null Array of restored post entries, or null if none.
	 */
	public function getRestoredPosts(int $page, int $entriesPerPage, array $filters = []): ?array {
		// get paged restored posts
		$restoredPosts = $this->getPagedEntries($page, $entriesPerPage, true, null, $filters);

		// return the results
		return $restoredPosts;
	}
	
	/**
	 * Fetch a paged list of open (soft-deleted) posts for a specific account.
	 *
	 * @param int   $accountId      Account ID whose deletions to list.
	 * @param int   $page           Zero-based page number.
	 * @param int   $entriesPerPage Number of entries per page.
	 * @param array $filters        Additional filters (deleted_by_type, post_type, ip_address).
	 * @return array|null Array of deleted post entries, or null if none.
	 */
	public function getDeletedPostsByAccount(int $accountId, int $page, int $entriesPerPage, array $filters = []): ?array {
		// Fetch deleted posts for a specific account ID within the given page range
		$deletedPosts = $this->getPagedEntries($page, $entriesPerPage, false, $accountId, $filters);
		
		// return the deleted posts
		return $deletedPosts;
	}

	/**
	 * Fetch a paged list of restored posts for a specific account.
	 *
	 * @param int   $accountId      Account ID.
	 * @param int   $page           Zero-based page number.
	 * @param int   $entriesPerPage Number of entries per page.
	 * @param array $filters        Additional filters (deleted_by_type, post_type, ip_address).
	 * @return array|null Array of restored post entries, or null if none.
	 */
	public function getRestoredPostsByAccount(int $accountId, int $page, int $entriesPerPage, array $filters = []): ?array {
		// fetch the restored posts for a specific account by page
		$restoredPosts = $this->getPagedEntries($page, $entriesPerPage, true, $accountId, $filters);

		// return restored posts
		return $restoredPosts;
	}

	private function getPagedEntries(int $page, int $entriesPerPage, bool $restoredPostsOnly = false, ?int $accountId = null, array $filters = []): ?array {
		// Calculate pagination values (LIMIT and OFFSET)
		[$pageAmount, $pageOffset] = $this->getPaginationParams($page, $entriesPerPage);

		// Fetch entries for the given page range
		$entries = $this->deletedPostsRepository->getPagedEntries($pageAmount, $pageOffset, 'id', 'DESC', $restoredPostsOnly, $accountId, $filters);
	
		// Return the result array or null if empty
		return $this->returnOrNull($entries);
	}

	/**
	 * Fetch a single deletion row by its deletion record ID.
	 *
	 * @param int $deletedPostId Deletion record ID.
	 * @return DeletedPost|null Merged deletion row, or null if not found.
	 */
	public function getDeletedPostRowById(int $deletedPostId): ?DeletedPost {
		// get the single row from the db
		$deletedPostRow = $this->deletedPostsRepository->getDeletedPostRowById($deletedPostId);
		
		return $this->returnOrNull($deletedPostRow);
	}

	/**
	 * Return the total count of open soft-deleted posts.
	 *
	 * @param array $filters Additional filters (deleted_by_type, post_type, ip_address).
	 * @return int Total count.
	 */
	public function getTotalAmountOfDeletedPosts(array $filters = []): int {
		// get the total amount of deleted posts stored in the table
		$totalDeletedPosts = $this->deletedPostsRepository->getTotalAmountFiltered(false, null, $filters);

		// return the amount
		return $totalDeletedPosts;
	}

	/**
	 * Return the total count of restored posts.
	 *
	 * @param array $filters Additional filters (deleted_by_type, post_type, ip_address).
	 * @return int Total count.
	 */
	public function getTotalAmountOfRestoredPosts(array $filters = []): int {
		// get the total amount of restored posts stored in the table
		$totalAmount = $this->deletedPostsRepository->getTotalAmountFiltered(true, null, $filters);

		// return the amount
		return $totalAmount;
	}

	/**
	 * Return the total count of open deletions attributed to the given account.
	 *
	 * @param int   $accountId Account ID.
	 * @param array $filters   Additional filters (deleted_by_type, post_type, ip_address).
	 * @return int Total count.
	 */
	public function getTotalAmountOfDeletedPostsFromAccountId(int $accountId, array $filters = []): int {
		// get the total amount of deleleted posts 
		$totalAmount = $this->deletedPostsRepository->getTotalAmountFiltered(false, $accountId, $filters);
	
		// return result
		return $totalAmount;
	}

	/**
	 * Return the total count of restored posts attributed to the given account.
	 *
	 * @param int   $accountId Account ID.
	 * @param array $filters   Additional filters (deleted_by_type, post_type, ip_address).
	 * @return int Total count.
	 */
	public function getTotalAmountOfRestoredPostsFromAccountId(int $accountId, array $filters = []): int {
		// get the total amount of restored posts by account id
		$totalAmount = $this->deletedPostsRepository->getTotalAmountFiltered(true, $accountId, $filters);
	
		// return result
		return $totalAmount;
	}

	/**
	 * Check whether the given deletion record exists and belongs to the given account.
	 *
	 * @param int $deletedPostId Deletion record ID.
	 * @param int $accountId     Account ID to authenticate against.
	 * @return bool True if the record is owned by the account.
	 */
	public function authenticateDeletedPost(int $deletedPostId, int $accountId): bool {
		// check the database if the row exists and was deleted by the user
		$rowExists = $this->deletedPostsRepository->deletedPostExistsByAccountId($deletedPostId, $accountId);

		// return result
		return $rowExists;
	}

	/**
	 * Soft-delete one or more posts and their attachments, creating deletion records.
	 * Thread OPs automatically include all their replies as proxy-deleted entries.
	 *
	 * @param array    $posts     Array of post data arrays to delete.
	 * @param int|null $deletedBy Account ID performing the deletion, or null for anonymous.
	 * @return void
	 */
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
			return $item->isOp();
		});

		// get their thread uids
		$threadUids = array_map(fn($p) => $p->getThreadUid(), $openingPosts);

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
			if (!($row instanceof Post)) {
				continue; // skip non-Post rows
			}
			$key = (string)$row->getUid();
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
			$isOp = $post->isOp();

			// Check if the post is already deleted:
			// open_flag must be non-empty AND by_proxy must be 0 or null
			// (meaning it wasn't deleted by a proxy process)
			$isAlreadyDeleted = !empty($post->getOpenFlag()) 
								&& ($post->isByProxy() === false || is_null($post->isByProxy()));

			// A reply is any post that is not an OP.
			$isNotOp = !$isOp;

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
		$bUids = array_map(fn($p) => $p->getUid(), $b);
		$bUids = array_map('strval', $bUids); // normalize to string

		$result = [];
		foreach ($a as $row) {
			if (!($row instanceof Post)) continue;
			$key = (string)$row->getUid();

			if (!in_array($key, $bUids, true)) {
				$result[] = $row;
			}
		}

		return $result;
	}

	private function deleteAttachments(array $posts): void {
		// post uids
		$postUids = array_map(fn($p) => $p->getUid(), $posts);

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

	private function deletePost(Post $post, ?int $deletedBy , bool $fileOnly = false, bool $byProxy = false): void {
		// the post uid
		$postUid = $post->getUid();

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

	/**
	 * Create file-only deletion entries and move the associated attachment files to purgatory.
	 *
	 * @param array    $attachments Array of attachment data arrays.
	 * @param int|null $deletedBy   Account ID performing the deletion, or null for anonymous.
	 * @return void
	 */
	public function deleteFilesFromPosts(array $attachments, ?int $deletedBy): void {
		$this->inTransaction(function() use ($attachments, $deletedBy) {
			// Construct file objects from the attachments array
			$attachmentsToMove = constructAttachmentsFromArray($attachments);

			// return early if theres no attachments to move
			// doing this prevents query errors since an IN clause cant take no values
			if(!$attachmentsToMove) {
				return;
			}

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

	/**
	 * Return the board UID for the post referenced by the given deletion record ID.
	 *
	 * @param int $deletedPostId Deletion record ID.
	 * @return int|null Board UID, or null if not found.
	 */
	public function getBoardUidByDeletedPostId(int $deletedPostId): ?int {
		// fetch board uid from database based on the deleted id
		$boardUid = $this->deletedPostsRepository->getBoardUidByDeletedPostId($deletedPostId);

		// return board uid
		return $this->returnOrNull($boardUid);
	}

	/**
	 * Fetch the most-recent deletion row for the given post UID.
	 *
	 * @param int $postUid Post UID.
	 * @return DeletedPost|null Merged deletion row, or null if not found.
	 */
	public function getDeletedPostRowByPostUid(int $postUid): ?DeletedPost {
		// fetch the row by post uid
		$deletedPost = $this->deletedPostsRepository->getDeletedPostRowByPostUid($postUid);

		// return row
		return $this->returnOrNull($deletedPost);
	}

	/**
	 * Fetch the most-recent deletion row associated with the given file ID.
	 *
	 * @param int $fileId File row ID.
	 * @return DeletedPost|null Merged deletion row, or null if not found.
	 */
	public function getDeletedPostRowByFileId(int $fileId): ?DeletedPost {
		// fetch the row by file id
		$deletedPost = $this->deletedPostsRepository->getDeletedPostRowByFileId($fileId);

		// return row
		return $this->returnOrNull($deletedPost);
	}

	/**
	 * Prune all soft-deleted entries older than the given hour limit and permanently delete them.
	 *
	 * @param int $timeLimit Age cutoff in hours.
	 * @return void
	 */
	public function pruneExpiredPosts(int $timeLimit): void {
		// run transaction
		$this->inTransaction(function() use($timeLimit) {
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

	/**
	 * Remove a deletion record without purging the underlying post or file.
	 *
	 * @param int $deletedPostId Deletion record ID to remove.
	 * @return void
	 */
	public function removeEntry(int $deletedPostId): void {
		// run transaction
		$this->inTransaction(function() use($deletedPostId) {
			// run the method to delete the entry from the database
			$this->deletedPostsRepository->removeRowById($deletedPostId);
		});
	}

	/**
	 * Copy deletion entries from original posts to their copies, remapping post and file UIDs.
	 *
	 * @param int[] $hostPostUids Array of original post UIDs.
	 * @param int[] $newPostUids  Array of corresponding new post UIDs.
	 * @param int[] $hostFileIDs  Array of original file IDs.
	 * @param int[] $newFileIDs   Array of corresponding new file IDs.
	 * @return void
	 */
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