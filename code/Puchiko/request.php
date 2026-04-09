<?php

namespace Puchiko\request;

/**
 * Sanitize a URL by stripping CR, LF, and null bytes to prevent header injection.
 * 
 * @param string $url The URL to sanitize
 * @return string The sanitized URL
 */
function sanitizeHeaderInjection(string $url): string {
	return str_replace(["\r", "\n", "\0"], '', $url);
}

/**
 * Redirect the client to the given URL or back to the referring page.
 *
 * @param string $to Target URL, or 'back' to return to the HTTP referer.
 */
function redirect(string $to) {
	if ($to === 'back') {
		$referer = $_SERVER['HTTP_REFERER'] ?? '';

		if ($referer !== '') {
			header("Location: " . sanitizeHeaderInjection($referer));
			exit;
		}

		// No referer — fall back to JS history.back()
		echo '<!DOCTYPE html><html><head><script>history.back()</script></head><body></body></html>';
		exit;
	}

	header("Location: " . sanitizeHeaderInjection($to));
	exit;
}