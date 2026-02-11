<?php

namespace Puchiko\strings;

use function Puchiko\array\array_equals;

/**
 * Format a file size in bytes into a human-readable string (B, KB, MB).
 *
 * @param int $bytes The file size in bytes.
 * @return string Formatted file size string.
 */
function formatFileSize(int $bytes): string {
    // If the size is 1 MB (1024 * 1024 bytes) or more
    if ($bytes >= 1024 * 1024) {
        // Divide by 1 MB and round to 2 decimal places
        return round($bytes / (1024 * 1024), 2) . ' MB';
    } 
    // If the size is 1 KB (1024 bytes) or more, but less than 1 MB
    elseif ($bytes >= 1024) {
        // Divide by 1 KB and cast to integer
        return (int)($bytes / 1024) . ' KB';
    } 
    // If the size is less than 1 KB
    else {
        // Return the size in bytes
        return $bytes . ' B';
    }
}

// detect if a string contains html tags
function containsHtmlTags($string) {
	return $string !== strip_tags($string);
}

/**
 * Builds a URL with query parameters that differ from the defaults.
 *
 * @param string $baseUrl     The base URL without query parameters.
 * @param array $defaults     Default values for each query parameter.
 * @param array $userParams   User-specified parameters to compare with defaults.
 * @param bool $isAppending  	 Whether the base URL already contains a '?' and to use a '?' when appending vs a '&'. Routes/modes in Kokonotsuba aready have ? set so this will be set true in most cases.
 *
 * @return string             The resulting URL with only non-default parameters.
 */
function buildSmartQuery(string $baseUrl, array $defaults, array $userParams, bool $isAppending = true): string {
	$query = [];
	
	foreach ($userParams as $key => $value) {
		// Skip empty values
		if (empty($value)) {
			continue;
		}
		
		// Handle array parameters specially
		if (is_array($value)) {
			// Only include if different from defaults (order-insensitive)
			if (!isset($defaults[$key]) || !array_equals($value, $defaults[$key] ?? [])) {
				$query[$key] = implode(' ', $value);
			}
		} else {
			// Only include if different from defaults
			if (!isset($defaults[$key]) || $value !== $defaults[$key]) {
				$query[$key] = $value;
			}
		}
	}
	
	if($isAppending) {
		$urlKey = '&';
	} else {
		$urlKey = '?';
	}
	
	// Build URL using RFC1738 encoding
	$url = $baseUrl . (empty($query) ? '' : $urlKey . http_build_query($query, '', '&', PHP_QUERY_RFC1738));
	
	return $url;
}

// Currently a simple minify
function html_minify($buffer){
	$search = array(
		 
		// Remove whitespaces after tags
		'/\>[^\S ]+/s',
		 
		// Remove whitespaces before tags
		'/[^\S ]+\</s',
		 
		// Remove multiple whitespace sequences
		'/(\s)+/s',
	);
	$replace = array('>', '<', '\\1');
	$buffer = preg_replace($search, $replace, $buffer);
	return $buffer;
}

/**
 * zlib versions after 1.2.9 b0rks php_handle_swc function in php: https://bugs.php.net/bug.php?id=74910
 * so getimagesize() doesn't work with on compressed swfs
 * this is a replacement for getimagesize() to use on .swf files
 */
function getswfsize($file) {
	$swf = file_get_contents($file);
	$swf = unpack(
		'a3signature/'.
		'Cversion/'.
		'Vlength/'.
		'a*payload', $swf);
	extract($swf);

	if ($signature == 'CWS') {
		$type = IMAGETYPE_SWC;
		$payload = gzuncompress($payload);
	} else if ($signature == 'FWS') {
		$type = IMAGETYPE_SWF;
	} else {
		return false;
	}
	 
	$payload = substr($payload, 0, 17);
	$payload = array_values(unpack('C*', $payload));

	$nbits = _getbits($payload, 0, 5);
	$w = (_getbits($payload, 5 + $nbits * 1, $nbits) -
		  _getbits($payload, 5 + $nbits * 0, $nbits)) / 20;
	$h = (_getbits($payload, 5 + $nbits * 3, $nbits) -
		  _getbits($payload, 5 + $nbits * 2, $nbits)) / 20;
	return [$w, $h, $type, 'width="'.$w.'" height="'.$h.'"',
		'mime' => 'application/x-shockwave-flash'];
}

function _getbits($buffer, $pos, $count){
	$result = 0;
 
	for ($loop = $pos; $loop < $pos + $count; $loop++) {
		$result = $result +
			(((($buffer[$loop >> 3]) >> (7 - ($loop % 8))) & 0x01) << ($count - ($loop - $pos) - 1));
	}
	return $result;
}

function generateUid($length = 8) {
	$randomData = bin2hex(random_bytes(8));

	$uid = uniqid($randomData, true);
	$uid = str_replace('.', '', $uid);
	$uid = substr($uid, 0, $length);

	return $uid;
}

function sanitizeStr(string $str, bool $isAdmin = false, bool $injectHtml = false): string {
	// Trim whitespace from both ends of the string
	$str = trim($str);

	// Remove potentially problematic characters (e.g., control characters not allowed in XML 1.1)
	// Reference: http://www.w3.org/TR/2006/REC-xml11-20060816/#charsets
	$str = preg_replace(
		'/([\x01-\x08\x0B\x0C\x0E-\x1F\x7F-\x84\x86-\x9F\x{FDD0}-\x{FDDF}])/u',
		'',
		htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
	);

	// Convert single quote to HTML entity (htmlspecialchars doesn't convert it by default)
	$str = str_replace("'", "&#039;", $str);

	// Allow HTML tags when $injectHtml is true and the user is an admin ($isAdmin)
	if ($isAdmin && $injectHtml) {
		// Convert &lt;tag&gt; back to <tag>
		$str = preg_replace('/&lt;(.*?)&gt;/', '<$1>', $str);
	}

	return $str;
}

function strlenUnicode($str) {
	return mb_strlen($str, 'UTF-8');
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
function generateFilteredUrl(string $baseUrl, array $filters = []) {
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

/**
 * Safely truncates a string to a maximum length without breaking multibyte characters (e.g., emojis, non-Latin chars).
 * Optionally appends an ellipsis (…) if truncation occurs.
 *
 * @param string $text The input text to truncate.
 * @param int $maxLength The maximum number of characters to keep.
 * @param string $encoding The character encoding (default is UTF-8).
 * @param bool $addEllipsis Whether to append "…" if the text was truncated.
 * @return string The safely truncated string.
 */
function truncateText(
    string $text,
    int $maxLength,
    string $encoding = 'UTF-8',
    bool $addEllipsis = true
): string {
    // If the text length is within the limit, return as is
    if (mb_strlen($text, $encoding) <= $maxLength) {
        return $text;
    }

    // Truncate to the desired length (minus 1 if we're adding an ellipsis)
    $truncatedLength = $addEllipsis ? $maxLength - 1 : $maxLength;

    // Use mb_substr to avoid breaking multibyte characters
    $truncated = mb_substr($text, 0, $truncatedLength, $encoding);

    // Append ellipsis if desired
    return $addEllipsis ? $truncated . '(' . html_entity_decode('&hellip;', ENT_QUOTES, $encoding) . ')' : $truncated;
}