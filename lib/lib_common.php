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

define("LEV_NONE", 0);
define("LEV_JANITOR", 1);
define("LEV_MODERATOR", 2);
define("LEV_ADMIN", 3);

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
 

/* 輸出表頭 | document head */
function head(&$dat,$resno=0){
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$PIO = PMCLibrary::getPIOInstance();

	$pte_vals = array('{$RESTO}'=>$resno?$resno:'', '{$IS_THREAD}'=>boolval($resno));
	if ($resno) {
		$post = $PIO->fetchPosts($resno);
		if (mb_strlen($post[0]['com']) <= 10){
			$CommentTitle = $post[0]['com'];
		} else {
			$CommentTitle = mb_substr($post[0]['com'],0,10,'UTF-8') . "...";
		}
		$pte_vals['{$PAGE_TITLE}'] = ($post[0]['sub'] ? $post[0]['sub'] : $CommentTitle).' - '.TITLE;
	}
	$dat .= $PTE->ParseBlock('HEADER',$pte_vals);
	$PMS->useModuleMethods('Head', array(&$dat,$resno)); // "Head" Hook Point
	$dat .= '</head>';
	$pte_vals += array('{$HOME}' => '[<a href="'.HOME.'" target="_top">'._T('head_home').'</a>]',
		'{$STATUS}' => '[<a href="'.PHP_SELF.'?mode=status">'._T('head_info').'</a>]',
		'{$ADMIN}' => '[<a href="'.PHP_SELF.'?mode=admin">'._T('head_admin').'</a>]',
		'{$REFRESH}' => '[<a href="'.PHP_SELF2.'?">'._T('head_refresh').'</a>]',
		'{$SEARCH}' => (USE_SEARCH) ? '[<a href="'.PHP_SELF.'?mode=search">'._T('head_search').'</a>]' : '',
		'{$HOOKLINKS}' => '');
	$PMS->useModuleMethods('Toplink', array(&$pte_vals['{$HOOKLINKS}'],$resno)); // "Toplink" Hook Point
	$dat .= $PTE->ParseBlock('BODYHEAD',$pte_vals);
}

/* 發表用表單輸出 | user contribution form */
function form(&$dat, $resno, $name='', $mail='', $sub='', $com='', $cat='', $preview=false){
	global $ADDITION_INFO;
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$hidinput =
		($resno ? '<input type="hidden" name="resto" value="'.$resno.'" />' : '').
		(TEXTBOARD_ONLY ? '' : '<input type="hidden" name="MAX_FILE_SIZE" value="{$MAX_FILE_SIZE}" />');

	$pte_vals = array(
		'{$RESTO}' => strval($resno),
		'{$IS_THREAD}' => $resno!=0,
		'{$FORM_HIDDEN}' => $hidinput,
		'{$MAX_FILE_SIZE}' => strval(TEXTBOARD_ONLY ? 0 : MAX_KB * 1024),
		'{$FORM_NAME_FIELD}' => '<input tabindex="1" maxlength="'.INPUT_MAX.'" type="text" name="name" id="name" size="28" value="'.$name.'" class="inputtext" />',
		'{$FORM_EMAIL_FIELD}' => '<input tabindex="2" maxlength="'.INPUT_MAX.'" type="text" name="email" id="email" size="28" value="'.$mail.'" class="inputtext" />',
		'{$FORM_TOPIC_FIELD}' => '<input tabindex="3" maxlength="'.INPUT_MAX.'"  type="text" name="sub" id="sub" size="28" value="'.$sub.'" class="inputtext" />',
		'{$FORM_SUBMIT}' => '<button tabindex="10" type="submit" name="mode" value="regist">'.($resno ? 'Post' : 'New Thread' ).'</button>',
		'{$FORM_COMMENT_FIELD}' => '<textarea tabindex="6" maxlength="'.COMM_MAX.'" name="com" id="com" cols="48" rows="4" class="inputtext">'.$com.'</textarea>',
		'{$FORM_DELETE_PASSWORD_FIELD}' => '<input tabindex="6" type="password" name="pwd" id="pwd" size="8" maxlength="8" value="" class="inputtext" />',
		'{$FORM_EXTRA_COLUMN}' => '',
		'{$FORM_FILE_EXTRA_FIELD}' => '',
		'{$FORM_NOTICE}' => (TEXTBOARD_ONLY?'':_T('form_notice',str_replace('|',', ',ALLOW_UPLOAD_EXT),MAX_KB,($resno ? MAX_RW : MAX_W),($resno ? MAX_RH : MAX_H))),
		'{$HOOKPOSTINFO}' => '');
	if (USE_PREVIEW) $pte_vals['{$FORM_SUBMIT}'].= '<button tabindex="11" type="submit" name="mode" value="preview">Preview</button>';
	if(!TEXTBOARD_ONLY && (RESIMG || !$resno)){
		if(isset($_FILES['upfile']['error']) && $_FILES['upfile']['error']!=UPLOAD_ERR_NO_FILE) $w = ($preview?'<small class="warning"><b>Please enter the file again:</b></small><br />':'');
		else $w = '';
		$pte_vals += array('{$FORM_ATTECHMENT_FIELD}' => $w.'<input type="file" name="upfile" id="upfile" />');

		if (!$resno) {
			$pte_vals += array('{$FORM_NOATTECHMENT_FIELD}' => '<input type="checkbox" name="noimg" id="noimg" value="on" />');
		}
		if(USE_UPSERIES) { // 啟動連貼機能
			$pte_vals['{$FORM_CONTPOST_FIELD}'] = '<input type="checkbox" name="up_series" id="up_series" value="on"'.((isset($_GET["upseries"]) && $resno)?' checked="checked"':'').' />';
		}
		$PMS->useModuleMethods('PostFormFile', array(&$pte_vals['{$FORM_FILE_EXTRA_FIELD}']));
	}
	$PMS->useModuleMethods('PostForm', array(&$pte_vals['{$FORM_EXTRA_COLUMN}'])); // "PostForm" Hook Point
	if(USE_CATEGORY) {
		$pte_vals += array('{$FORM_CATEGORY_FIELD}' => '<input tabindex="5" type="text" name="category" id="category" size="28" value="'.$cat.'" class="inputtext" />');
	}
	if(STORAGE_LIMIT) $pte_vals['{$FORM_NOTICE_STORAGE_LIMIT}'] = _T('form_notice_storage_limit',total_size(),STORAGE_MAX);
	$PMS->useModuleMethods('PostInfo', array(&$pte_vals['{$HOOKPOSTINFO}'])); // "PostInfo" Hook Point

	$dat .= $PTE->ParseBlock('POSTFORM',$pte_vals);
}

