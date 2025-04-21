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
 * Create a directory, reporting errors through $globalHTML.
 *
 * @param string $directoryName
 * @param globalHTML $globalHTML
 * @return string
 */
function createDirectoryWithErrorHandle(string $directoryName, globalHTML $globalHTML): string {
	if (!file_exists($directoryName)) {
		if (!mkdir($directoryName, 0755, true)) {
			$globalHTML->error("Failed to create directory: $directoryName");
		}
	}
	return $directoryName;
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
		die("Could not create $directoryName");
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
	$destPath = $destDir . DIRECTORY_SEPARATOR . basename($sourceFile);
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
