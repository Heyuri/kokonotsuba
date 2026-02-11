<?php

namespace Puchiko\request;

/**
 * Check whether the current HTTP request is a POST request.
 *
 * @return bool True if the request method is POST, false otherwise
 */
function isPostRequest(): bool {
	// REQUEST_METHOD is set by the web server (e.g. GET, POST, PUT)
	return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/**
 * Check whether the current HTTP request is a GET request.
 *
 * @return bool True if the request method is GET, false otherwise
 */
function isGetRequest(): bool {
	// Use null coalescing to avoid notices in non-HTTP contexts (CLI, tests)
	return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
}

/* redirect */
function redirect(string $to) {
	if($to=='back') {
		$to = $_SERVER['HTTP_REFERER']??'';
	}
	
	header("Location: " . $to);
	exit;
}