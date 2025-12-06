<?php
/**
 * Pixmicat! Common Library
 *
 * 存放常用函式供主程式引入
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 */

// Windows PHP 5.2.0 does not have this function implemented.
if (!function_exists('inet_pton')) {
	// Source: http://stackoverflow.com/a/14568699
	function inet_pton($ip){
		# ipv4
		if (strpos($ip, '.') !== FALSE) {
			if (strpos($ip, ':') === FALSE) $ip = pack('N',ip2long($ip));
			else {
				$ip = explode(':',$ip);
				$ip = pack('N',ip2long($ip[count($ip)-1]));
			}
		}
		# ipv6
		elseif (strpos($ip, ':') !== FALSE) {
			$ip = explode(':', $ip);
			$parts=8-count($ip);
			$res='';$replaced=0;
			foreach ($ip as $seg) {
				if ($seg!='') $res .= str_pad($seg, 4, '0', STR_PAD_LEFT);
				elseif ($replaced==0) {
					for ($i=0;$i<=$parts;$i++) $res.='0000';
					$replaced=1;
				} elseif ($replaced==1) $res.='0000';
			}
			$ip = pack('H'.strlen($res), $res);
		}
		return $ip;
	}
}

/* 反櫻花字 */
function anti_sakura($str){
	return preg_match('/[\x{E000}-\x{F848}]/u', $str);
}


/* 適用UTF-8環境的擬substr，取出特定數目字元
原出處：Sea Otter @ 2005.05.10
http://www.meyu.net/star/viewthread.php?tid=267&fpage=10 */
function str_cut($str, $maxlen=20){
	$i = $l = 0; $len = strlen($str); $f = true; $return_str = $str;
	while($i < $len){
		$chars = ord($str[$i]);
		if($chars < 0x80){ $l++; $i++; }
		elseif($chars < 0xe0){ $l++; $i += 2; }
		elseif($chars < 0xf0){ $l += 2; $i += 3; }
		elseif($chars < 0xf8){ $l++; $i += 4; }
		  elseif($chars < 0xfc){ $l++; $i += 5; }
		elseif($chars < 0xfe){ $l++; $i += 6; }
		if(($l >= $maxlen) && $f){
			$return_str = substr($str, 0, $i);
			$f = false;
		}
		if(($l > $maxlen) && ($i <= $len)){
			$return_str = $return_str.'…';
			break;
		}
	}
	return $return_str;
}

/* 檢查瀏覽器和伺服器是否支援gzip壓縮方式 */
function CheckSupportGZip(){
	$HTTP_ACCEPT_ENCODING = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
	if(headers_sent() || connection_aborted()) return 0; // 已送出資料，取消
	if(!(function_exists('gzencode') && function_exists('ob_start') && function_exists('ob_get_clean'))) return 0; // 伺服器相關的套件或函式無法使用，取消
	if(strpos($HTTP_ACCEPT_ENCODING, 'gzip')!==false) return 'gzip';
	return 0;
}


function matchCIDR($addr, $cidr) {
	list($ip, $mask) = explode('/', $cidr);
	return (ip2long($addr) >> (32 - $mask) == ip2long($ip.str_repeat('.0', 3 - substr_count($ip, '.'))) >> (32 - $mask));
}

//refer https://stackoverflow.com/questions/7951061/matching-ipv6-address-to-a-cidr-subnet

// converts inet_pton output to string with bits
function inet_to_bits($inet) 
{
	$unpacked = unpack('A16', $inet);
	$unpacked = str_split($unpacked[1]);
	$binaryip = '';
	foreach ($unpacked as $char) {
		$binaryip .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
	}
	return $binaryip;
}

/**
 * (OpenShift) 取得 Client IP Address
 *
 * @return string IP Address
 * @since 8th.Release
 */
function getRemoteAddrOpenShift() {
	if (isset($_ENV['OPENSHIFT_REPO_DIR'])) {
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	return '';
}


function strlenUnicode($str) {
	return mb_strlen($str, 'UTF-8');
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

/* redirect */
function redirect($to, $time=0, $verbose=false) {
	if($to=='back') {
		$to = $_SERVER['HTTP_REFERER']??'';
	}
	
	if ($verbose) {
		$tojs = $to==($_SERVER['HTTP_REFERER']??'') ? 'history.go(-1);' : "location.href=\"$to\"";
		echo '<!DOCTYPE html>
	<html><head>
		<meta charset="utf-8">
		<title>Redirecting...</title>
		<meta http-equiv="refresh" content="0;URL='.$to.'">
		<script>
	setTimeout(function(){'.$tojs.'}, '.$time.'*1000);
		</script>
	<style>
	body {
		font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
	}
	</style>
	</head>
	<body>
		<div style="text-align:center">
			<h1>Redirecting...</h1>
			<p>If your browser doesn\'t redirect for you, please click: <a href="'.$to.'" onclick="event.preventDefault();'.$tojs.'">Go</a></p>
		</div>
	</body>
	</html>';
		exit;
	}
	
	header("Location: " . $to);
	exit;
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

function drawAlert($message) {
	$escapedMessage = addslashes($message);
	$escapedMessage = str_replace(array("\r", "\n"), '', $escapedMessage);	
	echo "	<script type='text/javascript'> 
			alert('" . $escapedMessage . "');
		</script>";
}

function generateUid($length = 8) {
	$randomData = bin2hex(random_bytes(8));

	$uid = uniqid($randomData, true);
	$uid = str_replace('.', '', $uid);
	$uid = substr($uid, 0, $length);

	return $uid;
}

function executeFunction(callable $func, ...$params) {
	return $func(...$params);
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

/**
 * Load uploaded file data for a specific file input and index.
 * Supports multiple files for both 'upfile' and 'quickReplyUpFile'.
 *
 * @param string $inputName  The name of the file input (e.g. 'upfile').
 * @param int $index         Index for multiple file uploads.
 *
 * @return array [$upfile, $upfile_name, $upfile_status]
 */
function loadUploadData(string $inputName, int $index): array {
	// Ensure input exists and is a proper file upload entry
	if (
		isset($_FILES[$inputName]['tmp_name']) &&
		is_array($_FILES[$inputName]['tmp_name']) &&
		!empty($_FILES[$inputName]['tmp_name'][$index])
	) {
		$upfile = sanitizeStr($_FILES[$inputName]['tmp_name'][$index]);
		$upfile_name = $_FILES[$inputName]['name'][$index];
		$upfile_status = $_FILES[$inputName]['error'][$index];

		return [$upfile, $upfile_name, $upfile_status];
	}

	// No file uploaded for this input/index
	return [
		'',
		'',
		UPLOAD_ERR_NO_FILE
	];
}

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


// Helper function for order-insensitive array comparison
function array_equals(array $arr1, array $arr2): bool {
	return count($arr1) === count($arr2) && 
		   empty(array_diff($arr1, $arr2)) && 
		   empty(array_diff($arr2, $arr1));
}

function find_row_by_key_value(array $rows, string $key, $value): ?array {
	// iterate over each row in the provided array
	foreach ($rows as $row) {
		// skip invalid rows that are not arrays
		if (!is_array($row)) {
			continue;
		}

		// check if the key exists in the current row
		if (array_key_exists($key, $row)) {
			// compare the value and return the row if it matches exactly
			if ($row[$key] === $value) {
				return $row;
			}
		}
	}

	// if no matching row is found, return null
	return null;
}
