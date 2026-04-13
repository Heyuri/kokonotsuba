<?php

namespace Kokonotsuba\libraries;

use function Puchiko\strings\sanitizeStr;

function renderBasicBootstrapErrorPage(string $message): never {
	http_response_code(500);
	echo '<!DOCTYPE html>';
	echo '<html><head><meta charset="UTF-8"><title>Error</title></head><body>';
	echo '<h1>Error</h1>';
	echo '<p>' . sanitizeStr($message) . '</p>';
	echo '</body></html>';
	exit;
}

/**
 * Write a line to the error log safely.
 * This function must NEVER throw.
 */
function logError(string $message): void {
	$config = getGlobalConfig();
	$logFile = $config['ERROR_HANDLER_FILE'] ?? null;

	if (!$logFile) {
		return;
	}

	$line = sprintf(
		"[%s] %s\n",
		date('Y-m-d H:i:s'),
		$message
	);

	// Suppress errors — logging must never crash the app
	@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool {
	// Respect @-suppressed errors
	if (!(error_reporting() & $errno)) {
		return true;
	}

	logError(sprintf(
		'PHP Error #%d: %s in %s on line %d',
		$errno,
		$errstr,
		$errfile,
		$errline
	));

	return true;
}

set_error_handler('\Kokonotsuba\libraries\errorHandler');

function fatalErrorHandler(): void {
	$e = error_get_last();

	if ($e === null) {
		return;
	}

	logError(sprintf(
		'Fatal Error #%d: %s in %s on line %d',
		$e['type'],
		$e['message'],
		$e['file'],
		$e['line']
	));
}

register_shutdown_function('\Kokonotsuba\libraries\fatalErrorHandler');
