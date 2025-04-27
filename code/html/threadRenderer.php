<?php

/*
* thread html renderer for Kokonotsuba!
* Handles high-level output for threads. The actual html resides in templates/
*/ 

class threadRenderer {
	private board $board;
	private array $config;
	private globalHTML $globalHTML;
	private moduleEngine $moduleEngine;
	private templateEngine $templateEngine;
	private mixed $PIO;
	private IFileIO $FileIO;

	public function __construct(board $board, array $config, globalHTML $globalHTML, moduleEngine $moduleEngine, templateEngine $templateEngine) {
		$this->board = $board;
		$this->config = $config;
		$this->globalHTML = $globalHTML;
		$this->moduleEngine = $moduleEngine;
		$this->templateEngine = $templateEngine;

		$this->PIO = PIOPDO::getInstance();
		$this->FileIO = PMCLibrary::getFileIOInstance();
	}

	/**
	 * Main render function to build full HTML of thread and replies.
	 */
	public function render(array $threads,
	 array $currentPageThreads,
	 mixed $tree_cut, 
	 array $posts, int $hiddenReply, 
	 string $thread_uid, 
	 mixed $arr_kill, 
	 bool $kill_sensor, 
	 bool $showquotelink = true, 
	 bool $adminMode = false, 
	 int $threadIterator = 0, 
	 string $overboardBoardTitleHTML = '', 
	 string $crossLink = '',
	 array $templateValues = []): string {
		$thread_uid = $thread_uid ?: 0;
		$thdat = '';
		
		// whether this is reply mode
		$replyMode = $thread_uid ? true : false;
		
		// whether this is thread (index) mode
		$threadMode = $thread_uid ? false : true;

		// thread uid
		$thread_uid = $posts[0]['thread_uid'];
		
		// post op number
		$postOPNumber = $posts[0]['no'];

		// number of replies
		$replyCount = count($posts);
	
		if (is_array($tree_cut)) $tree_cut = array_flip($tree_cut);
	
		// render posts for a thread
		foreach ($posts as $i => $post) {
			$thdat .= $this->renderSinglePost($posts,
				$post,
				$i,
				$threadMode,
				$adminMode,
				$showquotelink,
				$currentPageThreads,
				$threadIterator,
				$hiddenReply,
				$kill_sensor,
				$arr_kill,
				$postOPNumber,
				$replyMode,
				$replyCount,
				$overboardBoardTitleHTML,
				$thread_uid,
				$crossLink,
				$templateValues
			);
		}
	
		$thdat .= $this->templateEngine->ParseBlock('THREADSEPARATE', $thread_uid ? array('{$RESTO}' => $postOPNumber) : array());
		return $thdat;
	}
	
