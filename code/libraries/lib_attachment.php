<?php

function resolveThumbName(?array $attachment): false|string {
	// return early if no attachment
	if(!$attachment) {
		return '';
	}

	// get board
	$board = getAttachmentBoard($attachment);

	// Get the configured thumbnail file extension
	$thumbnailExtention = $board->getConfigValue('THUMB_SETTING.Format');

	// attachment file name on disk
	$storedFileName = $attachment['storedFileName'];

	// decide base directory
	$baseDirectory = $attachment['isDeleted'] ? 
		// global attachments directory (where deleted attachments are stored)
		getGlobalAttachmentDirectory()
		// regular board upload directory
		: $board->getBoardUploadedFilesDirectory() . $board->getConfigValue('THUMB_DIR');

	// Construct the full path to the thumbnail file
	$thumbnailBaseName = $baseDirectory . $storedFileName . 's.';
	
	// Return false if the thumbnail file does not exist at the constructed path
	if(file_exists($thumbnailBaseName . $thumbnailExtention)) {
		$thumbnailPath = $thumbnailBaseName . $thumbnailExtention;
	}
	// For backwards compatibility, if the expected extension doesn't exist, check the alternative format
	else if(file_exists($thumbnailBaseName . ($thumbnailExtention === 'png' ? 'jpg' : 'png'))) {
		$thumbnailPath = $thumbnailBaseName . ($thumbnailExtention === 'png' ? 'jpg' : 'png');
	} else {
		return false;
	}
			
	// Extract and return just the filename from the full path
	$thumbnailName = basename($thumbnailPath);

	return $thumbnailName;
}

function getAttachmentUrl(?array $attachment, bool $isThumb = false): false|string {
    // If no attachment is provided, return an empty string
    if (!$attachment) {
        return '';
    }

    // Retrieve the board associated with the attachment
    $board = getAttachmentBoard($attachment);

    // Base URL where the board stores uploaded files
    $baseUploadUrl = $board->getBoardUploadedFilesURL();

    // Determine which directory to use:
    // - THUMB_DIR for thumbnails
    // - IMG_DIR for full-size images
    $directory = $isThumb
        ? $board->getConfigValue('THUMB_DIR')
        : $board->getConfigValue('IMG_DIR');

    // Construct the file name (e.g., "12345.jpg")
    $fileName = $attachment['storedFileName'] . '.' . $attachment['fileExtension'];

    // Build the final URL:
    // - Thumbnails use resolveThumbName
    // - Full-size images use the regular stored file name
    if($isThumb) {
		// get the thumbnail name
		$thumbName = resolveThumbName($attachment);
		
		// however if there is no valid thumb name then just return an empty string
		// because there is no valid thumbnail to point to
		if(empty($thumbName) || !$thumbName) {
			return false;
		}

		// construct thumb url
		$url = $baseUploadUrl . $directory . $thumbName;

		// then return
		return $url;
	}
	// regular image url
	else {
		// construct the attachment url
		$url = $baseUploadUrl . $directory . $fileName;

		// return the url
		return $url;
	}
}

function attachmentFileExists(?array $attachment): bool {
	// if theres no valid attachment then return false
	if(!$attachment) {
		return false;
	}

	// get board
	$board = getAttachmentBoard($attachment);

	// get the upload directory
	$baseDirectory = $board->getBoardUploadedFilesDirectory();

	// attachment directory name
	$attachmentsDirectory = $board->getConfigValue('IMG_DIR');

	// get file name
	$fileName = $attachment['storedFileName'] . '.' . $attachment['fileExtension'];

	// put together full path
	$filePath = $baseDirectory . $attachmentsDirectory . $fileName;

	// if file exists then return true
	if(file_exists($filePath)) {
		return true;
	}
	// otherwise return false - file doesn't exist
	else {
		return false;
	}
}

function getAttachmentBoard(array $attachment): board {
	// board uid of the attachment's post
	$boardUid = $attachment['boardUID'];

	// get the board of the attachment
	$board = searchBoardArrayForBoard($boardUid);

	// return the board
	return $board;
}

function constructAttachment(int $fileId,
	int $postUid,
	string $boardUID,
	?string $fileName,
	?string $storedFileName,
	?string $fileExtension,
	?string $fileMd5,
	int $fileWidth,
	int $fileHeight,
	int $thumbnailFileWidth,
	int	$thumbnailFileHeight,
	string|int $fileSize,
	?string $mimeType,
	bool $isHidden,
	int $isDeleted,
	string $timestampAdded
): attachment {
	// create fileEntry instance
	$fileEntry = new fileEntry;

	// hydrate the object
	$fileEntry->hydrateFileEntry(
		$fileId,
		$postUid,
		$boardUID,
		$fileName,
		$storedFileName,
		$fileExtension,
		$fileMd5,
		$fileWidth,
		$fileHeight,
		$thumbnailFileWidth,
		$thumbnailFileHeight,
		$fileSize,
		$mimeType,
		$isHidden,
		$isDeleted,
		$timestampAdded,
	);

	// get board
	$board = searchBoardArrayForBoard($boardUID);

	// construct attachment
	$attachment = new attachment($fileEntry, $board);

	// return attachment
	return $attachment;
}

/**
 * @param array $files Array of associative arrays containing file entry data.
 * @return attachment[]
 */
function constructAttachmentsFromArray(array $files): array {
    $attachments = [];

    foreach ($files as $file) {

        // build attachment from the existing constructAttachment()
        $attachments[] = constructAttachment(
            $file['fileId'],
            $file['postUid'],
            $file['boardUID'],
            $file['fileName'],
            $file['storedFileName'],
            $file['fileExtension'],
            $file['fileMd5'],
            $file['fileWidth'],
            $file['fileHeight'],
            $file['thumbWidth'],
            $file['thumbHeight'],
            $file['fileSize'],
            $file['mimeType'],
            (bool)$file['isHidden'],
            $file['isDeleted'],
            $file['timestampAdded']
        );
    }

    return $attachments;
}

function getAttachmentsFromPosts(array $posts): array {
	$all = [];

	foreach ($posts as $post) {
		// Ensure $post is array-like
		if (!is_array($post)) {
			continue;
		}

		// Ensure attachments key exists and is an array
		$attachments = $post['attachments'] ?? null;
		if (!is_array($attachments)) {
			continue;
		}

		// Filter out null values and non-attachment data
		foreach ($attachments as $attachment) {
			if ($attachment !== null) {
				$all[] = $attachment;
			}
		}
	}

	return $all;
}