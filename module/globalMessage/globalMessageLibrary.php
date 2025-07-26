<?php

namespace Kokonotsuba\Modules\globalMessage;

use BoardException;

function getGlobalMessage(string $globalMessageFile) {
	// Check if the cached message is available
	static $cachedGlobalMsg = null;
	
	if ($cachedGlobalMsg === null) {
		// If not cached, fetch the message and cache it
		$cachedGlobalMsg = getCurrentGlobalMsg($globalMessageFile);
	}
	
	// Append the cached (or freshly fetched) message to $msg
	return $cachedGlobalMsg ?? '';
}

function getCurrentGlobalMsg(string $globalMessageFile): string {
	if (file_exists($globalMessageFile)) {
	    return file_get_contents($globalMessageFile);
	}
	return '';
}

function writeToGlobalMsg(string $globalMessageFile, string $message): void {
	if (!is_writable($globalMessageFile)) {
		throw new BoardException('Error: Unable to write to the file.');
	}
	file_put_contents($globalMessageFile, $message);
}