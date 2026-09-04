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

/**
 * Resolve a possibly relative URL against the current request's origin.
 *
 * WEBSITE_URL and STATIC_URL are allowed to be absolute, protocol-relative or root-relative,
 * and every URL built on them inherits that form. A root-relative URL only resolves correctly
 * while the document holding it is itself served over HTTP, so anywhere a real URL is needed —
 * a Location header, an iframe document, markup baked into a static rebuild — it is resolved
 * here first. Already-absolute URLs are returned untouched.
 *
 * @param string $url URL to resolve.
 * @param string $scheme Scheme of the current request, without '://'.
 * @param string $host Host of the current request, including port when present.
 * @param string $documentPath Path of the current document, used only by document-relative URLs.
 * @return string Absolute URL, or $url unchanged when it is already absolute or the host is unknown.
 */
function absoluteUrl(string $url, string $scheme, string $host, string $documentPath = '/'): string {
	// Already carries a scheme.
	if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url) === 1) {
		return $url;
	}

	// Nothing to resolve against.
	if ($host === '') {
		return $url;
	}

	// Protocol-relative: //host/path
	if (str_starts_with($url, '//')) {
		return $scheme . ':' . $url;
	}

	$origin = $scheme . '://' . $host;

	// Root-relative: /path
	if (str_starts_with($url, '/')) {
		return $origin . $url;
	}

	// Document-relative: resolve against the directory the current document sits in, which is
	// what the browser would have done with the same string in an href.
	$slash = strrpos($documentPath, '/');
	$directory = $slash === false ? '/' : substr($documentPath, 0, $slash + 1);
	if ($directory === '' || $directory[0] !== '/') {
		$directory = '/' . $directory;
	}

	return $origin . $directory . $url;
}
