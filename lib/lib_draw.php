<?php
/*
* Draw library
*/

/* 網址自動連結 */
function auto_link_callback2($matches) {
	global $config;
	$URL = $matches[1].$matches[2]; // https://example.com

	// Redirect URL!
	if ($config['REF_URL']) {
		$URL_Encode = urlencode($URL);  // https%3A%2F%2Fexample.com (For the address bar)
		return '<a href="'.$config['REF_URL'].'?'.$URL_Encode.'" target="_blank" rel="nofollow noreferrer">'.$URL.'</a>';
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
	return str_replace("\n",'<br>',$proto);
}

/* 引用標註 */
function quote_unkfunc($comment){
	$comment = preg_replace('/(^|<br\s*\/?>)((?:&gt;|＞).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc">$2</span>', $comment);
	$comment = preg_replace('/(^|<br\s*\/?>)((?:&lt;).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc2">$2</span>', $comment);
	return $comment;
}

/* quote links */
function quote_link($comment){
	global $config;
	$PIO = PMCLibrary::getPIOInstance();
	
	if($config['USE_QUOTESYSTEM']){
		if(preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)){
			$matches_unique = array();
			foreach($matches as $val){ if(!in_array($val, $matches_unique)) array_push($matches_unique, $val); }
			foreach($matches_unique as $val){
				$post = $PIO->fetchPosts(intval($val[2]));
				if($post){
					$comment = str_replace($val[0], '<a href="'.$config['PHP_SELF'].'?res='.($post[0]['resto']?$post[0]['resto']:$post[0]['no']).'#p'.$post[0]['no'].'" class="quotelink">'.$val[0].'</a>', $comment);
				} else {
					$comment = str_replace($val[0], '<a href="javascript:void(0);" class="quotelink"><del>'.$val[0].'</del></a>', $comment);
				}
			}
		}
	}
	return $comment;
}

function buildThreadNavButtons($threadID, $threadInnerIterator, $config, $PIO) {		
	$threads = $PIO->fetchThreadList(0, $config['PAGE_DEF']); 
	$upArrow = '';
	$downArrow = '';
	$postFormButton = '<a title="Go to post form" href="#postform">&#9632;</a>';
	
	// Determine if thread is at the 'top'
	if ($threadInnerIterator == 0) {
		$upArrow = ''; // No thread above the current thread
	} else {
		$aboveThreadID = isset($threads[$threadInnerIterator - 1]) ? $threads[$threadInnerIterator - 1] : '';
		if ($aboveThreadID) {
			$upArrow = '<a title="Go to above thread" href="#t'.$aboveThreadID.'">&#9650;</a>';
		}
	}
	
	// Determine if thread is at the 'bottom'
	if ($threadInnerIterator >= count($threads) - 1) {
		$downArrow = ''; // No more threads below this one
	} else {
		$belowThreadID = isset($threads[$threadInnerIterator + 1]) ? $threads[$threadInnerIterator + 1] : '';
		if ($belowThreadID) {
			$downArrow = '<a title="Go to below thread" href="#t'.$belowThreadID.'">&#9660;</a>';
		}
	}
	
	// Assemble the button HTML
	$THREADNAV = $postFormButton.$upArrow.$downArrow;
	
	return $THREADNAV;
}
