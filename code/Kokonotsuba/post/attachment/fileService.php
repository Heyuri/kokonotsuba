<?php

namespace Kokonotsuba\post\attachment;

use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\createDirectory;

/** Service for managing attachment file records and physical file purgatory movement. */
class fileService {
	public function __construct(
		private readonly fileRepository $fileRepository
	) {
		// get attachments dir
		$attachmentsDir = getGlobalAttachmentDirectory();

		// create the attachments directory if it doesn't exist
		createDirectory($attachmentsDir);
	}

	/**
	 * Move soft-deleted attachment files back to their board directories and un-hide their records.
	 *
	 * @param attachment[] $attachments Array of attachment objects to restore.
	 * @return void
	 */
	public function restoreAttachmentsFromPurgatory(array $attachments): void {
		// get file IDs
		$fileIDs = $this->getFileIDsFromAttachments($attachments);

		// mark each file row as no longer hidden
		$this->fileRepository->setHiddenStatuses($fileIDs, false);

		// also mark each file
		$this->markAttachmentsAsRestored($fileIDs);
		
		// move the files out of the attachment directory back to their respective boards
		$this->restoreFileLocation($attachments);
	}

	private function restoreFileLocation(array $attachments): void {
		// loop through the attachments and move the files to the upload path 
		foreach($attachments as $attach) {
			// full path of the hidden attachment path
			$currentPath = $attach->getHiddenPath();

			// full path of the new attachment path
			$destinationImgDirectory = $attach->getUploadPath();
		
			// move the file
			$this->sanitizeAndMove($currentPath, $destinationImgDirectory);
	
			// full path of the hidden thumbnail
			$currentThumbPath = $attach->getHiddenPath(true);

			// full path of the new thumb directory
			$destinationThumbDirectory = $attach->getUploadPath(true);

			// move the thumbnail
			$this->sanitizeAndMove($currentThumbPath, $destinationThumbDirectory);
		}
	}

	/**
	 * Permanently delete the physical files for purged attachments (file rows are left intact).
	 *
	 * @param attachment[] $attachments Array of attachment objects to purge.
	 * @return void
	 */
	public function purgeAttachmentsFromPurgatory(array $attachments): void {
		// delete the actual files
		$this->deleteAttachmentFiles($attachments);

		// leave the file rows
		// the file rows can be deleted when the associated post is purged.
		// This is so file-deletions will still retain the file name and 'file deleted' .
		// otherwise it'd be rendered as if there never was an attachment since there's no way to track it after purging
	}

	private function deleteAttachmentFiles(array $attachments): void {
		// loop through attachments and delete files
		foreach($attachments as $attach) {
			// get the path of the attachment's file
			$filePath = $attach->getPath();

			// remove the file
			$this->removeFile($filePath);

			// and remove the thumbnail
			$thumbnailPath = $attach->getPath(true);

			// remove the thumb
			$this->removeFile($thumbnailPath);
		}
	}

	private function removeFile(string $path): void {
		// sanitize to prevent path traversal
		$path = realpath($path);

		// check if the file exists
		if(file_exists($path)) {
			// remove the file
			unlink($path);
		}
	}

	/**
	 * Move attachment files to the purgatory directory and mark their records as hidden and deleted.
	 *
	 * @param attachment[] $attachments Array of attachment objects to move to purgatory.
	 * @return void
	 */
	public function moveFilesToPurgatory(array $attachments): void {
		// get file IDs
		$fileIDs = $this->getFileIDsFromAttachments($attachments);

		// mark the files as hidden so it knows its in purgatory
		$this->fileRepository->setHiddenStatuses($fileIDs, true);

		// mark the files as deleted
		$this->markAttachmentsAsDeleted($fileIDs); 

		// move the files
		$this->moveFilesToDirectory($attachments);
	}

	private function getFileIDsFromAttachments(array $attachments): array {
		// init array to store file ids
		$fileIds = [];

		// loop through and get file IDs from attachments
		foreach($attachments as $attach) {
			// get the file id of the attachment
			$fileId = $attach->getFileId();
			
			// add it to the array
			$fileIds[] = $fileId;
		}

		// return the file ids
		return $fileIds;
	}