/* 輸出頁尾文字 | footer credits */
function foot(&$dat,$res=false){
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$pte_vals = array('{$FOOTER}'=>'','{$IS_THREAD}'=>$res);
	$PMS->useModuleMethods('Foot', array(&$pte_vals['{$FOOTER}'])); // "Foot" Hook Point
	$pte_vals['{$FOOTER}'] .= '<center>- <a rel="nofollow noreferrer license" href="https://web.archive.org/web/20150701123900/http://php.s3.to/" target="_blank">GazouBBS</a> + <a rel="nofollow noreferrer license" href="http://www.2chan.net/" target="_blank">futaba</a> + <a rel="nofollow noreferrer license" href="https://pixmicat.github.io/" target="_blank">Pixmicat!</a> + <a rel="nofollow noreferrer license" href="https://github.com/Heyuri/kokonotsuba/" target="_blank">Kokonotsuba</a> -</center>';
	$dat .= $PTE->ParseBlock('FOOTER',$pte_vals);
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
		<meta charset="utf-8" />
		<title>Redirecting...</title>
		<meta http-equiv="refresh" content="'.($time+1).';URL='.$to.'" />
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

/* 網址自動連結 */
function auto_link_callback2($matches) {
	$URL = $matches[1].$matches[2]; // https://example.com

	// Redirect URL!
	if (REF_URL) {
		$URL_Encode = urlencode($URL);  // https%3A%2F%2Fexample.com (For the address bar)
		return '<a href="'.REF_URL.'?'.$URL_Encode.'" target="_blank" rel="nofollow noreferrer">'.$URL.'</a>';
	}
	// Also works if its blank!
	return '<a href="'.$URL.'" target="_blank" rel="nofollow noreferrer">'.$URL.'</a>';
}
function auto_link_callback($matches){
	return (strtolower($matches[3]) == "</a>") ? $matches[0] : preg_replace_callback('/([a-zA-Z]+)(:\/\/[\w\+\$\;\?\.\{\}%,!#~*\/:@&=_-]+)/u', 'auto_link_callback2', $matches[0]);
}
function auto_link($proto){
	$proto = preg_replace('|<br\s*/?>|',"\n",$proto);
	$proto = preg_replace_callback('/(>|^)([^<]+?)(<.*?>|$)/m','auto_link_callback',$proto);
	return str_replace("\n",'<br />',$proto);
}

/* 引用標註 */
function quote_unkfunc($comment){
	$comment = preg_replace('/(^|<br \/>)((?:&gt;|＞).*?)(?=<br \/>|$)/ui', '$1<span class="unkfunc">$2</span>', $comment);
	$comment = preg_replace('/(^|<br \/>)((?:&lt;).*?)(?=<br \/>|$)/ui', '$1<span class="unkfunc2">$2</span>', $comment);
	return $comment;
}

/* quote links */
function quote_link($comment){
	$PIO = PMCLibrary::getPIOInstance();

	if(USE_QUOTESYSTEM){
		if(preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)){
			$matches_unique = array();
			foreach($matches as $val){ if(!in_array($val, $matches_unique)) array_push($matches_unique, $val); }
			foreach($matches_unique as $val){
				$post = $PIO->fetchPosts(intval($val[2]));
				if($post){
					$comment = str_replace($val[0], '<a href="'.PHP_SELF.'?res='.($post[0]['resto']?$post[0]['resto']:$post[0]['no']).'#p'.$post[0]['no'].'" class="quotelink">'.$val[0].'</a>', $comment);
				} else {
					$comment = str_replace($val[0], '<a href="javascript:void(0);" class="quotelink"><del>'.$val[0].'</del></a>', $comment);
				}
			}
		}
	}
	return $comment;
}

