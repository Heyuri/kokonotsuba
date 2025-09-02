<?php

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class deletedPostsService {
	public function __construct(
		private transactionManager $transactionManager,
		private readonly deletedPostsRepository $deletedPostsRepository,
		private readonly attachmentService $attachmentService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly postRepository $postRepository
	) {}

	public function purgeDeletedPostsByAccountId(int $accountId): void {
		// get the post uids
		$postUids = $this->getAllDeletedUidsByAccountId($accountId);

		// purge files from file system
		$this->attachmentService->removeAttachmentsFromPurgatory($postUids, true);

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

		// return null if its false
		if(!$postUids) {
			return null;
		} 
		// otherwise return the actual result
		else {
			return $postUids;
		}
	}

	public function restorePost(int $deletedPostId, int $accountId): void {
		// now, restore the file if the post has one
		$this->attachmentService->restoreAttachmentsFromPurgatory($deletedPostId);
		
		// restore the post data by flagging it as not deleted
		$this->deletedPostsRepository->restorePostData($deletedPostId, $accountId);
	
		// get the post data from the associated deleted posts row
		$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);

		// generate the logging string
		$restoreActionString = $this->generateRestoreLoggingString($postData['no']);

		// Log the purge action to the logging table
		$this->logAction($restoreActionString, $postData['boardUID']);
	}

	private function generateRestoreLoggingString(int $no): string {
		// generate purge action for logger
		$actionString = "Restored post No.$no.";

		// return the result
		return $actionString;
	}

	public function purgeAllDeletedPosts(): void {
		// 
	}

	public function purgePost(int $deletedPostId): void {
		// get the post data from the associated deleted posts row
		$postData = $this->deletedPostsRepository->getPostByDeletedPostId($deletedPostId);

		// generate the logging string before purging the data
		$purgeActionString = $this->generatePurgeLoggingString($postData['no']);

		// delete the associated files from the filesystem
		$this->attachmentService->purgeAttachmentsFromPurgatory($deletedPostId);

		// purge the post data from the database
		// this will also delete the post from the posts table rather than hiding it
		$this->deletedPostsRepository->purgeDeletedPostsById($deletedPostId);

		// Log the purge action to the logging table
		$this->logAction($purgeActionString, $postData['boardUID']);
	}

	private function generatePurgeLoggingString(int $no): string {
		// generate purge action for logger
		$actionString = "Purged Post No.$no from system.";

		// return the result
		return $actionString;
	}
	
	private function logAction(string $actionString, int $boardUid): void {
		// log the action
		$this->actionLoggerService->logAction($actionString, $boardUid);
	}

	public function purgePostsFromList(array $deletedPostsList): void {
		// get the post data from the list
		$listData = $this->postRepository->getPostsByUids($deletedPostsList);

		// purge the posts in the list
		$this->deletedPostsRepository->purgeDeletedPostsFromList($deletedPostsList);

		// purge all attachments from list
		$this->attachmentService->purgeAttachmentsFromPurgatory($deletedPostsList);

		// log the list purging action to the logging table
		$this->logMultipleActions($listData);
	}

	private function logMultipleActions(array $listData): void {
		//
	}

	private function getPaginationParams(int $page, int $entriesPerPage): array {
		$page = max($page, 0);
		$entriesPerPage = max($entriesPerPage, 0);
		$offset = $page * $entriesPerPage;

		return [$entriesPerPage, $offset];
	}

	private function returnOrNull($result): ?array {
		return $result === false ? null : $result;
	}

	public function getDeletedPosts(int $page, int $entriesPerPage): ?array {
		[$pageAmount, $pageOffset] = $this->getPaginationParams($page, $entriesPerPage);

		$deletedPosts = $this->deletedPostsRepository->getDeletedPosts($pageAmount, $pageOffset);

		return $this->returnOrNull($deletedPosts);
	}

	public function getDeletedPostsByAccount(int $accountId, int $page, int $entriesPerPage): ?array {
		[$pageAmount, $pageOffset] = $this->getPaginationParams($page, $entriesPerPage);

		$deletedPosts = $this->deletedPostsRepository->getDeletedPostsByAccountId($accountId, $pageAmount, $pageOffset);

		return $this->returnOrNull($deletedPosts);
	}

	public function getTotalAmount(): int {
		// get the total amount of deleted posts stored in the table
		$totalDeletedPosts = $this->deletedPostsRepository->getTotalAmountOfDeletedPosts();

		// return the amount
		return $totalDeletedPosts;
	}

	public function getTotalAmountFromAccountId(int $accountId): int {
		// get the total amount of deleleted posts 
		$totalAmountOfDeletedPostsByAccountId = $this->deletedPostsRepository->getTotalAmountOfDeletedPostsByAccountId($accountId);
	
		// return result
		return $totalAmountOfDeletedPostsByAccountId;
	}

	public function authenticateDeletedPost(int $deletedPostId, int $accountId): bool {
		// check the database if the row exists and was deleted by the user
		$rowExists = $this->deletedPostsRepository->deletedPostExistsByAccountId($deletedPostId, $accountId);

		// return result
		return $rowExists;
	}

	public function authenticateDeletedPostList(array $deletedPostsList, int $accountId): array {
		// return an array of IDs from $deletedPostsList to filter out the deleted posts the user isn't authorized to delete
		$newDeletedPostsList = $this->deletedPostsRepository->getIDsFromListByAccountId($deletedPostsList, $accountId);
	
		// return the array
		return $newDeletedPostsList;
	}
}