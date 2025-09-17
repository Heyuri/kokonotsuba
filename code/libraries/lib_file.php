<?php
/*
* File library for Kokonotsuba!
* Functions for dealing with file I/O
*/


/**
 * Recursively delete a directory and its contents.
 *
 * @param string $dirPath
 * @return bool
 */
function safeRmdir(string $dirPath): bool {
	if (!is_dir($dirPath)) {
		return false;
	}

	$items = scandir($dirPath);
	if ($items === false) {
		return false;
	}

	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}

		$itemPath = $dirPath . '/' . $item;
		if (is_dir($itemPath)) {
			if (!safeRmdir($itemPath)) {
				return false;
			}
		} else {
			if (!unlink($itemPath)) {
				return false;
			}
		}
	}

	return rmdir($dirPath);
}

/**
 * Get total size (in bytes) of a directory tree.
 *
 * @param string $directory
 * @return int|false
 */
function getDirectorySize($directory): int|false {
	$size = 0;

	if (!is_dir($directory)) {
		return false;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		$size += $file->getSize();
	}

	return $size;
}

/**
 * Simple directory creation (die on failure).
 *
 * @param string $directoryName
 * @return void
 */
function createDirectory(string $directoryName): void {
	if (file_exists($directoryName)) {
		return;
	}
	if (!mkdir($directoryName, 0755, true)) {
		throw new Exception("Could not create $directoryName");
	}
}

/**
 * Write text to a file (overwrites).
 *
 * @param string $filePath
 * @param string $text
 * @return bool
 */
function writeToFile(string $filePath, string $text): bool {
	$fileHandle = fopen($filePath, 'w');
	if ($fileHandle === false) {
		return false;
	}
	$result = fwrite($fileHandle, $text);
	fclose($fileHandle);
	return ($result !== false);
}

/**
 * Ensure directory exists, then create and write to file.
 *
 * @param string $directory
 * @param string $fileName
 * @param string $text
 * @throws Exception
 * @return void
 */
function createFileAndWriteText(string $directory, string $fileName, string $text): void {
	$filePath = $directory . '/' . $fileName;
	if (!file_exists($directory)) {
		mkdir($directory, 0777, true);
	}
	$file = fopen($filePath, 'w');
	if (!$file) {
		throw new Exception("Failed to create or open the file.");
	}
	fwrite($file, $text);
	fclose($file);
}

/**
 * Copy a file to a new name (and optional destination).
 *
 * @param string      $sourceFilePath
 * @param string      $newFileName
 * @param string|null $destinationDir
 * @return string
 */
function copyFileWithNewName(string $sourceFilePath, string $newFileName, string|null $destinationDir = null): string|null {
	if (!file_exists($sourceFilePath)) {
		return "Error: Source file does not exist.";
	}
	if ($destinationDir === null) {
		$destinationDir = dirname($sourceFilePath);
	}
	$destinationDir = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	$newFilePath = $destinationDir . $newFileName;
	return copy($sourceFilePath, $newFilePath)
		? "File copied successfully to: $newFilePath"
		: "Error: Failed to copy the file.";
}

/**
 * Move a single file into a directory, creating it if needed.
 *
 * @param string $sourceFile
 * @param string $destDir
 * @return bool
 */
function moveFileOnly(string $sourceFile, string $destDir): bool {
	if (!is_file($sourceFile)) {
		return false;
	}
	if (!is_dir($destDir) && !mkdir($destDir, 0777, true)) {
		return false;
	}
	$destPath = $destDir . '/' . basename($sourceFile);
	return rename($sourceFile, $destPath);
}

/**
 * Roll back a stack of created paths (in reverse).
 *
 * @param array $paths
 * @return void
 */
function rollbackCreatedPaths(array $paths): void {
	foreach (array_reverse($paths) as $path) {
		safeRmdir($path);
	}
}

/**
 * Removes GPS metadata from a JPEG image using exiftool.
 *
 * This function targets only the GPS-related EXIF tags and removes them from the file.
 * It uses exiftool with the -gps:all= and -overwrite_original flags to modify the image in-place.
 */
function removeGpsDataFromJpeg($imagePath) {
	// Check if the provided file path exists
	if (!file_exists($imagePath)) {
		throw new Exception("File does not exist: " . $imagePath);
	}

	// Escape the file path to safely use in shell command
	$escapedPath = escapeshellarg($imagePath);

	// Redirect stderr to stdout to capture all output
	$command = "exiftool -gps:all= -overwrite_original $escapedPath 2>&1";

	// Execute the command and capture output and return status
	exec($command, $output, $returnVar);

	// Convert output array to string
	$errorOutput = implode("\n", $output);

	// Treat "0 image files updated" as non-fatal if it's due to no GPS data
	if ($returnVar !== 0) {
		foreach ($output as $line) {
			if (strpos($line, '0 image files updated') !== false && strpos($line, "weren't updated due to errors") === false) {
				// No GPS data to remove, not an error
				return true;
			}
		}
		// If it's a real error, throw
		throw new Exception("Failed to remove GPS data. Error code: $returnVar\nOutput:\n$errorOutput");
	}

	// Return true on success
	return true;
}

// check if file is a valid mysql dump
function isValidMySQLDumpFile(string $filePath): bool {
	// Open the file
	$file = fopen($filePath, 'r');
	if (!$file) {
		throw new Exception("Unable to open the file: $filePath");
	}

	// Check for MySQL-specific comments and statements
	$valid = false;
	$patterns = [
		'/^-- MySQL dump/',
		'/^-- Server version/',
		'/^CREATE DATABASE/',
		'/^USE/',
		'/^CREATE TABLE/',
		'/^INSERT INTO/',
	];

	// Read the first few lines of the file to check for MySQL dump patterns
	$linesChecked = 0;
	while (($line = fgets($file)) !== false && $linesChecked < 20) {
		$line = trim($line); // Trim whitespace from the line
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $line)) {
				$valid = true;
				break 2; // If a match is found, exit early
			}
		}
		$linesChecked++;
	}

	fclose($file);

	// Return if the file seems to be a valid MySQL dump
	return $valid;
}

function deleteCreatedBoardConfig(string $boardConfigName): void {
	$boardConfigPath = getBoardConfigDir() . $boardConfigName;

	if(file_exists($boardConfigPath)) {
		unlink($boardConfigPath);
	}
}

function serveImage(string $imagePath) {
    // Check if the file exists and is a valid image
    if (file_exists($imagePath) && is_file($imagePath)) {
        // Get image information (MIME type, etc.)
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo) {
            // Set the correct content-type header based on image MIME type
            header("Content-Type: " . $imageInfo['mime']);
            
            // Output the image
            readfile($imagePath);
        } else {
            // If the file isn't a valid image
            header("HTTP/1.0 415 Unsupported Media Type");
        }
    } else {
        // If the image doesn't exist
        header("HTTP/1.0 404 Not Found");
    }
}

function serveVideo(string $videoPath) {
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

        // Set the correct content-type header for video
        header("Content-Type: " . $mimeType);

        // Output the video
        readfile($videoPath);
    } else {
        // If the video doesn't exist
        header("HTTP/1.0 404 Not Found");
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
        } else {
            header("HTTP/1.0 415 Unsupported Media Type");
        }
    } else {
        // If the file doesn't exist
        header("HTTP/1.0 404 Not Found");
    }
}