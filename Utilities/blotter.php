<?php
// Heyuri! blotter.php
/* CONFIG */
define('BLOTTERFILE', '../static/html/blotter.log.txt');
define('ADDHEAD', file_get_contents('../static/html/styles.inc.html')); //deprecated
define('BOARDLIST', file_get_contents('../toplinks.txt'));
define('HOME', '//www.example.come/');
define('TITLE', 'Blotter');
define('PASS', 'changethispassword');
define('PHP_SELF', 'blotter.php');
define('DATE_FORMAT', 'Y/m/d');
define('BLOTTER_HTML', '../static/html/blotter.inc.html');
define('BLOTTER_LINES', 4);
define('HERE', '//www.example.com/blotter.php');

/* END CONFIG */

if(!is_file(BLOTTERFILE)) touch(BLOTTERFILE);
$blotter=file(BLOTTERFILE);

if($_SERVER['REQUEST_METHOD']=='POST'){
	if($_POST['pwd']!=PASS) die('Error: Invalid password.');
	$newblotter='<b>'.date(DATE_FORMAT).'</b> - '.$_POST['msg']."\r\n";
	$newblotter.=implode('',$blotter);
	file_put_contents(BLOTTERFILE,$newblotter);

	die('Blotter updated.<meta http-equiv="refresh" content="1;url='.PHP_SELF.'?rebuild"/>');
}

if($_SERVER['QUERY_STRING']=='rebuild'){
	// Update HTML files
	if(BLOTTER_HTML){
		$blotter = file(BLOTTERFILE);
		$dat = '<ul id="blotter">';
		for($i=0;$i<BLOTTER_LINES;$i++){
			if (!isset($blotter[$i])) break;
			if (!$blotter[$i]) continue;
			$dat.= '<li><div align="LEFT">'.$blotter[$i].'</div></li>';
		}
		$dat.= '<li><div align="RIGHT"><small>[<a href="'.HERE.'" target="_blank">Show All</a>]</small></div></li>';
		$dat.= '</ul>';
		file_put_contents(BLOTTER_HTML, $dat);
	}

	die('Blotter rebuilt.<meta http-equiv="refresh" content="1;url='.PHP_SELF.'"/>');
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<meta http-equiv="cache-control" content="max-age=0" />
			<meta http-equiv="cache-control" content="no-cache" />
			<meta http-equiv="expires" content="0" />
			<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
			<meta http-equiv="pragma" content="no-cache" />
			<meta name="viewport" content="width=device-width, initial-scale=1.0" />
			<meta name="Berry" content="no" />
			<title>Blotter@Heyuri</title>
			<meta name="robots" content="follow,archive" />
			<link class="linkstyle" rel="stylesheet" type="text/css" href="../static/css/heyuriclassic.css" title="Heyuri Classic" />
			<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="../static/css/futaba.css" title="Futaba" />
			<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="../static/css/oldheyuri.css" title="Sakomoto" />			<meta http-equiv="cache-control" content="no-cache" />
			<link rel="shortcut icon" href="../static/image/favicon.png" />

			<script type="text/javascript" src="../static/js/koko.js"></script>
			<script type="text/javascript" src="../static/js/koko/style.js"></script>			
<link rel="alternate" type="application/rss+xml" title="RSS 2.0 Feed" href="//img.heyuri.net/b/koko.php?mode=module&amp;load=mod_rss" /></head><body class="heyuri"><a name="top"></a>
		<div id="nav">
			<?=BOARDLIST?>
			<div class="adminbar" align="right">[<a class="extr" href="<?=HOME?>">Home</a>]</div>
		</div>
		<center class="logo"><h1><?=TITLE?></h1><hr size="1" width="50%"/></center>
<?php

if($_SERVER['QUERY_STRING']=='newblotter'){
	echo '<form action="'.PHP_SELF.'" method="POST">';
	echo <<<FORM
<table><tbody>
	<tr><td class="postblock"><label for="pwd"><b>Password</b></label></td><td><input type="password" name="pwd" id="pwd" value=""/></td></tr>
	<tr><td class="postblock"><label for="msg"><b>Message</b></label></td><td><input type="text" name="msg" id="msg" value="" size="40"/><input type="submit"/></td></tr>
</tbody></table>
</form>
<hr size="1" width="50%" />
FORM;
}

echo '<center><table class="postlists" cellspacing="0" cellpadding="0" border="1"><tbody>';
if(count($blotter)){
	foreach($blotter as $b){
		echo "<tr><td style='padding: 3px;'>$b</td></tr>";
	}
}else{
	echo '<tr><td>Blotter file is empty.</td></tr>';
}
echo '</tbody></table>';

?>
- <a rel="nofollow noreferrer license" href="https://web.archive.org/web/20150701123900/http://php.s3.to/" target="_blank">GazouBBS</a> + <a rel="nofollow noreferrer license" href="http://www.2chan.net/" target="_blank">futaba</a> + <a rel="nofollow noreferrer license" href="https://pixmicat.github.io/" target="_blank">Pixmicat!</a> + <a rel="nofollow noreferrer license" href="https://github.com/Heyuri/kokonotsuba/" target="_blank">Kokonotsuba</a> -
</center>
</body></html>
