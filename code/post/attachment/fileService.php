<?php

class fileService {
	public function __construct(
		private readonly fileRepository $fileRepository
	) {
		// get attachments dir
		$attachmentsDir = getGlobalAttachmentDirectory();

		// create the attachments directory if it doesn't exist
		createDirectory($attachmentsDir);
	}

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
			$destinationImgDirectory = $attach->getUploadDirectory();
		
			// move the file
			$this->sanitizeAndMove($currentPath, $destinationImgDirectory);
	
			// full path of the hidden thumbnail
			$currentThumbPath = $attach->getHiddenPath(true);

			// full path of the new thumb directory
			$destinationThumbDirectory = $attach->getUploadDirectory(true);

			// move the thumbnail
			$this->sanitizeAndMove($currentThumbPath, $destinationThumbDirectory);
		}
	}

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

	public function moveFilesToPurgatory(array $attachments): void {
		// get file IDs
		$fileIDs = $this->getFileIDsFromAttachments($attachments);

		// mark the files as hidden so it knows its in purgatory
		$this->fileRepository->setHiddenStatuses($fileIDs, true);

		// mark the files as deleted
		$this->markAttachmentsAsDeleted($fileIDs); 

		// get the global attachment directory
		$globalAttachmentDirectory = getGlobalAttachmentDirectory();
		
		// move the files
		$this->moveFilesToDirectory($attachments, $globalAttachmentDirectory);
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

	private function moveFilesToDirectory(array $attachments, string $destination): void {	
		// loop through attachments and move the file
		foreach($attachments as $attach) {
			// full path of the file
			$fullPath = $attach->getUploadPath();

			// sanitize and move attachment
			$this->sanitizeAndMove($fullPath, $destination);

			// get the thumbnail path
			$thumbnailPath = $attach->getUploadPath(true);

			// sanitize and move attachment thumbnail
			$this->sanitizeAndMove($thumbnailPath, $destination);
		}
	}

	private function sanitizeAndMove(string $fullPath, string $destination): void {
		// sanitize to prevent path traversal
		$fullPath = realpath($fullPath);
		$destination = realpath($destination);

		// move the file itself to the attachment directory
		moveFileOnly($fullPath, $destination);
	}

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

	public function getAttachmentsForPost(int $postUid): ?array {
		// get the file and thumbnail pair
		$fileThumbPair = $this->fileRepository->getFilesForPost($postUid);

		// return null if the entry wasn't found
		if(!$fileThumbPair) {
			return null;
		}

		// construct the attachment pair
		$attachments = $this->buildAttachments($fileThumbPair);

		// return the attachment pair
		return $attachments;
	}

	public function getAttachmentsForThread(string $threadUid): ?array {
		// get the fileEntries from posts in the thread
		$threadFileEntries = $this->fileRepository->getFilesForThread($threadUid);

		// return null if the entries weren't found
		if(!$threadFileEntries) {
			return null;
		}

		// construct the thread attachments
		$threadAttachments = $this->buildAttachments($threadFileEntries);

		// return attachments
		return $threadAttachments;
	}

	public function addFile(
		int $postUid,
		string $fileName,
		string $storedFileName,
		string $fileExtension,
		string $fileMd5,
		?int $fileWidth,
		?int $fileHeight,
		?int $thumbFileWidth,
		?int $thumbFileHeight,
		int $fileSize,
		string $mimeType,
		bool $isHidden
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
			$isHidden);
	}

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

	public function animateFile(int $fileId): void {
		// run repo method
		$this->fileRepository->toggleAnimatedFileById($fileId, true);
	}

	public function disableAnimatedFile(int $fileId): void {
		// run repo method
		$this->fileRepository->toggleAnimatedFileById($fileId, false);
	}

	public function markAttachmentsAsDeleted(array $fileIDs): void {
		// run internal repository method
		$this->fileRepository->toggleIsDeleted($fileIDs, true);
	}

	public function markAttachmentsAsRestored(array $fileIDs): void {
		// run internal repository method
		$this->fileRepository->toggleIsDeleted($fileIDs, false);
	}

	public function isDuplicateAttachment(string $md5Hash, bool $countDeleted = true, ?int $timeRangeInSeconds = null): bool {
		// check the files table for if the attachment was previously posted.
		// this also counts files that have been deleted if $countDeleted is true.
		$isDuplicate = $this->fileRepository->checkDuplicateHash($md5Hash, $countDeleted, $timeRangeInSeconds);

		// then return the condition
		return $isDuplicate;
	}
}