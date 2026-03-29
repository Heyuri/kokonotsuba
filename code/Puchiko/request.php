<?php

namespace Puchiko\request;

use Kokonotsuba\request\request;

/**
 * Check whether the current HTTP request is a POST request.
 *
 * @return bool True if the request method is POST, false otherwise
 */
function isPostRequest(request $request): bool {
	return $request->isPost();
}

/**
 * Check whether the current HTTP request is a GET request.
 *
 * @return bool True if the request method is GET, false otherwise
 */
function isGetRequest(request $request): bool {
	return $request->isGet();
}

/* redirect */
function redirect(string $to) {
	header("Location: " . $to);
	exit;
}