	private function moveFilesToDirectory(array $attachments): void {	
		// loop through attachments and move the file
		foreach($attachments as $attach) {
			// full path of the file
			$fullPath = $attach->getUploadPath();
			
			// get the path of the hidden attachment
			$attachmentDestinationPath = $attach->getHiddenPath();

			// sanitize and move attachment
			$this->sanitizeAndMove($fullPath, $attachmentDestinationPath);

			// get the thumbnail path
			$thumbnailPath = $attach->getUploadPath(true);

			// get the path of the hidden thumbnail
			$thumbDestinationPath = $attach->getHiddenPath(true);

			// sanitize and move attachment thumbnail
			$this->sanitizeAndMove($thumbnailPath, $thumbDestinationPath);
		}
	}

	private function sanitizeAndMove(string $fullPath, string $destinationPath): void {
		// dont rename if the file is already non-existant
		if(!file_exists($fullPath)) {
			return;
		}

		// sanitize to prevent path traversal
		$fullPath = realpath($fullPath);

		// move the file itself to the attachment directory
		rename($fullPath, $destinationPath);
	}

	/**
	 * Fetch a single attachment object by file ID.
	 *
	 * @param int $fileId File row ID.
	 * @return attachment|null Attachment object, or null if not found.
	 */
	public function getAttachment(int $fileId): ?attachment {
		// get the file by id
		$fileEntry = $this->fileRepository->getFileById($fileId);

		// return null if the entry wasn't found
		if(!$fileEntry) {
			return null;
		}

		// board uid
		$boardUid = $fileEntry->getBoardUid();

		// get board of the file entry / post
		$board = searchBoardArrayForBoard($boardUid);

		// construct attachment object
		$attachment = new attachment($fileEntry, $board);

		// return the attachment object
		return $attachment;
	}

	/**
	 * Fetch all attachment objects for the given post UID.
	 *
	 * @param int $postUid Post UID.
	 * @return attachment[]|null Array of attachment objects, or null if none found.
	 */
	public function getAttachmentsForPost(int $postUid): ?array {
		// get the attachments for the post
		$fileEntries = $this->fileRepository->getFilesForPost($postUid);

		// return null if the entry wasn't found
		if(!$fileEntries) {
			return null;
		}

		// construct the attachments
		$attachments = $this->buildAttachments($fileEntries);

		// return the attachments
		return $attachments;
	}

	/**
	 * Fetch all attachment objects for posts in the given thread.
	 *
	 * @param string $threadUid              Thread UID.
	 * @param bool   $excludeAlreadyDeleted  If true, skip files with an open deletion record.
	 * @return attachment[]|null Array of attachment objects, or null if none found.
	 */
	public function getAttachmentsForThread(string $threadUid, bool $excludeAlreadyDeleted = false): ?array {
		// get the fileEntries from posts in the thread
		$threadFileEntries = $this->fileRepository->getFilesForThread($threadUid, $excludeAlreadyDeleted);

		// return null if the entries weren't found
		if(!$threadFileEntries) {
			return null;
		}

		// construct the thread attachments
		$threadAttachments = $this->buildAttachments($threadFileEntries);

		// return attachments
		return $threadAttachments;
	}

	/**
	 * Insert a new file attachment row.
	 *
	 * @param int         $postUid          Post UID.
	 * @param string|null $fileName         Original filename.
	 * @param string|null $storedFileName   Server-stored filename.
	 * @param string|null $fileExtension    File extension.
	 * @param string|null $fileMd5          MD5 hash.
	 * @param int|null    $fileWidth        Image width.
	 * @param int|null    $fileHeight       Image height.
	 * @param int|null    $thumbFileWidth   Thumbnail width.
	 * @param int|null    $thumbFileHeight  Thumbnail height.
	 * @param int|null    $fileSize         Size in bytes.
	 * @param string|null $mimeType         MIME type.
	 * @param bool        $isHidden         Whether the file starts in purgatory.
	 * @param bool        $isDeleted        Whether the file starts marked deleted.
	 * @return void
	 */
	public function addFile(
		int $postUid,
		?string $fileName,
		?string $storedFileName,
		?string $fileExtension,
		?string $fileMd5,
		?int $fileWidth,
		?int $fileHeight,
		?int $thumbFileWidth,
		?int $thumbFileHeight,
		?int $fileSize,
		?string $mimeType,
		bool $isHidden,
		bool $isDeleted = false,
	): void {
		// add the row to database
		$this->fileRepository->insertFileRow($postUid, 
			$fileName, 
			$storedFileName, 
			$fileExtension, 
			$fileMd5, 
			$fileWidth, 
			$fileHeight,
			$thumbFileWidth,
			$thumbFileHeight, 
			$fileSize, 
			$mimeType, 
			$isHidden,
			$isDeleted,
		);
	}