/* 取得完整的網址 */
function fullURL(){
	return '//'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], PHP_SELF));
}

/* 反櫻花字 */
function anti_sakura($str){
	return preg_match('/[\x{E000}-\x{F848}]/u', $str);
}

/* 輸出錯誤畫面 */
function error($mes, $dest=''){
	$PTE = PMCLibrary::getPTEInstance();

	if(is_file($dest)) unlink($dest);
	$pte_vals = array('{$SELF2}'=>PHP_SELF2.'?'.time(), '{$MESG}'=>$mes, '{$RETURN_TEXT}'=>_T('return'), '{$BACK_TEXT}'=>_T('error_back'));
	$dat = '';
	head($dat);
	$dat .= $PTE->ParseBlock('ERROR',$pte_vals);
	foot($dat);
	exit($dat);
}

/* 文字修整 */
function CleanStr($str, $IsAdmin=false){
	$str = trim($str); // 去除前後多餘空白
	if(get_magic_quotes_gpc()) $str = stripslashes($str); // "\"斜線符號去除
	// XML 1.1 Second Edition: 部分避免用字 (http://www.w3.org/TR/2006/REC-xml11-20060816/#charsets)
	$str = preg_replace('/([\x1-\x8\xB-\xC\xE-\x1F\x7F-\x84\x86-\x9F\x{FDD0}-\x{FDDF}])/u', '', htmlspecialchars($str));

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
		$chars = ord($str{$i});
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
function BanIPHostDNSBLCheck($IP, $HOST, &$baninfo){
	if(!BAN_CHECK) return false; // Disabled
	global $BANPATTERN, $DNSBLservers, $DNSBLWHlist;

	// IP/Hostname Check
	$HOST = strtolower($HOST);
	$checkTwice = ($IP != $HOST); // 是否需檢查第二次
	$IsBanned = false;
	foreach($BANPATTERN as $pattern){
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
	if(!$DNSBLservers[0]) return false; // Skip check
	if(array_search($IP, $DNSBLWHlist)!==false) return false; // IP位置在白名單內
	$rev = implode('.', array_reverse(explode('.', $IP)));
	$lastPoint = count($DNSBLservers) - 1; if($DNSBLservers[0] < $lastPoint) $lastPoint = $DNSBLservers[0];
	$isListed = false;
	for($i = 1; $i <= $lastPoint; $i++){
		$query = $rev.'.'.$DNSBLservers[$i].'.'; // FQDN
		$result = gethostbyname($query);
		if($result && ($result != $query)){ $isListed = $DNSBLservers[$i]; break; }
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

// Password validation
function valid($pass='') {
	if (!$pass) $pass = $_SESSION['kokologin']??'';
	$level = LEV_NONE;
	foreach ((is_array(JANITOR_HASH) ? JANITOR_HASH : array(JANITOR_HASH)) as $janitorhash) {
		if (crypt($pass, $janitorhash) === $janitorhash) return $level=LEV_JANITOR; }
	foreach ((is_array(MOD_HASH) ? MOD_HASH : array(MOD_HASH)) as $modhash) {
		if (crypt($pass, $modhash) === $modhash) return $level=LEV_MODERATOR; }
	foreach ((is_array(ADMIN_HASH) ? ADMIN_HASH : array(ADMIN_HASH)) as $adminhash) {
		if (crypt($pass, $adminhash) === $adminhash) return $level=LEV_ADMIN; }
	return $level;
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

// Moderator log
function logtime($desc, $from='SYSTEM') {
	if (is_int($from)) {
		switch ($from) {
			case LEV_NONE: $from = 'USER'; break;
			case LEV_JANITOR: $from = 'JANITOR'; break;
			case LEV_MODERATOR: $from = 'MODERATOR'; break;
			case LEV_ADMIN: $from = 'ADMIN'; break;
		}
	}
	$now = date("m/d/y H:i:s", $_SERVER['REQUEST_TIME']);
	$ip = ' ('.getREMOTE_ADDR().')';
	static $fp; if (!$fp) $fp = fopen(STORAGE_PATH.ACTION_LOG, "a");
	fwrite($fp, "$from$ip: $now: $desc\r\n");
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
