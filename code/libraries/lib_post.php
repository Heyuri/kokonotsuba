<?php
//post lib

function applyRoll(&$com, &$email){
	$com = "$com\n<p class=\"roll\">[NUMBER: ".rand(1,10000)."]</p>";
	$email = preg_replace('/^roll( *)/i', '', $email);
}

/* Catch impersonators and modify name to display such */ 
function catchFraudsters(&$name) {
	if (preg_match('/[◆◇♢♦⟡★]/u', $name)) $name .= " (fraudster)";
}

function searchBoardArrayForBoard(int $targetBoardUID) {
	// Using the global board array
	$boards = GLOBAL_BOARD_ARRAY;

	foreach ($boards as $board) {
		if ($board->getBoardUID() === intval($targetBoardUID)) {
			return $board;
		}
	}
}

function createAssocArrayFromBoardArray(array $boards): array {
	$assocBoardArray = [];

	// loop over each board and extract its title and uid
	foreach($boards as $board) {
		$boardUid = $board->getBoardUID();
		$boardTitle = $board->getBoardTitle();

		$assocBoardArray[] = [
			'board_uid' => $boardUid,
			'board_title' => $boardTitle
		];
	}

	return $assocBoardArray;
}

function getBoardsByUIDs(array $targetBoardUIDs): array {
    $boards = GLOBAL_BOARD_ARRAY;
    $matchedBoards = [];

    // Normalize target UIDs to integers for reliable comparison
    $uidSet = array_map('intval', $targetBoardUIDs);

    foreach ($boards as $board) {
        if (in_array($board->getBoardUID(), $uidSet, true)) {
            $matchedBoards[] = $board;
        }
    }

    return $matchedBoards;
}

function createBoardStoredFilesFromArray(array $posts, array $boards) {
	$files = [];
	foreach($posts as $post) {
		$board = searchBoardArrayForBoard($post['boardUID']);

		$files[] = new boardStoredFile($post['tim'], $post['ext'], $board);
	}
	return $files;
}

function getUserFileFromRequest(): fileFromUpload {
	// get file attributes
	[$tempFilename, $fileName, $fileStatus] = loadUploadData();

	$md5chksum = md5_file($tempFilename);
	$extension = normalizeExtension($fileName);
	$timeInMillisecond = (int) ($_SERVER['REQUEST_TIME_FLOAT'] * 1000);

	// remove exif if its a jpeg
	if (isJpegExtension($extension) && isExiftoolAvailable()) {
		removeGpsDataFromJpeg($tempFilename);
	}

	// get file name
	$fileName = pathinfo($fileName, PATHINFO_FILENAME);

	// get mime type
	$mimeType = detectMimeType($tempFilename);

	// get file size
	$fileSize = filesize($tempFilename);

	// get dimensions
	[$imgW, $imgH] = getMediaDimensions($tempFilename, $mimeType, $extension);

	$file = new file(
		$extension,
		$timeInMillisecond,
		$tempFilename,
		$fileName,
		$fileSize,
		$imgW,
		$imgH,
		$md5chksum,
		$mimeType,
		$fileStatus
	);

	return new fileFromUpload($file);
}

function normalizeExtension(string $fileName): string {
	$extension = pathinfo($fileName, PATHINFO_EXTENSION);
	return strtolower(trim($extension));
}

function isJpegExtension(string $extension): bool {
	return $extension === 'jpeg' || $extension === 'jpg';
}

function detectMimeType(string $filePath): string {
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($finfo, $filePath);
	finfo_close($finfo);
	return $mimeType;
}

function getMediaDimensions(string $filePath, string $mimeType, string $extension): array {
	$imgW = 0;
	$imgH = 0;

	if (isImage($mimeType)) {
		[$imgW, $imgH] = getimagesize($filePath);
	} elseif (isVideo($mimeType)) {
		[$imgW, $imgH] = getVideoDimensions($filePath); // You must implement this
	} elseif (isSwf($mimeType, $extension)) {
		[$imgW, $imgH] = getswfsize($filePath);
	}

	return [$imgW, $imgH];
}

