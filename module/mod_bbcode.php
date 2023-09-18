<?php
class mod_bbcode extends ModuleHelper { 
	private $myPage;
	private $urlcount;
	private	$ImgTagTagMode = 0; // [img] tag behavior (0: no conversion 1: conversion when no textures 2: always conversion)
	private	$URLTagMode = 0; // [url] tag behavior (0: no conversion 1: normal)
	private	$MaxURLCount = 2; // [url] tag upper limit (when the upper limit is exceeded, the tag is a trap tag [written to $URLTrapLog])
	private	$URLTrapLog = './URLTrap.log'; // [url]trap label log file
	private $AATagMode = 1; // Koko [0:Enabled 1:Disabled]
	private $supportRuby = 0; // <ruby> tag (0: not supported 1: supported)
	private $emotes = array(
			'nigra'=>STATIC_URL.'image/emote/nigra.gif',
			'sage'=>STATIC_URL.'image/emote/sage.gif',
			'longcat'=>STATIC_URL.'image/emote/longcat.gif',
			'tacgnol'=>STATIC_URL.'image/emote/tacgnol.gif',
			'angry'=>STATIC_URL.'image/emote/emo-yotsuba-angry.gif',
			'astonish'=>STATIC_URL.'image/emote/emo-yotsuba-astonish.gif',
			'biggrin'=>STATIC_URL.'image/emote/emo-yotsuba-biggrin.gif',
			'closed-eyes'=>STATIC_URL.'image/emote/emo-yotsuba-closed-eyes.gif',
			'closed-eyes2'=>STATIC_URL.'image/emote/emo-yotsuba-closed-eyes2.gif',
			'cool'=>STATIC_URL.'image/emote/emo-yotsuba-cool.gif',
			'cry'=>STATIC_URL.'image/emote/emo-yotsuba-cry.gif',
			'dark'=>STATIC_URL.'image/emote/emo-yotsuba-dark.gif',
			'dizzy'=>STATIC_URL.'image/emote/emo-yotsuba-dizzy.gif',
			'drool'=>STATIC_URL.'image/emote/emo-yotsuba-drool.gif',
			'love'=>STATIC_URL.'image/emote/emo-yotsuba-heart.gif',
			'glare'=>STATIC_URL.'image/emote/emo-yotsuba-glare.gif',
			'glare1'=>STATIC_URL.'image/emote/emo-yotsuba-glare-01.gif',
			'glare2'=>STATIC_URL.'image/emote/emo-yotsuba-glare-02.gif',
			'happy'=>STATIC_URL.'image/emote/emo-yotsuba-happy.gif',
			'huh'=>STATIC_URL.'image/emote/emo-yotsuba-huh.gif',
			'nosebleed'=>STATIC_URL.'image/emote/emo-yotsuba-nosebleed.gif',
			'nyaoo-closedeyes'=>STATIC_URL.'image/emote/emo-yotsuba-nyaoo-closedeyes.gif',
			'nyaoo-closed-eyes'=>STATIC_URL.'image/emote/emo-yotsuba-nyaoo-closedeyes.gif',
			'nyaoo'=>STATIC_URL.'image/emote/emo-yotsuba-nyaoo.gif',
			'nyaoo2'=>STATIC_URL.'image/emote/emo-yotsuba-nyaoo2.gif',
			'ph34r'=>STATIC_URL.'image/emote/emo-yotsuba-ph34r.gif',
			'ninja'=>STATIC_URL.'image/emote/emo-yotsuba-ph34r.gif',
			'rolleyes'=>STATIC_URL.'image/emote/emo-yotsuba-rolleyes.gif',
			'rollseyes'=>STATIC_URL.'image/emote/emo-yotsuba-rolleyes.gif',
			'sad'=>STATIC_URL.'image/emote/emo-yotsuba-sad.gif',
			'smile'=>STATIC_URL.'image/emote/emo-yotsuba-smile.gif',
			'sweat'=>STATIC_URL.'image/emote/emo-yotsuba-sweat.gif',
			'sweat2'=>STATIC_URL.'image/emote/emo-yotsuba-sweat2.gif',
			'sweat3'=>STATIC_URL.'image/emote/emo-yotsuba-sweat3.gif',
			'tongue'=>STATIC_URL.'image/emote/emo-yotsuba-tongue.gif',
			'unsure'=>STATIC_URL.'image/emote/emo-yotsuba-unsure.gif',
			'wink'=>STATIC_URL.'image/emote/emo-yotsuba-wink.gif',
			'x3'=>STATIC_URL.'image/emote/emo-yotsuba-x3.gif',
			'xd'=>STATIC_URL.'image/emote/emo-yotsuba-xd.gif',
			'xp'=>STATIC_URL.'image/emote/emo-yotsuba-xp.gif',
			'party'=>STATIC_URL.'image/emote/emo-yotsuba-partyhat.png',
			'mona2'=>STATIC_URL.'image/emote/mona2.gif',
			'nida'=>STATIC_URL.'image/emote/nida.gif',
			'banana'=>STATIC_URL.'image/emote/banana.gif',
			'af2'=>STATIC_URL.'image/emote/af2.gif',
			'nagato'=>STATIC_URL.'image/emote/nagato.gif',
			'folder'=>STATIC_URL.'image/emote/folder.gif',
			'sofa'=>STATIC_URL.'image/emote/sofa.gif',
			'iyahoo'=>STATIC_URL.'image/emote/iyahoo.gif',
		);

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->myPage = $this->getModulePageURL();// Base position
	}

	public function getModuleName(){
		return 'mod_bbcode : 內文BBCode轉換';
	}

	public function getModuleVersionInfo(){
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $resto, $imgWH){
		$com = $this->bb2html($com,$dest);
	}

	public function bb2html($string, $dest){
		$this->urlcount=0; // Reset counter
		$string = preg_replace('#\[b\](.*?)\[/b\]#si', '<b>\1</b>', $string);
		$string = preg_replace('#\[s\](.*?)\[/s\]#si', '<span class="spoiler">\1</span>', $string);
		$string = preg_replace('#\[spoiler\](.*?)\[/spoiler\]#si', '<span class="spoiler">\1</span>', $string);
		$string = preg_replace('#\[code\](.*?)\[/code\]#si', '<pre class="code">\1</pre>', $string);
		$string = preg_replace('#\[i\](.*?)\[/i\]#si', '<i>\1</i>', $string);
		$string = preg_replace('#\==(.*?)==#si', '<span class="soot">\1</span>', $string);
		$string = preg_replace('#\[u\](.*?)\[/u\]#si', '<u>\1</u>', $string);
		$string = preg_replace('#\[p\](.*?)\[/p\]#si', '<p>\1</p>', $string);
		$string = preg_replace('#\[sw\](.*?)\[/sw\]#si', '<pre class="sw">\1</pre>', $string);
		$string = preg_replace('#\[kao\](.*?)\[/kao\]#si', '<span class="ascii">\1</span>', $string);
		$string = preg_replace('#\[color=(\S+?)\](.*?)\[/color\]#si', '<font color="\1">\2</font>', $string);

		$string = preg_replace('#\[s([1-7])\](.*?)\[/s([1-7])\]#si', '<font size="\1">\2</font>', $string);

		$string = preg_replace('#\[del\](.*?)\[/del\]#si', '<del>\1</del>', $string);
		$string = preg_replace('#\[pre\](.*?)\[/pre\]#si', '<pre>\1</pre>', $string);
		$string = preg_replace('#\[quote\](.*?)\[/quote\]#si', '<blockquote>\1</blockquote>', $string);
		$string = preg_replace('#\[scroll\](.*?)\[/scroll\]#si', '<div style="overflow:scroll; height: 200px;">\1</div>', $string);
		
		if ($this->supportRuby){
		//add ruby tag
			$string = preg_replace('#\[ruby\](.*?)\[/ruby\]#si', '<ruby>\1</ruby>', $string);
			$string = preg_replace('#\[rt\](.*?)\[/rt\]#si', '<rt>\1</rt>', $string);
			$string = preg_replace('#\[rp\](.*?)\[/rp\]#si', '<rp>\1</rp>', $string);
		}

		if($this->URLTagMode){
			$string=preg_replace_callback('#\[url\](https?|ftp)(://\S+?)\[/url\]#si', array(&$this, '_URLConv1'), $string);
			$string=preg_replace_callback('#\[url\](\S+?)\[/url\]#si', array(&$this, '_URLConv2'), $string);
			$string=preg_replace_callback('#\[url=(https?|ftp)(://\S+?)\](.*?)\[/url\]#si', array(&$this, '_URLConv3'), $string);
			$string=preg_replace_callback('#\[url=(\S+?)\](.*?)\[/url\]#si', array(&$this, '_URLConv4'), $string);
			$this->_URLExcced();
		}

		if($this->AATagMode){
			$string=preg_replace('#\[aa\](.*?)\[/aa\]#si', '<pre class="ascii">\1</pre>', $string);
		}

		$string = preg_replace('#\[email\](\S+?@\S+?\\.\S+?)\[/email\]#si', '<a href="mailto:\1">\1</a>', $string);

		$string = preg_replace('#\[email=(\S+?@\S+?\\.\S+?)\](.*?)\[/email\]#si', '<a href="mailto:\1">\2</a>', $string);
		if (($this->ImgTagTagMode == 2) || ($this->ImgTagTagMode && !$dest)){
			$string = preg_replace('#\[img\](([a-z]+?)://([^ \n\r]+?))\[\/img\]#si', '<img class="bbcodeIMG" src="\1" style="border:1px solid \#021a40;" alt="\1" />', $string);
		}

		foreach ($this->emotes as $emo=>$url) {
			$string = str_replace(":$emo:", "<img title=\":$emo:\" class=\"emote\" src=\"$url\" alt=\"$emo\" border=\"0\" />", $string);
		}

		return $string;
	}

	private function _URLConv1($m){
		++$this->urlcount;
		return '<a class="bbcodeA" href="'.$m[1].$m[2].'" rel="nofollow noreferrer" target="_blank">'.$m[1].$m[2].'</a>';
	}

	private function _URLConv2($m){
		++$this->urlcount;
		return '<a class="bbcodeA" href="http://'.$m[1].'" rel="nofollow noreferrer" target="_blank">'.$m[1].'</a>';
	}

	private function _URLConv3($m){
		++$this->urlcount;
		return '<a class="bbcodeA" href="'.$m[1].$m[2].'" rel="nofollow noreferrer" target="_blank">'.$m[3].'</a>';
	}

	private function _URLConv4($m){
		++$this->urlcount;
		return '<a class="bbcodeA" href="http://'.$m[1].'" rel="nofollow noreferrer" target="_blank">'.$m[2].'</a>';
	}

	private function _URLRevConv($m){
		if($m[1]=='http' && $m[2]=='://'.$m[3]) {
			return '[url]'.$m[3].'[/url]';
		} elseif(($m[1].$m[2])==$m[3]) {
			return '[url]'.$m[1].$m[2].'[/url]';
		} else {
			if($m[1]=='http')
				return '[url='.substr($m[2],3).']'.$m[3].'[/url]';
			else
				return '[url='.$m[1].$m[2].']'.$m[3].'[/url]';
		}
	}

	private function _EMailRevConv($m){
		if($m[1]==$m[2]) return '[email]'.$m[1].'[/email]';
		else return '[email='.$m[1].']'.$m[2].'[/email]';
	}

	public function html2bb(&$string){
		$string = preg_replace('#<b>(.*?)</b>#si', '[b]\1[/b]', $string);
		$string = preg_replace('#<span class="spoiler">(.*?)</span>#si', '[s]\1[/s]', $string);
		$string = preg_replace('#<span class="spoiler">(.*?)</span>#si', '[spoiler]\1[/spoiler]', $string);
		$string = preg_replace('#<pre class="code">(.*?)</pre>#si', '[aa]\1[/aa]', $string);
		$string = preg_replace('#<i>(.*?)</i>#si', '[i]\1[/i]', $string);
		$string = preg_replace('#<span class="soot">(.*?)</span>#si', '==\1==', $string);
		$string = preg_replace('#<u>(.*?)</u>#si', '[u]\1[/u]', $string);
		$string = preg_replace('#<p>(.*?)</p>#si', '[p]\1[/p]', $string);
		$string = preg_replace('#<pre class="sw">(.*?)</pre>#si', '[sw]\1[/sw]', $string);
		
		$string = preg_replace('#<font color="(\S+?)">(.*?)</font>#si', '[color=\1]\2[/color]', $string);

		$string = preg_replace('#<font size="([1-7])">(.*?)</font>#si', '[s\1]\2[/s\1]', $string);

		$string = preg_replace('#<del>(.*?)</del>#si', '[del]\1[/del]', $string);
		$string = preg_replace('#<pre>(.*?)</pre>#si', '[pre]\1[/pre]', $string);
		$string = preg_replace('#<blockquote>(.*?)</blockquote>#si', '[quote]\1[/quote]', $string);

		if ($this->supportRuby){
			$string = preg_replace('#<ruby>(.*?)</ruby>#si', '[ruby]\1[/ruby]', $string);
			$string = preg_replace('#<rt>(.*?)</rt>#si', '[rt]\1[/rt]', $string);
			$string = preg_replace('#<rp>(.*?)</rp>#si', '[rp]\1[/rp]', $string);
		}

		$string = preg_replace_callback('#<a class="bbcodeA" href="(https?|ftp)(://\S+?)" rel="nofollow noreferrer" target="_blank">(.*?)</a>#si', array(&$this, '_URLRevConv'), $string);
		$string = preg_replace_callback('#<a class="bbcodeA" href="mailto:(\S+?@\S+?\\.\S+?)" rel="nofollow noreferrer" target="_blank">(.*?)</a>#si', array(&$this, '_EMailRevConv'), $string);
		$string = preg_replace('#<img class="bbcodeIMG" src="(([a-z]+?)://([^ \n\r]+?))" style="border:1px solid \#021a40;" alt=".*?" />#si', '[img]\1[/img]', $string);

	}


	private function _URLExcced(){
		if($this->urlcount > $this->MaxURLCount) {
		  	  $fh = fopen($this->URLTrapLog, 'a+b');
		  	  fwrite($fh, time()."\t$_SERVER[REMOTE_ADDR]\t$cnt\n");
		  	  fclose($fh);
		  	  error("URL:標籤超過上限");
		}
	}

	public function ModulePage(){
		$dat='';
		head($dat);
		$dat.='
BBCODE Settings:
<ul>
	<li>[b]Hello[/b] will become <b>Hello</b></li>
	<li>[u]Hello[/u] will become <u>Hello</u></li>
	<li>[i]Hello[/i] will become <i>Hello</i></li>
	<li>[del]Hello[/del] will become <s>Hello</s></li>
	<li>[aa]Hello[/aa] will become</li>
</ul>
';
		foot($dat);
		echo $dat;
	}
}
