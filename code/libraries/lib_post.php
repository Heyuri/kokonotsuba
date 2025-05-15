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

function searchBoardArrayForBoard($boards, $targetBoardUID) {
	foreach ($boards as $board) {
		if ($board->getBoardUID() === intval($targetBoardUID)) {
			return $board;
		}
	}
}

function createBoardStoredFilesFromArray($posts) {
	$boardIO = boardIO::getInstance();

	$boards = $boardIO->getAllRegularBoards();
	$files = [];
	foreach($posts as $post) {
		$board = searchBoardArrayForBoard($boards, $post['boardUID']);

		$files[] = new boardStoredFile($post['tim'], $post['ext'], $board);
	}
	return $files;
}

function getPostUidsFromThread(string $threadUid) {
	$threadSingleton = threadSingleton::getInstance();

	$postsFromThread = $threadSingleton->fetchPostsFromThread($threadUid);

	$postUids = array_column($postsFromThread, 'post_uid');

	return $postUids;
}

function getUserFileFromRequest() {
	// get file attributes
	[$tempFilename, $fileName, $fileStatus] = loadUploadData();

	$md5chksum = md5_file($tempFilename);
	$extension = pathinfo($fileName, PATHINFO_EXTENSION);
	$extension = strtolower(trim($extension)); // Normalize extension
	$timeInMillisecond = intval($_SERVER['REQUEST_TIME_FLOAT'] * 1000);

	// get file size
	$fileSize = filesize($tempFilename);

	$fileName = pathinfo($fileName, PATHINFO_FILENAME);

	// get mime type
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($finfo, $tempFilename);
	finfo_close($finfo);

	// get dimensions
	$imgW = 0;
	$imgH = 0;

	if (strpos($mimeType, 'image/') === 0) {
		[$imgW, $imgH] = getimagesize($tempFilename);
	} elseif (isVideo($mimeType)) {
		[$imgW, $imgH] = getVideoDimensions($tempFilename); // You must implement this
	} elseif ($extension === 'swf') {
		[$imgW, $imgH] = getswfsize($tempFilename);
	}

	$file = new file($extension, $timeInMillisecond, $tempFilename, $fileName, $fileSize, $imgW, $imgH, $md5chksum, $mimeType, $fileStatus);

	$fileFromUpload = new fileFromUpload($file);

	return $fileFromUpload;
}


function createVideoThumbnail(string $videoPath, string $outputImagePath, string $format = 'mp4', string $timestamp = '00:00:01'): bool {
	// Check if video file exists and is readable
	if (!file_exists($videoPath) || !is_readable($videoPath)) {
		throw new RuntimeException("Video file not found or not readable: $videoPath");
	}

	// Escape args
	$escapedVideo = escapeshellarg($videoPath);
	$escapedOutput = escapeshellarg($outputImagePath);
	$escapedTimestamp = escapeshellarg($timestamp);

	// Build format command without quotes around format
	$formatCmd = '';
	if (!empty($format)) {
		$formatCmd = "-f $format ";
	}

	// Use fast seeking by placing -ss before -i
	$cmd = "ffmpeg -y -ss {$escapedTimestamp} {$formatCmd}-i {$escapedVideo} -vframes 1 {$escapedOutput} 2>&1";

	// Execute and capture output
	exec($cmd, $output, $returnCode);

	// Debug
	if ($returnCode !== 0) {
		throw new RuntimeException("FFmpeg error: " . implode("\n", $output));
		return false;
	}

	return file_exists($outputImagePath);
}


function isVideo(string $mimeType): bool {
	return strpos($mimeType, 'video/') === 0;
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