<?php

namespace Puchiko;

use RuntimeException;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

function getVideoDimensions(string $filePath): array {
	if (!file_exists($filePath) || !is_readable($filePath)) {
		throw new RuntimeException("Video file not accessible: $filePath");
	}

	$escapedFile = escapeshellarg($filePath);

	// ffmpeg outputs stream info to stderr, so we capture stderr using 2>&1
	$cmd = "ffmpeg -i $escapedFile 2>&1";

	exec($cmd, $output, $returnCode);

	if ($returnCode !== 0 && $returnCode !== 1) {
		// ffmpeg often returns 1 even on success when probing
		throw new RuntimeException("Failed to run ffmpeg to get dimensions.");
	}

	$dimensions = [0, 0];

	foreach ($output as $line) {
		if (preg_match('/Stream.*Video.* (\d{2,5})x(\d{2,5})/', $line, $matches)) {
			$dimensions = [(int)$matches[1], (int)$matches[2]];
			break;
		}
	}

	if ($dimensions[0] === 0 || $dimensions[1] === 0) {
		throw new RuntimeException("Could not parse video dimensions.");
	}

	return $dimensions;
}
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
 * Move a file to an exact destination path.
 * Can optionally overwrite existing files.
 *
 * @param string $sourceFile
 * @param string $destPath
 * @param bool $overwrite
 * @return bool
 */
function moveFileOnly(string $sourceFile, string $destPath, bool $overwrite = false): bool {
	if (!is_file($sourceFile)) {
		return false;
	}

	$destDir = dirname($destPath);

	if (!is_dir($destDir) && !mkdir($destDir, 0777, true)) {
		return false;
	}

	if (file_exists($destPath)) {
		if (!$overwrite) {
			return false;
		}
		if (!unlink($destPath)) {
			return false;
		}
	}

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