	/*
	* Render an individual post for a thread
	*/
	private function renderSinglePost(array $threadPosts,
		array $post,
		int $i,
		bool $threadMode,
		bool $adminMode,
		bool $showquotelink,
		array $currentPageThreads,
		int $threadIterator,
		int $hiddenReply,
		bool $kill_sensor,
		array $arr_kill,
		int $postOPNumber,
		bool $replyMode,
		int $replyCount,
		string $overboardBoardTitleHTML,
		string $thread_uid,
		string $crossLink,
		array $templateValues,
	): string {
		$isReply = $i > 0;
		$data = $this->preparePostData($post);

		

		[$REPLYBTN, $QUOTEBTN] = $this->buildQuoteAndReplyButtons($data['no'], $postOPNumber, $replyMode, $thread_uid, $showquotelink, $crossLink);

		$categoryHTML = $this->processCategoryLinks($data['category'], $crossLink);
		$IMG_BAR = ($data['ext']) ? $this->buildAttachmentBar($data['tim'], $data['ext'], $data['fname'], $data['imgsize'], $data['imgw'], $data['imgh'], $data['tw'], $data['th'], '') : '';

		$POSTFORM_EXTRA = $WARN_BEKILL = $WARN_OLD = $WARN_ENDREPLY = $WARN_HIDEPOST = $THREADNAV = $BACKLINKS = '';

		// Navigation
		if ($threadMode) {
			$THREADNAV = $this->globalHTML->buildThreadNavButtons($currentPageThreads, $threadIterator);
		}

		// Admin controls hook
		if ($adminMode) {
			$modFunc = '';
			$this->moduleEngine->useModuleMethods('AdminList', array(&$modFunc, $post, $isReply));
			$POSTFORM_EXTRA .= $modFunc;
		}

		// File size warning
		if ($this->config['STORAGE_LIMIT'] && $kill_sensor && isset($arr_kill[$data['no']])) {
			$WARN_BEKILL = '<div class="warning">'._T('warn_sizelimit').'</div>';
		}

		// Hidden reply notice
		if (!$isReply && $hiddenReply) {
			$WARN_HIDEPOST = '<div class="omittedposts">'._T('notice_omitted', $hiddenReply).'</div>';
		}

		// Old thread warning
		if (!$isReply && $this->config['MAX_AGE_TIME'] && $_SERVER['REQUEST_TIME'] - $post['time'] > ($this->config['MAX_AGE_TIME'] * 3600)) {
			$data['com'] .= "<p class='markedDeletion'><span class='warning'>"._T('warn_oldthread')."</span></p>";
		}

		// Template binding
		$templateValues += $isReply
			? bindReplyValuesToTemplate(
				$this->board, $this->config, $data['post_uid'], $data['no'], $postOPNumber, $data['sub'], $data['name'], $data['now'],
				$categoryHTML, $QUOTEBTN, $IMG_BAR, $data['imgsrc'] ?? '', $WARN_BEKILL, $data['com'], $POSTFORM_EXTRA, '', $BACKLINKS, $thread_uid
			)
			: bindOPValuesToTemplate(
				$this->board, $this->config, $data['post_uid'], $data['no'], $data['sub'], $data['name'], $data['now'],
				$categoryHTML, $QUOTEBTN, $REPLYBTN, $IMG_BAR, $data['imgsrc'] ?? '', $data['fname'], $data['imgsize'],
				$data['imgw'], $data['imgh'], $data['imageURL'] ?? '', $replyCount, $WARN_OLD, $WARN_BEKILL,
				$WARN_ENDREPLY, $WARN_HIDEPOST, $data['com'], $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $thread_uid
			);

		// append board title to thread 
		$templateValues['{$BOARD_THREAD_NAME}'] = $overboardBoardTitleHTML;

		// bind post op number to resto
		if ($replyMode) {
			$templateValues['{$RESTO}'] = $postOPNumber;
		}

		$this->moduleEngine->useModuleMethods($isReply ? 'ThreadReply' : 'ThreadPost', array(&$templateValues, $post, $threadPosts, $isReply));
		return $this->templateEngine->ParseBlock($isReply ? 'REPLY' : 'THREAD', $templateValues);
	}

	 /**
	 * Sanitize and format post data for rendering using associative arrays.
	 */
	private function preparePostData(array $post): array {
		// Basic fields
		$email = isset($post['email']) ? trim($post['email']) : '';
		$name = $post['name'] ?? '';
		$now = $post['now'] ?? '';
		$com = $post['com'] ?? '';
		$ext = $post['ext'] ?? '';
		$tim = $post['tim'] ?? '';
		$fname = $post['fname'] ?? '';
		$imgw = $post['imgw'] ?? 0;
		$imgh = $post['imgh'] ?? 0;
		$tw = $post['tw'] ?? 0;
		$th = $post['th'] ?? 0;
		$imgsize = $post['imgsize'] ?? '';
	
		// Mailto formatting
		if ($this->config['CLEAR_SAGE']) {
			$email = preg_replace('/^sage( *)/i', '', $email);
		}
		if ($this->config['ALLOW_NONAME'] == 2 && $email) {
			$now = "<a href=\"mailto:$email\">$now</a>";
		} elseif ($email) {
			$name = "<a href=\"mailto:$email\">$name</a>";
		}
	
		$com = $this->globalHTML->quote_link($this->board, $this->PIO, $com);
		$com = $this->globalHTML->quote_unkfunc($com);
	
		// Image setup
		$imgsrc = '';
		$imageURL = '';
		if ($ext) {
			$imageURL = $this->FileIO->getImageURL($tim . $ext, $this->board);
			$thumbName = $this->FileIO->resolveThumbName($tim, $this->board);
	
			if ($tw && $th && $thumbName) {
				$thumbURL = $this->FileIO->getImageURL($thumbName, $this->board);
				$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$thumbURL.'" width="'.$tw.'" height="'.$th.'" class="postimg" alt="'.$imgsize.'" title="Click to show full image"></a>';
			} elseif ($ext === ".swf") {
				$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$this->config['SWF_THUMB'].'" class="postimg" alt="SWF Embed"></a>';
			} else {
				$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$this->config['STATIC_URL'].'image/nothumb.gif" class="postimg" alt="'.$imgsize.'" hspace="20" vspace="3" border="0" align="left"></a>'; // Default display style 	(when no preview image)
			}
		}
	
		// Return everything needed
		return [
			'email' => $email,
			'name' => $name,
			'now' => $now,
			'com' => $com,
			'no' => $post['no'],
			'sub' => $post['sub'],
			'status' => $post['status'],
			'tim' => $tim,
			'ext' => $ext,
			'fname' => $fname,
			'imgsize' => $imgsize,
			'imgw' => $imgw,
			'imgh' => $imgh,
			'tw' => $tw,
			'th' => $th,
			'category' => $post['category'],
			'post_uid' => $post['post_uid'],
			'imgsrc' => $imgsrc,
			'imageURL' => $imageURL
		];
	}