	/**
	 * Fetch all attachment objects for an array of post UIDs.
	 *
	 * @param int[] $postUids Array of post UIDs.
	 * @return attachment[]|null Array of attachment objects, or null if none found.
	 */
	public function getAttachmentsFromPostUids(array $postUids): ?array {
		// fetch fileEntries from a list of post uids
		$fileEntries = $this->fileRepository->getAttachmentsFromPostUids($postUids);

		// if its false (no results)
		// then return null
		if(!$fileEntries) {
			return null;
		}

		// construct attachments
		$attachments = $this->buildAttachments($fileEntries);

		// otherwise just return the attachments results
		return $attachments;
	}

	private function buildAttachments(array $fileEntries): array {
		// init attachment array
		$attachments = [];
		
		// loop through and construct attachment objects
		foreach($fileEntries as $entry) {
			// board uid of the entry
			$boardUid = $entry->getBoardUid();

			// board of the entry
			$board = searchBoardArrayForBoard($boardUid);

			// add the attachment to the array
			$attachments[] = new attachment($entry, $board);
		}

		// return attachments
		return $attachments;
	}

	/**
	 * Enable the animated flag on the given file.
	 *
	 * @param int $fileId File row ID.
	 * @return void
	 */
	public function animateFile(int $fileId): void {
		// run repo method
		$this->fileRepository->toggleAnimatedFileById($fileId, true);
	}

	/**
	 * Disable the animated flag on the given file.
	 *
	 * @param int $fileId File row ID.
	 * @return void
	 */
	public function disableAnimatedFile(int $fileId): void {
		// run repo method
		$this->fileRepository->toggleAnimatedFileById($fileId, false);
	}

	/**
	 * Mark the given file as spoilered.
	 *
	 * @param int $fileId File row ID.
	 * @return void
	 */
	public function spoilerFile(int $fileId): void {
		$this->fileRepository->toggleSpoiledFileById($fileId, true);
	}

	/**
	 * Remove the spoiler flag from the given file.
	 *
	 * @param int $fileId File row ID.
	 * @return void
	 */
	public function unspoilerFile(int $fileId): void {
		$this->fileRepository->toggleSpoiledFileById($fileId, false);
	}

	/**
	 * Mark the given file IDs as deleted (is_deleted = true).
	 *
	 * @param int[] $fileIDs Array of file row IDs.
	 * @return void
	 */
	public function markAttachmentsAsDeleted(array $fileIDs): void {
		// run internal repository method
		$this->fileRepository->toggleIsDeleted($fileIDs, true);
	}

	/**
	 * Mark the given file IDs as not deleted (is_deleted = false).
	 *
	 * @param int[] $fileIDs Array of file row IDs.
	 * @return void
	 */
	public function markAttachmentsAsRestored(array $fileIDs): void {
		// run internal repository method
		$this->fileRepository->toggleIsDeleted($fileIDs, false);
	}

	/**
	 * Check whether the given MD5 hash already exists in the files table.
	 *
	 * @param string   $md5Hash            MD5 hash to check.
	 * @param bool     $countDeleted       Whether to include deleted file rows in the check.
	 * @param int|null $timeRangeInSeconds If set, only consider files added within the last N seconds.
	 * @return bool True if the hash was found (duplicate).
	 */
	public function isDuplicateAttachment(string $md5Hash, bool $countDeleted = true, ?int $timeRangeInSeconds = null): bool {
		// check the files table for if the attachment was previously posted.
		// this also counts files that have been deleted if $countDeleted is true.
		$isDuplicate = $this->fileRepository->checkDuplicateHash($md5Hash, $countDeleted, $timeRangeInSeconds);

		// then return the condition
		return $isDuplicate;
	}

	/**
	 * Return the next AUTO_INCREMENT value for the files table.
	 *
	 * @return int Next available file ID.
	 */
	public function getNextId(): int {
		// use repo to check database for the last inserted id for the files table
		$nextId = $this->fileRepository->getNextId();

		// then return it
		return $nextId;
	}
}