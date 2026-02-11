<?php
/*
* File library for Kokonotsuba!
* Functions for dealing with file I/O
*/

namespace Kokonotsuba\libraries;

function deleteCreatedBoardConfig(string $boardConfigName): void {
	$boardConfigPath = getBoardConfigDir() . $boardConfigName;

	if(file_exists($boardConfigPath)) {
		unlink($boardConfigPath);
	}
}

// Helper to send a file response with common headers
function sendInlineFile(string $path, string $mimeType, string $fileName): void {
	// Clear all output buffers to prevent corruption of binary data
	while (ob_get_level() > 0) {
		ob_end_clean();
	}

	// Set headers
	header("Content-Type: " . $mimeType);
	header("Content-Length: " . filesize($path));
	header("Content-Disposition: inline; filename=\"" . $fileName . "\"");

	// Output the file
	readfile($path);
	exit;
}

function serveImage(string $imagePath): void {
	// Check if the file exists and is a valid image
	if (file_exists($imagePath) && is_file($imagePath)) {
		// Get image information (MIME type, etc.)
		$imageInfo = getimagesize($imagePath);
		if ($imageInfo) {
			// Output the image
			sendInlineFile($imagePath, $imageInfo['mime'], basename($imagePath));
		} else {
			// If the file isn't a valid image
			header("HTTP/1.0 415 Unsupported Media Type");
			exit;
		}
	} else {
		// If the image doesn't exist
		header("HTTP/1.0 404 Not Found");
		exit;
	}
}

function serveVideo(string $videoPath): void {
	// Check if the file exists and is a valid video file
	if (file_exists($videoPath) && is_file($videoPath)) {
		// Get video file MIME type
		$fileExtension = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
		switch ($fileExtension) {
			case 'mp4':
				$mimeType = 'video/mp4';
				break;
			case 'webm':
				$mimeType = 'video/webm';
				break;
			case 'ogg':
				$mimeType = 'video/ogg';
				break;
			default:
				header("HTTP/1.0 415 Unsupported Media Type");
				exit;
		}

		// Output the video
		sendInlineFile($videoPath, $mimeType, basename($videoPath));
	} else {
		// If the video doesn't exist
		header("HTTP/1.0 404 Not Found");
		exit;
	}
}

function serveSWF(string $swfPath): void {
	// Check if the file exists and is a valid SWF
	if (file_exists($swfPath) && is_file($swfPath)) {
		$fileExtension = strtolower(pathinfo($swfPath, PATHINFO_EXTENSION));
		$fileName = basename($swfPath);

		if ($fileExtension !== 'swf') {
			header("HTTP/1.0 415 Unsupported Media Type");
			exit;
		}

		// Output the SWF file
		sendInlineFile($swfPath, 'application/x-shockwave-flash', $fileName);
	} else {
		header("HTTP/1.0 404 Not Found");
		exit;
	}
}

function serveMedia(string $mediaPath) {
	// Ensure the file exists
	if (file_exists($mediaPath) && is_file($mediaPath)) {
		// Get file extension
		$fileExtension = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));

		// Decide if it's an image or video
		if (in_array($fileExtension, ['png', 'jpg', 'jpeg', 'gif'])) {
			// Serve image
			serveImage($mediaPath);
		} elseif (in_array($fileExtension, ['mp4', 'webm', 'ogg'])) {
			// Serve video
			serveVideo($mediaPath);
		} elseif ($fileExtension === 'swf') {
			// Serve flash
			serveSWF($mediaPath);
		} else {
			header("HTTP/1.0 415 Unsupported Media Type");
		}
	} else {
		// If the file doesn't exist
		header("HTTP/1.0 404 Not Found");
	}
}

/**
 * Determine whether the given extension or MIME type represents an archive file.
 *
 * @param string $extension File extension without the dot (e.g., "zip", "rar")
 * @param string $mime      MIME type detected for the file
 *
 * @return bool True if the file is recognized as an archive, otherwise false
 */
function isArchiveFile(string $extension, string $mime) {
	// known archive extensions
	$extList = [
		'zip','rar','7z','tar','gz','tgz','bz2','tbz','xz','txz'
	];

	// known archive MIME types
	$mimeList = [
		'application/zip',
		'application/x-rar-compressed',
		'application/x-7z-compressed',
		'application/x-tar',
		'application/gzip',
		'application/x-bzip2',
		'application/x-xz'
	];

	// normalize values for comparison
	$ext = strtolower($extension);
	$mime = strtolower($mime);

	// check extension match
	if (in_array($ext, $extList, true)) {
		return true;
	}

	// check MIME type match
	if (in_array($mime, $mimeList, true)) {
		return true;
	}

	// no match found
	return false;
}

/**
 * Removes the extension from a string if it has one.
 *
 * A "file extension" is considered anything after the last dot.
 * If there’s no dot, the original string is returned.
 *
 * @param string $input String that may have a "file extension"
 * @return string String without the extension
 */
function stripExtension(string $input): string {
    $pos = strrpos($input, '.');
    if ($pos === false) {
        return $input; // no dot, return as-is
    }
    return substr($input, 0, $pos);
}