	/**
	 * Builds quote and reply links for post display.
	 */
	private function buildQuoteAndReplyButtons(int $no, int $postOPNumber, bool $replyMode, string $thread_uid, bool $showquotelink, string $crossLink): array {
		$REPLYBTN = $QUOTEBTN = '';
		$self = $this->config['PHP_SELF'];

		if ($this->config['USE_QUOTESYSTEM']) {
			if ($thread_uid) {
				$QUOTEBTN = '<a href="'.$crossLink.$self.'?res='.$postOPNumber.'#q'.($showquotelink ? "{$postOPNumber}_" : '').$no.'" class="qu" title="Quote">'.$no.'</a>';
			} else {
				
				$QUOTEBTN = '<a href="'.$crossLink.$self.'?res='.$postOPNumber.'#q'.$no.'" title="Quote">'.$no.'</a>';
			}
		} else {
			$QUOTEBTN = $no;
		}

		// add [Reply] button
		if (!$replyMode) $REPLYBTN = '[<a href="'.$crossLink.$self.'?res='.$no.'">'._T('reply_btn').'</a>]';

		return [$REPLYBTN, $QUOTEBTN];
	}

	/**
	 * Formats category field into HTML with search links.
	 */
	private function processCategoryLinks(string $category, string $crossLink): string {
		if (!$this->config['USE_CATEGORY']) return '';
		$categories = explode(',', str_replace('&#44;', ',', $category));
		$categories = array_map('trim', $categories);
		return implode(', ', array_map(function ($c) use ($crossLink) {
			return '<a href="'.$crossLink.$this->config['PHP_SELF'].'?mode=module&load=mod_searchcategory&c='.urlencode($c).'">'.$c.'</a>';
		}, array_filter($categories)));
	}

	/**
	 * Builds the attachment/file download bar with filename and size info.
	 */
	private function buildAttachmentBar(int $tim, string $ext, string $fname, string $imgsize, int $imgw, int $imgh, int $img_thumb): string {
		// if the filename isn't set, then use unix timestamp
		if (!isset($fname)) $fname = $tim;
		
		// truncate filename for drawing
		$truncated = strlen($fname) > 40 ? substr($fname, 0, 40).'(&hellip;)' : $fname;
		
		if ($fname !== 'SPOILERS') {
			$truncated .= $ext;
			$fname .= $ext;
		}

		// variables forjavascript
		$fnameJS = str_replace('&#039;', '\\&#039;', $fname);
		$truncatedJS = str_replace('&#039;', '\\&#039;', $truncated);
		
		// get image url
		$imageURL = $this->FileIO->getImageURL($tim.$ext, $this->board);
		
		// image info dimensions
		$imgwh_bar = ($this->config['SHOW_IMGWH'] && ($imgw || $imgh)) ? ', '.$imgw.'x'.$imgh : '';
		

		return  _T('img_filename').'<a href="'.$imageURL.'" target="_blank" rel="nofollow" onmouseover="this.textContent=\''.$fnameJS.'\';" onmouseout="this.textContent=\''.$truncatedJS.'\'"> '.$truncated.'</a> <a href="'.$imageURL.'" title="'.$fname.'" download="'.$fname.'"><div class="download"></div></a> <span class="fileProperties">('.$imgsize.$imgwh_bar.')</span>';
	}
}