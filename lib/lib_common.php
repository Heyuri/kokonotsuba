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

/* 文字修整 */
function CleanStr($str, $IsAdmin=false){
	$str = trim($str); // 去除前後多餘空白
	// if(get_magic_quotes_gpc()) $str = stripslashes($str); // "\"斜線符號去除
	// XML 1.1 Second Edition: 部分避免用字 (http://www.w3.org/TR/2006/REC-xml11-20060816/#charsets)
	$str = preg_replace('/([\x1-\x8\xB-\xC\xE-\x1F\x7F-\x84\x86-\x9F\x{FDD0}-\x{FDDF}])/u', '', htmlspecialchars($str));
	$str = str_replace("'", "&#039;", $str); // htmlspecialchars above doesn't work on apostrophe

	if($IsAdmin && CAP_ISHTML){ // 管理員開啟HTML
		$str = preg_replace('/&lt;(.*?)&gt;/', '<$1>', $str); // 如果有&lt;...&gt;則轉回<...>成為正常標籤
	}
	return $str;
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

/* 封鎖 IP / Hostname / DNSBL 綜合性檢查 */
function BanIPHostDNSBLCheck($config, $IP, $HOST, &$baninfo){
	if(!$config['BAN_CHECK']) return false; // Disabled
				
	// IP/Hostname Check
	$HOST = strtolower($HOST);
	$checkTwice = ($IP != $HOST); // 是否需檢查第二次
	$IsBanned = false;
	foreach($config['BANPATTERN'] as $pattern){
		$slash = substr_count($pattern, '/');
		if($slash==2){ // RegExp
			$pattern .= 'i';
		}elseif($slash==1){ // CIDR Notation
			if(matchCIDR($IP, $pattern)){ $IsBanned = true; break; }
			continue;
		}elseif(strpos($pattern, '*')!==false || strpos($pattern, '?')!==false){ // Wildcard
			$pattern = '/^'.str_replace(array('.', '*', '?'), array('\.', '.*', '.?'), $pattern).'$/i';
		}else{ // Full-text
			if($IP==$pattern || ($checkTwice && $HOST==strtolower($pattern))){ $IsBanned = true; break; }
			continue;
		}
		if(preg_match($pattern, $HOST) || ($checkTwice && preg_match($pattern, $IP))){ $IsBanned = true; break; }
	}
	if($IsBanned){ $baninfo = _T('ip_banned'); return true; }

	// DNS-based Blackhole List(DNSBL) 黑名單
	if(!isset($config['DNSBLservers'][0])||!$config['DNSBLservers'][0]) return false; // Skip check
	if(array_search($IP, $config['DNSBLWHlist'])!==false) return false; // IP位置在白名單內
	$rev = implode('.', array_reverse(explode('.', $IP)));
	$lastPoint = count($config['DNSBLservers']) - 1; if($config['DNSBLservers'][0] < $lastPoint) $lastPoint = $config['DNSBLservers'][0];
	$isListed = false;
	for($i = 1; $i <= $lastPoint; $i++){
		$query = $rev.'.'.$config['DNSBLservers'][$i].'.'; // FQDN
		$result = gethostbyname($query);
		if($result && ($result != $query)){ $isListed = $config['DNSBLservers'][$i]; break; }
	}
	if($isListed){ $baninfo = _T('ip_dnsbl_banned',$isListed); return true; }
	return false;
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

function matchCIDRv6($addr, $cidr) {
    list($net, $mask) = explode('/', $cidr);
    $ip = inet_pton($addr);
    $binaryip=inet_to_bits($ip);
    $net=inet_pton($net);
    $binarynet=inet_to_bits($net);
    $ip_net_bits=substr($binaryip,0,$mask);
    $net_bits   =substr($binarynet,0,$mask);

    if($ip_net_bits!==$net_bits) {
        return false;
    }
    return true;
}

//
function getREMOTE_ADDR(){
	static $ip_cache;
	if ($ip_cache) return $ip_cache;
	
    $ipCloudFlare = getRemoteAddrCloudFlare();
    if (!empty($ipCloudFlare)) {
        return $ip_cache = $ipCloudFlare;
    }

    $ipOpenShift = getRemoteAddrOpenShift();
    if (!empty($ipOpenShift)) {
        return $ip_cache = $ipOpenShift;
    }

    $ipProxy = getRemoteAddrThroughProxy();
    if (!empty($ipProxy)) {
        return $ip_cache = $ipProxy;
    }

    return $ip_cache = $_SERVER['REMOTE_ADDR'];
}

/**
 * 取得 (Transparent) Proxy 提供之 IP 參數
 */
function getRemoteAddrThroughProxy() {
    global $PROXYHEADERlist;

    if (!defined('TRUST_HTTP_X_FORWARDED_FOR') || !TRUST_HTTP_X_FORWARDED_FOR) {
        return '';
    }
    $ip='';
    $proxy = $PROXYHEADERlist;
    
	foreach ($proxy as $key) {
		if (array_key_exists($key, $_SERVER)) {
			foreach (explode(',', $_SERVER[$key]) as $ip) {
				$ip = trim($ip);
				// 如果結果為 Private IP 或 Reserved IP，捨棄改用 REMOTE_ADDR
				if (filter_var($ip, FILTER_VALIDATE_IP,FILTER_FLAG_IPV4 |FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !==false) {
					return $ip;
				}
			}
		}
	}

    return '';
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

/**
 * 若來源是 CloudFlare IP, 從 CF-Connecting-IP 取得 client IP
 * CloudFlare IP 來源: https://www.cloudflare.com/ips
*/

function getRemoteAddrCloudFlare() {
    $addr = $_SERVER['REMOTE_ADDR'];
    $cloudflare_v4 = array('199.27.128.0/21', '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/12');
    $cloudflare_v6 = array('2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32');

    if(filter_var($addr, FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) { //v4 address
        foreach ($cloudflare_v4 as &$cidr) {
            if(matchCIDR($addr, $cidr)) {
                return $_SERVER['HTTP_CF_CONNECTING_IP'];
            }
        }
    } else { // v6 address
        foreach ($cloudflare_v6 as &$cidr) {
            if(matchCIDRv6($addr, $cidr)) {
                return $_SERVER['HTTP_CF_CONNECTING_IP'];
            }
        }
    }
    return '';
}

function strlenUnicode($str) {
    return mb_strlen($str, 'UTF-8');
}

function num2Role($roleNumber, $config) {
	$num = intval($roleNumber);
	$from = '';
	
	switch ($num) {
			case $config['roles']['LEV_NONE']: $from = 'USER'; break;
			case $config['roles']['LEV_USER']: $from = 'REGISTERED_USER'; break;
			case $config['roles']['LEV_JANITOR']: $from = 'JANITOR'; break;
			case $config['roles']['LEV_MODERATOR']: $from = 'MODERATOR'; break;
			case $config['roles']['LEV_ADMIN']: $from = 'ADMIN'; break;
	}
	return $from;
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
	</head><body>
	<center>
		<h1>Redirecting...
		<p>If your browser doesn\'t redirect for you, please click: <a href="'.$to.'" onclick="event.preventDefault();'.$tojs.'">Go</a></p></h1>
	<br><br>
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

function safeRmdir($dirPath) {
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

		$itemPath = $dirPath . DIRECTORY_SEPARATOR . $item;

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

	if (!rmdir($dirPath)) {
		return false;
	}
	return true;
}

function getDirectorySize($directory) {
	$size = 0;

	if (!is_dir($directory)) {
		return false;
	}

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		$size += $file->getSize();
	}

	return $size;
}

function createDirectoryWithErrorHandle($directoryName, $globalHTML) {
	if(file_exists($directoryName)) return;
	if(!mkdir($directoryName, 0755, true)) $globalHTML->error("Failed to create directory: $directoryName");
}

function createDirectory($directoryName) {
	if(file_exists($directoryName)) return;
	if(!mkdir($directoryName, 0755, true)) die("Could not create $directoryName");
}

function writeToFile($filePath, $text) {
	$fileHandle = fopen($filePath, 'w');
	if ($fileHandle === false) {
		return false;
	}
	
	$result = fwrite($fileHandle, $text);
	if ($result === false) {
		fclose($fileHandle);
		return false;
	}
	
	fclose($fileHandle);
	return true;
}

function createFileAndWriteText($directory, $fileName, $text) {
	$filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

	if (!file_exists($directory)) mkdir($directory, 0777, true);

	$file = fopen($filePath, 'w');

	if ($file) {
		fwrite($file, $text);
		fclose($file);
	} else {
		throw new Exception("Failed to create or open the file.");
	}
}

function copyFileWithNewName($sourceFilePath, $newFileName, $destinationDir = null) {
	if (!file_exists($sourceFilePath)) {
		return "Error: Source file does not exist.";
	}

	// If no destination directory is provided, use the directory of the source file
	if ($destinationDir === null) {
		$destinationDir = dirname($sourceFilePath);
	}

	// Ensure the destination directory ends with a slash
	$destinationDir = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

	// Construct the full path for the new file
	$newFilePath = $destinationDir . $newFileName;

	// Copy the file
	if (copy($sourceFilePath, $newFilePath)) {
		return "File copied successfully to: $newFilePath";
	} else {
		return "Error: Failed to copy the file.";
	}
}

function moveFileOnly(string $sourceFile, string $destDir) {
	// Ensure source file exists
	if (!is_file($sourceFile)) {
		return false;
	}

	// Ensure destination directory exists
	if (!is_dir($destDir)) {
		if (!mkdir($destDir, 0777, true)) {
			return false;
		}
	}

	$destPath = $destDir . DIRECTORY_SEPARATOR . basename($sourceFile);

	// Move the file
	if (!rename($sourceFile, $destPath)) {
		return false;
	}
}

function addSlashesToArray(&$arrayOfValuesForQuery) {
	foreach ($arrayOfValuesForQuery as &$item) {
		$item = "'" . addslashes($item) . "'";
	}
}
