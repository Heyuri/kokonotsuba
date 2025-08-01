<?php

namespace Kokonotsuba\Modules\bbCode;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain { 
	private $urlcount;
	private	$ImgTagTagMode = 0; // [img] tag behavior (0: no conversion 1: conversion when no textures 2: always conversion)
	private	$URLTagMode = 0; // [url] tag behavior (0: no conversion 1: normal)
	private	$MaxURLCount = 2; // [url] tag upper limit (when the upper limit is exceeded, the tag is a trap tag [written to $URLTrapLog])
	private	$URLTrapLog = './URLTrap.log'; // [url]trap label log file
	private $AATagMode = 1; // Koko [0:Enabled 1:Disabled]
	private $supportRuby = 1; // <ruby> tag (0: not supported 1: supported)
	private $emotes = array();

	private readonly ?string $staticUrl;

	public function getName(): string {
		return 'Kokonotsuba BBCode module';
	}

	public function getVersion(): string {
		return '2025';
	}

	public function initialize(): void {
		// Load configuration values
		$this->staticUrl = $this->getConfig('STATIC_URL', []);
		
		$this->initializeEmotes();

		// Register the listener for the PostInfo hook
		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread) {
			$this->onBeforeCommit($com, $file);  // Call the method to modify the form
		});
	}

	private function initializeEmotes(): void {
		$this->emotes = array(
			'nigra'=>$this->staticUrl.'image/emote/nigra.gif',
			'sage'=>$this->staticUrl.'image/emote/sage.gif',
			'longcat'=>$this->staticUrl.'image/emote/longcat.gif',
			'tacgnol'=>$this->staticUrl.'image/emote/tacgnol.gif',
			'angry'=>$this->staticUrl.'image/emote/emo-yotsuba-angry.gif',
			'astonish'=>$this->staticUrl.'image/emote/emo-yotsuba-astonish.gif',
			'biggrin'=>$this->staticUrl.'image/emote/emo-yotsuba-biggrin.gif',
			'closed-eyes'=>$this->staticUrl.'image/emote/emo-yotsuba-closed-eyes.gif',
			'closed-eyes2'=>$this->staticUrl.'image/emote/emo-yotsuba-closed-eyes2.gif',
			'cool'=>$this->staticUrl.'image/emote/emo-yotsuba-cool.gif',
			'cry'=>$this->staticUrl.'image/emote/emo-yotsuba-cry.gif',
			'dark'=>$this->staticUrl.'image/emote/emo-yotsuba-dark.gif',
			'dizzy'=>$this->staticUrl.'image/emote/emo-yotsuba-dizzy.gif',
			'drool'=>$this->staticUrl.'image/emote/emo-yotsuba-drool.gif',
			'love'=>$this->staticUrl.'image/emote/emo-yotsuba-heart.gif',
			'blush'=>$this->staticUrl.'image/emote/emo-yotsuba-blush3.gif',
			'mask'=>$this->staticUrl.'image/emote/emo-yotsuba-mask.gif',
			'lolico'=>$this->staticUrl.'image/emote/emo-yotsuba-lolico.gif',
			'glare'=>$this->staticUrl.'image/emote/emo-yotsuba-glare.gif',
			'glare1'=>$this->staticUrl.'image/emote/emo-yotsuba-glare-01.gif',
			'glare2'=>$this->staticUrl.'image/emote/emo-yotsuba-glare-02.gif',
			'happy'=>$this->staticUrl.'image/emote/emo-yotsuba-happy.gif',
			'huh'=>$this->staticUrl.'image/emote/emo-yotsuba-huh.gif',
			'nosebleed'=>$this->staticUrl.'image/emote/emo-yotsuba-nosebleed.gif',
			'nyaoo-closedeyes'=>$this->staticUrl.'image/emote/emo-yotsuba-nyaoo-closedeyes.gif',
			'nyaoo-closed-eyes'=>$this->staticUrl.'image/emote/emo-yotsuba-nyaoo-closedeyes.gif',
			'nyaoo'=>$this->staticUrl.'image/emote/emo-yotsuba-nyaoo.gif',
			'nyaoo2'=>$this->staticUrl.'image/emote/emo-yotsuba-nyaoo2.gif',
			'ph34r'=>$this->staticUrl.'image/emote/emo-yotsuba-ph34r.gif',
			'ninja'=>$this->staticUrl.'image/emote/emo-yotsuba-ph34r.gif',
			'rolleyes'=>$this->staticUrl.'image/emote/emo-yotsuba-rolleyes.gif',
			'rollseyes'=>$this->staticUrl.'image/emote/emo-yotsuba-rolleyes.gif',
			'sad'=>$this->staticUrl.'image/emote/emo-yotsuba-sad.gif',
			'smile'=>$this->staticUrl.'image/emote/emo-yotsuba-smile.gif',
			'sweat'=>$this->staticUrl.'image/emote/emo-yotsuba-sweat.gif',
			'sweat2'=>$this->staticUrl.'image/emote/emo-yotsuba-sweat2.gif',
			'sweat3'=>$this->staticUrl.'image/emote/emo-yotsuba-sweat3.gif',
			'tongue'=>$this->staticUrl.'image/emote/emo-yotsuba-tongue.gif',
			'unsure'=>$this->staticUrl.'image/emote/emo-yotsuba-unsure.gif',
			'wink'=>$this->staticUrl.'image/emote/emo-yotsuba-wink.gif',
			'x3'=>$this->staticUrl.'image/emote/emo-yotsuba-x3.gif',
			'xd'=>$this->staticUrl.'image/emote/emo-yotsuba-xd.gif',
			'xp'=>$this->staticUrl.'image/emote/emo-yotsuba-xp.gif',
			'party'=>$this->staticUrl.'image/emote/emo-yotsuba-partyhat.png',
			'mona2'=>$this->staticUrl.'image/emote/mona2.gif',
			'nida'=>$this->staticUrl.'image/emote/nida.gif',
			'saitama'=>$this->staticUrl.'image/emote/anime_saitama05.gif',
			'banana'=>$this->staticUrl.'image/emote/banana.gif',
			'onigiri'=>$this->staticUrl.'image/emote/onigiri.gif',
			'shii'=>$this->staticUrl.'image/emote/anime_shii01.gif',
			'af2'=>$this->staticUrl.'image/emote/af2.gif',
			'pata'=>$this->staticUrl.'image/emote/u_pata.gif',
			'depression'=>$this->staticUrl.'image/emote/u_sasu.gif',
			'saitama2'=>$this->staticUrl.'image/emote/anime_saitama06.gif',
			'monapc'=>$this->staticUrl.'image/emote/anime_miruna_pc.gif',
			'purin'=>$this->staticUrl.'image/emote/purin.gif',
			'ranta'=>$this->staticUrl.'image/emote/anime_imanouchi04.gif',
			'nagato'=>$this->staticUrl.'image/emote/nagato.gif',
			'foruda'=>$this->staticUrl.'image/emote/foruda.gif',
			'sofa'=>$this->staticUrl.'image/emote/sofa.gif',
			'hardgay'=>$this->staticUrl.'image/emote/hg.gif',
			'iyahoo'=>$this->staticUrl.'image/emote/iyahoo.gif',
			'tehegg'=>$this->staticUrl.'image/emote/egg.gif',
			'kuz'=>$this->staticUrl.'image/emote/emo-yotsuba-tomo.gif',
			'emo'=>$this->staticUrl.'image/emote/emo.gif',
			'dance'=>$this->staticUrl.'image/emote/heyuri-dance.gif',
			'dance2'=>$this->staticUrl.'image/emote/heyuri-dance-pantsu.gif',
			'kuma6'=>$this->staticUrl.'image/emote/kuma6.gif',
			'waha'=>$this->staticUrl.'image/emote/waha.gif',
		);
		
	}
	
	public function onBeforeCommit(&$com, $file){
		$com = $this->bb2html($com, $file);
	}

	public function bb2html($string, $file){
		$this->urlcount=0; // Reset counter

		// Extract [code] and [code=lang] blocks before processing other BBCode
		$codeBlocks = [];
		$string = preg_replace_callback('#\[code(?:=([a-zA-Z0-9_+\-]+))?\](.*?)\[/code\]#si', function($matches) use (&$codeBlocks) {
			$key = '[[[CODEBLOCK_' . count($codeBlocks) . ']]]';
			$lang = isset($matches[1]) ? strtolower($matches[1]) : null;
			$content = preg_replace('#<br\s*/?>#i', "\n", $matches[2]);
			if ($lang) {
				$codeBlocks[$key] = '<pre class="code">' . $this->highlightCodeSyntax($content, $lang) . '</pre>';
			} else {
				$codeBlocks[$key] = '<pre class="code">' . htmlspecialchars_decode(htmlspecialchars($content, ENT_NOQUOTES, 'UTF-8')) . '</pre>';
			}
			return $key;
		}, $string);

		// Preprocess the BBCode to fix nesting issues
		$string = $this->fixBBCodeNesting($string);

		$string = preg_replace('#\[b\](.*?)\[/b\]#si', '<b>\1</b>', $string);
		$string = preg_replace('#\[s\](.*?)\[/s\]#si', '<span class="spoiler">\1</span>', $string);
		$string = preg_replace('#\[spoiler\](.*?)\[/spoiler\]#si', '<span class="spoiler">\1</span>', $string);
		$string = preg_replace('#\[code\](.*?)\[/code\]#si', '<pre class="code">\1</pre>', $string);
		$string = preg_replace('#\[i\](.*?)\[/i\]#si', '<i>\1</i>', $string);
		$string = preg_replace('#\[u\](.*?)\[/u\]#si', '<u>\1</u>', $string);
		$string = preg_replace('#\[p\](.*?)\[/p\]#si', '<p>\1</p>', $string);
		$string = preg_replace('#\[sw\](.*?)\[/sw\]#si', '<pre class="sw">\1</pre>', $string);
		$string = preg_replace('#\[kao\](.*?)\[/kao\]#si', '<span class="ascii">\1</span>', $string);
		
		$string = preg_replace('#\[color=(\S+?)\](.*?)\[/color\]#si', '<span style="color:\1;">\2</span>', $string);

		$string = preg_replace('#\[s([1-7])\](.*?)\[/s([1-7])\]#si', '<span class="fontSize\1">\2</span>', $string);

		$string = preg_replace('#\[del\](.*?)\[/del\]#si', '<del>\1</del>', $string);
		$string = preg_replace('#\[pre\](.*?)\[/pre\]#si', '<pre>\1</pre>', $string);
		$string = preg_replace('#\[quote\](.*?)\[/quote\]#si', '<blockquote>\1</blockquote>', $string);
		$string = preg_replace('#\[scroll\](.*?)\[/scroll\]#si', '<div class="scrollText">\1</div>', $string);

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
		if (($this->ImgTagTagMode == 2) || ($this->ImgTagTagMode && !$file)){
			$string = preg_replace('#\[img\](([a-z]+?)://([^ \n\r]+?))\[\/img\]#si', '<img class="bbcodeIMG" src="\1" style="border:1px solid \#021a40;" alt="\1">', $string);
		}

		foreach ($this->emotes as $emo=>$url) {
			$string = str_replace(":$emo:", "<img title=\":$emo:\" class=\"emote\" src=\"$url\" alt=\":$emo:\">", $string);
		}

		// Restore preserved code blocks
		if (!empty($codeBlocks)) {
			$string = strtr($string, $codeBlocks);
		}

		return $string;
	}

	// New function to fix improperly nested BBCode tags
	private function fixBBCodeNesting($text){
		// List of supported tags. Only these tags will be processed for nesting correction.
		$supportedTags = array('b', 'i', 'spoiler', 'color', 's', 'u', 's1', 's2', 's3', 's4', 's5', 's6', 's7', 'code', 'pre', 'aa', 'kao', 'sw', 'quote', 'ruby', 'rt', 'rp');
		
		$pattern = '/(\[\/?[a-zA-Z0-9]+\b(?:=[^\]]+)?\])/i';
		$tokens = array();
		$lastPos = 0;
		if(preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)){
			foreach($matches[0] as $match){
				$pos = $match[1];
				$tagStr = $match[0];
				if($pos > $lastPos){
					$tokens[] = array(
						'type' => 'text',
						'content' => substr($text, $lastPos, $pos - $lastPos)
					);
				}
				// Check for closing tag
				if(preg_match('/^\[\/([a-zA-Z0-9]+)\]/i', $tagStr, $m)){
					$tag = strtolower($m[1]);
					if(!in_array($tag, $supportedTags)){
						$tokens[] = array(
							'type' => 'text',
							'content' => $tagStr
						);
					} else {
						$tokens[] = array(
							'type' => 'tag',
							'closing' => true,
							'tag' => $tag,
							'full' => $tagStr
						);
					}
				}else if(preg_match('/^\[([a-zA-Z0-9]+)(=[^\]]+)?\]/i', $tagStr, $m)){
					$tag = strtolower($m[1]);
					if(!in_array($tag, $supportedTags)){
						$tokens[] = array(
							'type' => 'text',
							'content' => $tagStr
						);
					} else {
						$tokens[] = array(
							'type' => 'tag',
							'closing' => false,
							'tag' => $tag,
							'attr' => isset($m[2]) ? $m[2] : '',
							'full' => $tagStr
						);
					}
				}else{
					$tokens[] = array(
						'type' => 'text',
						'content' => $tagStr
					);
				}
				$lastPos = $pos + strlen($tagStr);
			}
		}
		if($lastPos < strlen($text)){
			$tokens[] = array(
				'type' => 'text',
				'content' => substr($text, $lastPos)
			);
		}
		
		$outputTokens = array();
		$stack = array();
		$tokenCount = count($tokens);
		for($i = 0; $i < $tokenCount; $i++){
			$token = $tokens[$i];
			if($token['type'] == 'text'){
				$outputTokens[] = $token;
			}else if(!$token['closing']){
				$outputTokens[] = $token;
				$stack[] = array(
					'tag' => $token['tag'],
					'attr' => $token['attr'],
					'pos' => count($outputTokens)-1
				);
			}else{
				$tagName = $token['tag'];
				if(!empty($stack) && end($stack)['tag'] == $tagName){
					array_pop($stack);
					$outputTokens[] = $token;
				}else{
					$found = false;
					$temp = array();
					while(!empty($stack)){
						$top = array_pop($stack);
						$temp[] = $top;
						if($top['tag'] == $tagName){
							$found = true;
							break;
						}
					}
					if($found){
						$toReopen = array();
						$matchingTag = array_pop($temp);
						while(!empty($temp)){
							$misplaced = array_pop($temp);
							$outputTokens[] = array(
								'type' => 'tag',
								'closing' => true,
								'tag' => $misplaced['tag'],
								'full' => '[/' . $misplaced['tag'] . ']'
							);
							$toReopen[] = $misplaced;
						}
						$outputTokens[] = $token;
						foreach($toReopen as $tagToReopen){
							$nextToken = ($i+1 < $tokenCount) ? $tokens[$i+1] : null;
							if($nextToken && $nextToken['type'] == 'tag' && $nextToken['closing'] && $nextToken['tag'] == $tagToReopen['tag']){
								$i++; // Skip the next closing tag
							}else{
								$outputTokens[] = array(
									'type' => 'tag',
									'closing' => false,
									'tag' => $tagToReopen['tag'],
									'attr' => $tagToReopen['attr'],
									'full' => '[' . $tagToReopen['tag'] . $tagToReopen['attr'] . ']'
								);
								$stack[] = $tagToReopen;
							}
						}
					}else{
						// No matching opening tag found; ignore this closing tag.
					}
				}
			}
		}
		while(!empty($stack)){
			$open = array_pop($stack);
			$outputTokens[] = array(
				'type' => 'tag',
				'closing' => true,
				'tag' => $open['tag'],
				'full' => '[/' . $open['tag'] . ']'
			);
		}
		$result = '';
		foreach($outputTokens as $tok){
			if($tok['type'] == 'text'){
				$result .= $tok['content'];
			}else{
				$result .= $tok['full'];
			}
		}
		return $result;
	}

	private function highlightCodeSyntax($code, $lang) {
		$lang = strtolower($lang);
	
		$keywords = [];
		$commentPatterns = [];
		$extraHighlighter = null;
	
		switch ($lang) {
			case 'c':
			case 'cpp':
			case 'c++':
				$keywords = ['int','char','float','double','if','else','for','while','return','struct','class','include'];
				$commentPatterns = ['#//.*?$#m', '#/\*.*?\*/#s'];
				break;
	
			case 'php':
				$keywords = ['function','echo','print','if','else','foreach','while','return','class','public','private','protected','static'];
				$commentPatterns = ['#//.*?$#m', '#/\*.*?\*/#s', '#\#.*?$#m'];
				break;
	
			case 'js':
			case 'javascript':
				$keywords = ['function','var','let','const','if','else','for','while','return'];
				$commentPatterns = ['#//.*?$#m', '#/\*.*?\*/#s'];
				break;
	
			case 'py':
			case 'python':
				$keywords = ['def','return','if','elif','else','for','while','import','from','as','class','try','except','with','lambda','pass','break','continue'];
				$commentPatterns = ['#\#.*?$#m', '#(&quot;&quot;&quot;|&apos;&apos;&apos;)(.*?)\1#s'];
				break;
	
			case 'pl':
			case 'perl':
				$keywords = ['sub','my','if','else','foreach','while','return','print','use','package'];
				$commentPatterns = ['#\#.*?$#m', '#=begin(.*?)=end#s'];
				break;


			case 'f':
			case 'fortran':
				$keywords = ['program','end','integer','real','double','do','if','then','else','print','call','subroutine','function','return'];
				$commentPatterns = ['#^!.*?$#m'];
				break;
	
			case 'html':
				$commentPatterns = ['#&lt;!--.*?--&gt;#s'];
				$extraHighlighter = function($text) {
					return preg_replace('/(&lt;\/?)([a-zA-Z0-9\-]+)(?=[\s&>])/', '$1<span class="codeKeyword">$2</span>', $text);
				};
				break;
	
			case 'css':
				$commentPatterns = ['#/\*.*?\*/#s'];
				$extraHighlighter = function($text) {
					return preg_replace('/([{\s;])([a-zA-Z0-9\-]+)(?=\s*:)/', '$1<span class="codeKeyword">$2</span>', $text);
				};
				break;
	
			default:
				return htmlspecialchars_decode(htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8'));
		}
	
		$escaped = htmlspecialchars_decode(htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8'));
	
		// Highlight comments
		$commentTokens = [];
		foreach ($commentPatterns as $pattern) {
			$escaped = preg_replace_callback($pattern, function($m) use (&$commentTokens) {
				$key = '[[[COMMENT_' . count($commentTokens) . ']]]';
				$commentTokens[$key] = '<span class="codeComment">' . $m[0] . '</span>';
				return $key;
			}, $escaped);
		}
	
		// Highlight keywords (if defined)
		if (!empty($keywords)) {
			$pattern = '/\b(' . implode('|', array_map('preg_quote', $keywords)) . ')\b/';
			$escaped = preg_replace($pattern, '<span class="codeKeyword">$1</span>', $escaped);
		}
	
		// Highlight additional regex matches
		if ($extraHighlighter) {
			$escaped = $extraHighlighter($escaped);
		}
	
		// Restore comments
		if (!empty($commentTokens)) {
			$escaped = strtr($escaped, $commentTokens);
		}
	
		return $escaped;
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
		$string = preg_replace('#<u>(.*?)</u>#si', '[u]\1[/u]', $string);
		$string = preg_replace('#<p>(.*?)</p>#si', '[p]\1[/p]', $string);
		$string = preg_replace('#<pre class="sw">(.*?)</pre>#si', '[sw]\1[/sw]', $string);

		$string = preg_replace('#<span style="color:(\S+?);">(.*?)</span>#si', '[color=\1]\2[/color]', $string);

		$string = preg_replace('#<span class="fontSize([1-7])">(.*?)</span>#si', '[s\1]\2[/s\1]', $string);

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
		$string = preg_replace('#<img class="bbcodeIMG" src="(([a-z]+?)://([^ \n\r]+?))" style="border:1px solid \#021a40;" alt=".*?">#si', '[img]\1[/img]', $string);

	}


	private function _URLExcced(){
		if($this->urlcount > $this->MaxURLCount) {
		  	  $fh = fopen($this->URLTrapLog, 'a+b');
		  	  fwrite($fh, time()."\t$_SERVER[REMOTE_ADDR]\t{$this->urlcount}\n");
		  	  fclose($fh);
		  	  throw new \BoardException("URL: Tags exceed max limit");
		}
	}

	public function ModulePage(){
		$htmlOutput = '';

		$htmlOutput .= $this->moduleContext->board->getBoardHead("BBCode settings");
		
		$htmlOutput .= '
BBCODE Settings:
<ul>
	<li>[b]Hello[/b] will become <b>Hello</b></li>
	<li>[u]Hello[/u] will become <u>Hello</u></li>
	<li>[i]Hello[/i] will become <i>Hello</i></li>
	<li>[del]Hello[/del] will become <s>Hello</s></li>
	<li>[aa]Hello[/aa] will become</li>
</ul>
';
		$htmlOutput .= $this->moduleContext->board->getBoardFooter();

		echo $htmlOutput;
	}
}