function isExiftoolAvailable(): bool {
	// Check if 'exiftool' is available in the system path
	exec("which exiftool", $output, $status);
	return $status === 0 && !empty($output);
}

function getMedianTime($videoPath) {
	// Escape the path to prevent command injection
	$cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath);
	
	// Execute the command and capture output
	$duration = (float)exec($cmd);

	// If duration is not valid, return false
	if ($duration <= 0) {
		return false;
	}

	// Calculate median time (in seconds)
	$median = $duration / 2;

	// Format median time as H:i:s
	return gmdate("H:i:s", $median);
}

function createVideoThumbnail(string $videoPath, string $outputImagePath, string $timestamp = '00:00:00'): bool {
    // Check if video file exists and is readable
    if (!file_exists($videoPath) || !is_readable($videoPath)) {
        throw new RuntimeException("Video file not found or not readable: $videoPath");
    }

    // Escape args
    $escapedVideo = escapeshellarg($videoPath);
    $escapedOutput = escapeshellarg($outputImagePath);
    $escapedTimestamp = escapeshellarg($timestamp);

    // Build the command without -f for format unless really necessary
    $cmd = "ffmpeg -y -ss {$escapedTimestamp} -i {$escapedVideo} -vframes 1 {$escapedOutput} 2>&1";

    // Execute and capture output
    exec($cmd, $output, $returnCode);

    // Debugging: log and print more details if an error occurs
    if ($returnCode !== 0) {
        $errorDetails = implode("\n", $output);
        error_log("FFmpeg failed with error code {$returnCode}: {$errorDetails}");
        throw new RuntimeException("FFmpeg error: " . $errorDetails);
    }

    // Return whether the thumbnail image file exists
    return file_exists($outputImagePath);
}

function isVideo(string $mimeType): bool {
	return strpos($mimeType, 'video/') === 0;
}

function isSwf(string $mimeType, string $extension): bool {
	return $mimeType === 'application/x-shockwave-flash' && $extension === 'swf';
}

function isImage(string $mimeType): bool {
	return strpos($mimeType, 'image/') === 0;
}

function getThumbnailFromFile(file $file): thumbnail {
	$width = $file->getImageWidth();
	$height = $file->getImageHeight();

	$fileName = $file->getTemporaryFileName(); 

	$thumbnail =new thumbnail($width, $height, $fileName);
	
	return $thumbnail;
}

function scaleThumbnail(thumbnail $thumbnail, bool $isReply, int $maxReplyWidth, int $maxReplyHeight, int $maxWidth, int $maxHeight) {
	$thumbnailWidth = $thumbnail->getThumbnailWidth();
	$thumbnailHeight = $thumbnail->getThumbnailHeight();
	
	$maxWidth = $isReply ? $maxReplyWidth : $maxWidth;
	$maxHeight = $isReply ? $maxReplyHeight : $maxHeight;

	// scale the thumbnail dimensions
	if ($thumbnailWidth > $maxWidth || $thumbnailHeight > $maxHeight) {
		$scale = min($maxWidth / $thumbnailWidth, $maxHeight / $thumbnailHeight);
		$thumbnailWidth = ceil($thumbnailWidth * $scale);
		$thumbnailHeight = ceil($thumbnailHeight * $scale);
	}

	// now set the new scaled values
	$thumbnail->setThumbnailWidth($thumbnailWidth);
	$thumbnail->setThumbnailHeight($thumbnailHeight);
	
	// return
	return $thumbnail;
}

// method to get the pages to rebuild
function getPageOfThread(string $thread_uid, array $threads, int $threadsPerPage): int {
	$thread_list = array_values($threads); // Make sure it's zero-indexed
	$index = array_search($thread_uid, $thread_list);
		
	if ($index !== false) {
		return (int) floor($index / $threadsPerPage);
	}
	
	return -1; // Thread not found
}

function getPostUidsFromThreadArrays(array $threads): array {
	$postUids = array_unique(array_reduce($threads, function($carry, $thread) {
		if (isset($thread['posts']) && is_array($thread['posts'])) {
			return array_merge($carry, array_column($thread['posts'], 'post_uid'));
		}
		return $carry;
	}, []));

	return $postUids;
}