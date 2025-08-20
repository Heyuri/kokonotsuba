<?php
/*
* Helper html functions for Kokonotsuba!
* General-use helper functions to do with html output and string manipulation
*/

/**
 * Adds hidden input fields for each GET parameter at the top of the provided form HTML.
 *
 * @param string $formHtml  The original HTML form markup.
 * @param array $getValues  An associative array of GET parameters to inject as hidden inputs.
 * @return string           The modified form HTML with hidden inputs included.
 */
function addHiddenGetParamsToForm(string $formHtml, array $getValues): string {
	$hiddenInputs = '';

	foreach ($getValues as $name => $value) {
		$nameEscaped = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		$valueEscaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		$hiddenInputs .= "<input type=\"hidden\" name=\"{$nameEscaped}\" value=\"{$valueEscaped}\">\n";
	}

	// Inject the hidden inputs right after the opening <form> tag
	return preg_replace('/<form[^>]*>/i', '$0' . "\n" . $hiddenInputs, $formHtml);
}

/**
 * Extract GET parameters from a given URL.
 *
 * This function takes a URL as input, parses it, and returns 
 * an associative array of GET (query string) parameters.
 *
 * @param string $url The full URL containing GET parameters.
 * @return array An associative array of GET parameters, or an empty array if none are found.
 */
function extractGetParams($url) {
	// Parse the URL and extract its components
	$parsedUrl = parse_url($url);

	// Check if a query string exists in the URL
	if (!isset($parsedUrl['query'])) {
		return []; // Return an empty array if no query string is present
	}

	// Initialize an empty array to hold the GET parameters
	$getParams = [];

	// Parse the query string into an associative array
	parse_str($parsedUrl['query'], $getParams);

	// Return the associative array of GET parameters
	return $getParams;
}

/**
 * Generate a URL with the given filters (without pagination).
 *
 * This function generates a URL with the given filters (e.g., keyword, field, method) and no pagination.
 *
 * @param string $baseUrl The base URL for the page (e.g., $this->mypage).
 * @param array $filters An associative array of filter names and their values (e.g., ['keyword' => 'test', 'field' => 'com']).
 * @return string The full URL with filters (pagination is not handled by this function).
 */
function generateFilteredUrl($baseUrl, array $filters = []) {
    // Start with the base URL
    $url = $baseUrl . '&';

    // Append filters to the URL
    foreach ($filters as $key => $value) {
        // URL encode each filter value to ensure it is safe for URLs
        $url .= urlencode($key) . '=' . urlencode($value) . '&';
    }

    // Remove the last '&' character
    return rtrim($url, '&');
}

function autoLink(string $text, string $refUrl = ''): string {
	$pattern = '~https?://[^\s<]+~i';

	return preg_replace_callback($pattern, function ($m) use ($refUrl) {
		// 1) Normalize any pre-escaped entities in the matched URL
		$url = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// 2) (Optional) Allow only http/https
		if (!preg_match('~^https?://~i', $url)) {
			return $m[0];
		}

		// 3) Escape once for HTML attribute; also escape label to be safe in HTML text
		$href = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$label = htmlspecialchars($url, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return '<a href="'. $refUrl . $href . '" rel="nofollow noreferrer" target="_blank">' . $label . '</a>';
	}, $text);
}

/* Add quote class to quoted text */
function quote_unkfunc(string $comment): string {
	$comment = preg_replace('/(^|<br\s*\/?>)((?:&gt;|＞).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc">$2</span>', $comment);
	$comment = preg_replace('/(^|<br\s*\/?>)((?:&lt;).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc2">$2</span>', $comment);
	return $comment;
}
