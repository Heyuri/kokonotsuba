<?php

namespace Kokonotsuba\libraries;

use Kokonotsuba\request\request;
use function Puchiko\strings\sanitizeStr;

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
function CheckSupportGZip(request $request){
	$HTTP_ACCEPT_ENCODING = $request->getServer('HTTP_ACCEPT_ENCODING', '');
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
function getRemoteAddrOpenShift(request $request) {
	if (isset($_ENV['OPENSHIFT_REPO_DIR'])) {
		return $request->getServer('HTTP_X_FORWARDED_FOR', '');
	}
	return '';
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
function loadUploadData(string $inputName, int $index, request $request): array {
	// Ensure input exists and is a proper file upload entry
	$file = $request->getFile($inputName);
	if (
		$file !== null &&
		isset($file['tmp_name']) &&
		is_array($file['tmp_name']) &&
		!empty($file['tmp_name'][$index])
	) {
		$upfile = sanitizeStr($file['tmp_name'][$index]);
		$upfile_name = $file['name'][$index];
		$upfile_status = $file['error'][$index];

		return [$upfile, $upfile_name, $upfile_status];
	}

	// No file uploaded for this input/index
	return [
		'',
		'',
		UPLOAD_ERR_NO_FILE
	];
}