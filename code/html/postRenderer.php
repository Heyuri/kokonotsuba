<?php
/*
* Post renderer for Kokonotsuba!
* Handles post html output
*/

class postRenderer {
	private readonly IBoard $board;
	private readonly array $config;
	private readonly globalHTML $globalHTML;
	private readonly moduleEngine $moduleEngine;
	private readonly templateEngine $templateEngine;
	private readonly array $quoteLinksFromBoard;
	private readonly mixed $FileIO;

	public function __construct(IBoard $board, 
		array $config, 
		globalHTML $globalHTML, 
		moduleEngine $moduleEngine, 
		templateEngine $templateEngine,
		array $quoteLinksFromBoard) {
		$this->board = $board;
		$this->config = $config;
		$this->globalHTML = $globalHTML;
		$this->moduleEngine = $moduleEngine;
		$this->templateEngine = $templateEngine;
		$this->quoteLinksFromBoard = $quoteLinksFromBoard;

		$this->FileIO = PMCLibrary::getFileIOInstance();
	}

	public function render(array $post,
		array &$templateValues,
		int $threadResno,
		bool $killSensor,
		array $threadPosts,
		bool $adminMode,
		string $postFormExtra,
		string $warnBeKill,
		string $warnOld,
		string $warnHidePost,
		string $warnEndReply,
		int $replyCount,
		bool $threadMode = true,
		string $crossLink = '',
	) {
		// Prepare post data
		$data = $this->preparePostData($post);

		// Define if it's the thread's OP or a reply
		$isThreadOp = $data['is_op'] ? true : false;
		$isThreadReply = !$isThreadOp;  // Inverse of $isThreadOp

		// Apply quote and quote link
		$data['com'] = $this->globalHTML->quote_link($this->quoteLinksFromBoard, $data, $threadResno);
		$data['com'] = $this->globalHTML->quote_unkfunc($data['com']);

		// Post position config
		$postPositionEnabled = $this->config['RENDER_REPLY_NUMBER'];
		
		// Process category links
		$categoryHTML = $this->processCategoryLinks($data['category'], $crossLink);
	
		// Attachment bar (if any)
		$imageBar = ($data['ext']) ? $this->buildAttachmentBar($data['tim'], $data['ext'], $data['fname'], $data['imgsize'], $data['imgw'], $data['imgh'], $data['tw'], $data['th'], '') : '';
	
		// File size warning (if necessary)
		$warnBeKill = '';
		if ($this->config['STORAGE_LIMIT'] && $killSensor) {
			$warnBeKill = '<div class="warning">'._T('warn_sizelimit').'</div>';
		}

		$templateValues['{$POSTINFO_EXTRA}'] = '';

		// Admin controls hook (if admin mode is on)
		if ($adminMode) {
			$modFunc = '';
			$this->moduleEngine->useModuleMethods('AdminList', array(&$modFunc, $post, $isThreadReply));
			$postFormExtra .= $modFunc;
		}

		if($isThreadOp) {
			$maxAgeLimit = $this->config['MAX_AGE_TIME'];
			$postUnixTimestamp = is_numeric($post['root']) ? $post['root'] : strtotime($post['root']);
			if ($maxAgeLimit && $_SERVER['REQUEST_TIME'] - $postUnixTimestamp > ($maxAgeLimit * 60 * 60)) {
				$warnOld .= "<div class='warning'>"._T('warn_oldthread')."</div>";
			}
		}

	
		// Handle name/trip/capcode HTML generation
		$nameHtml = generatePostNameHtml(
			$this->config['staffCapcodes'],
			$this->config['CAPCODES'],
			$data['name'],
			$data['tripcode'],
			$data['secure_tripcode'],
			$data['capcode'],
			$data['email'],
			$this->config['CLEAR_SAGE']
		);
	
		// Generate the quote and reply buttons
		$quoteButton = $this->generateQuoteButton($threadResno, $data['no']);
		$replyButton = $threadMode ? $this->generateReplyButton($crossLink, $threadResno) : '';

		// Bind the template values based on whether it's a reply or OP
		if ($isThreadReply) {
			$templateValues = $this->renderReplyPost($data, 
			 $postPositionEnabled,
			 $templateValues, 
			 $threadResno,
			 $nameHtml, 
			 $categoryHTML, 
			 $quoteButton, 
			 $imageBar, 
			 $warnBeKill, 
			 $postFormExtra);

			// Module methods (if thread reply)
			$this->moduleEngine->useModuleMethods('ThreadReply', array(&$templateValues, $post, $threadPosts, $isThreadReply));
	
			// parse template values to REPLY template block for a post reply
			return $this->templateEngine->ParseBlock('REPLY', $templateValues);
		} elseif ($isThreadOp) {
			$templateValues = $this->renderOpPost($data, 
			 $templateValues, 
			 $nameHtml, 
			 $categoryHTML, 
			 $quoteButton, 
			 $replyButton, 
			 $imageBar, 
			 $postFormExtra, 
			 $replyCount, 
			 $warnOld, 
			 $warnBeKill, 
			 $warnEndReply, 
			 $warnHidePost);

			// Module methods (thread OP)
			$this->moduleEngine->useModuleMethods('ThreadPost', array(&$templateValues, $post, $threadPosts, $isThreadReply));
		
			// parse template values to THREAD template block to a thread OP post
			return $this->templateEngine->ParseBlock('OP', $templateValues);
		}
		
	}
	
	/**
	 * Renders a reply post by merging template values with reply-specific data.
	 * 
	 * Prepares the data structure for a non-OP post (a reply) using the reply template binding function.
	 * Adds name, quote, image, and comment information to the template.
	 */
	private function renderReplyPost(array $data,
		bool $postPositionEnabled,
		array $templateValues,
		int $threadResno,
		string $nameHtml,
		string $categoryHTML,
		string $quoteButton,
		string $imageBar,
		string $warnBeKill,
		string $postFormExtra
	): array {
		return array_merge(
			$templateValues,
			bindReplyValuesToTemplate(
				$this->board,
				$this->config,
				$data['post_uid'],
				$data['no'],
				$threadResno,
				$postPositionEnabled,
				$data['post_position'],
				$data['sub'],
				$nameHtml,
				$data['now'],
				$categoryHTML,
				$quoteButton,
				$imageBar,
				$data['imgsrc'] ?? '',
				$warnBeKill,
				$data['com'],
				$postFormExtra
			)
		);
	}

	/**
	 * Renders an original post (OP) by merging template values with thread-starting post data.
	 * 
	 * This handles the layout and logic specific to OPs including reply count, warnings,
	 * thread navigation, and extended image information.
	 */
	private function renderOpPost(array $data,
		array $templateValues,
		string $nameHtml,
		string $categoryHTML,
		string $quoteButton,
		string $replyButton,
		string $imageBar,
		string $postFormExtra,
		int $replyCount,
		string $warnOld,
		string $warnBeKill,
		string $warnEndReply,
		string $warnHidePost,
	): array {
		return array_merge(
			$templateValues,
			bindOPValuesToTemplate(
				$this->board,
				$this->config,
				$data['post_uid'],
				$data['no'],
				$data['sub'],
				$nameHtml,
				$data['now'],
				$categoryHTML,
				$quoteButton,
				$replyButton,
				$imageBar,
				$data['imgsrc'] ?? '',
				$data['fname'],
				$data['ext'],
				$data['imgsize'],
				$data['imgw'],
				$data['imgh'],
				$data['imageURL'] ?? '',
				$replyCount,
				$warnOld,
				$warnBeKill,
				$warnEndReply,
				$warnHidePost,
				$data['com'],
				$postFormExtra
			)
		);
	}


	 /**
	 * Sanitize and format post data for rendering using associative arrays.
	 */
	private function preparePostData(array $post): array {
		// Basic fields
		$email = isset($post['email']) ? trim($post['email']) : '';
		$name = $post['name'] ?? '';
		$tripcode = $post['tripcode'] ?? '';
		$secure_tripcode = $post['secure_tripcode'] ?? '';
		$capcode = $post['capcode'] ?? '';
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
		$status = new FlagHelper($post['status']);
	
		// Mailto formatting
		if ($this->config['CLEAR_SAGE']) {
			$email = preg_replace('/^sage( *)/i', '', $email);
		}
		if ($this->config['ALLOW_NONAME'] == 2 && $email) {
			$now = "<a href=\"mailto:$email\">$now</a>";
		}
	
		
		// Get full image URL
		$imageURL = $this->FileIO->getImageURL($tim . $ext, $this->board);
		
		// Build image html
		$imgsrc = $this->generateImageHTML($ext, $tim, $status, $tw, $th, $imgsize, $imageURL);

	
		// Return everything needed
		return [
			'email' => $email,
			'name' => $name,
			'tripcode' => $tripcode,
			'secure_tripcode' => $secure_tripcode,
			'capcode' => $capcode,
			'now' => $now,
			'com' => $com,
			'no' => $post['no'],
			'is_op' => $post['is_op'],
			'post_position' => $post['post_position'],
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
	 * Generates the appropriate HTML <a><img></a> tag for a post image or thumbnail,
	 * depending on the file type, thumbnail availability, and file deletion status.
	 */
	private function generateImageHTML($ext, $tim, $status, $tw, $th, $imgsize, $imageURL): string {
		// If there's no file extension, no image to display
		if (!$ext) {
			return '';
		}

		// Get thumbnail name
		$thumbName = $this->FileIO->resolveThumbName($tim, $this->board);

		// Case: File has been deleted, use placeholder image
		if ($status->value('fileDeleted')) {
			$thumbURL = $this->config['STATIC_URL'] . 'image/nofile.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize);
		}
		// Case: Thumbnail exists and dimensions are known
		elseif ($tw && $th && $thumbName) {
			$thumbURL = $this->FileIO->getImageURL($thumbName, $this->board);
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $tw, $th, 'Click to show full image');
		}
		// Case: Special handling for SWF files
		elseif ($ext === ".swf") {
			$thumbURL = $this->config['SWF_THUMB'];
			return $this->buildImageTag($imageURL, $thumbURL, 'SWF Embed');
		}
		// Case: No thumbnail available, use generic placeholder
		elseif (!$thumbName) {
			$thumbURL = $this->config['STATIC_URL'] . 'image/nothumb.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize);
		}

		// Default fallback (shouldn't be reached under normal conditions)
		return '';
	}

	/**
	 * Builds an HTML anchor tag wrapping an image, with optional sizing and tooltip.
	 */
	private function buildImageTag($imageURL, $thumbURL, $altText, $width = null, $height = null, $title = null): string {
		// Start building the <img> tag
		$imgTag = '<img src="' . $thumbURL . '" class="postimg" alt="' . $altText . '"';

		// Add optional width and height
		if ($width && $height) {
			$imgTag .= ' width="' . $width . '" height="' . $height . '"';
		}

		// Add optional title (used as tooltip)
		if ($title) {
			$imgTag .= ' title="' . $title . '"';
		}

		$imgTag .= '>';

		// Wrap the image in a clickable link to the full image
		return '<a href="' . $imageURL . '" target="_blank" rel="nofollow">' . $imgTag . '</a>';
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
	private function buildAttachmentBar(int $tim, string $ext, string $fname, string $imgsize, int $imgw, int $imgh): string {
		// if the filename isn't set, then use unix timestamp
		if (!isset($fname)) $fname = $tim;
	
		// Use mbstring functions to safely handle multi-byte characters
		$maxLength = 40;
		if (mb_strlen($fname, 'UTF-8') > $maxLength) {
			$truncated = mb_substr($fname, 0, $maxLength, 'UTF-8') . '(' . html_entity_decode('&hellip;', ENT_QUOTES, 'UTF-8') . ')';
		} else {
			$truncated = $fname;
		}

		$truncated .= $ext;
		$fname .= $ext;

		// Escape single quotes for JavaScript
		$fnameJS = str_replace('&#039;', '\\&#039;', $fname);
		$truncatedJS = str_replace('&#039;', '\\&#039;', $truncated);

		// Get image URL
		$imageURL = $this->FileIO->getImageURL($tim . $ext, $this->board);

		// Image info dimensions
		$imgwh_bar = ($this->config['SHOW_IMGWH'] && ($imgw || $imgh)) ? ', ' . $imgw . 'x' . $imgh : '';

		return _T('img_filename') . 
			'<a href="' . htmlspecialchars($imageURL) . '" target="_blank" rel="nofollow" onmouseover="this.textContent=\'' . htmlspecialchars($fnameJS) . '\';" onmouseout="this.textContent=\'' . htmlspecialchars($truncatedJS) . '\'">' . 
   			htmlspecialchars($truncated) . 
			'</a> <a href="' . htmlspecialchars($imageURL) . '" title="' . htmlspecialchars($fname) . '" download="' . htmlspecialchars($fname) . '">
			<div class="download"></div></a> 
			<span class="fileProperties">(' . htmlspecialchars($imgsize) . htmlspecialchars($imgwh_bar) . ')</span>';
	}


	/**
	 * Generates a quote button that links to a specific post.
	 * 
	 * If the quote system is enabled, this returns a clickable anchor tag
	 * pointing to the thread/post. Otherwise, it just returns the post number
	 * as plain text.
	 */
	private function generateQuoteButton(int $threadResno, int $no): string {
		if ($this->config['USE_QUOTESYSTEM']) {
			// Build the URL to the specific post
			$threadUrl = $this->board->getBoardThreadURL($threadResno, $no, true);
			// Create the clickable quote button
			$quoteButton = '<a href="'.$threadUrl.'" class="qu" title="Quote">'.$no.'</a>';
		} else {
			// Fallback: show plain post number
			$quoteButton = $no;
		}

		return $quoteButton;
	}

	/**
	 * Generates a reply button linking to the reply form for the thread.
	 * 
	 * Builds a URL based on the thread number and returns it as a clickable
	 * [Reply] link.
	 */
	private function generateReplyButton(string $crossLink, int $threadResno): string {
		// Build the URL to the thread's reply form
		$replyUrl = $crossLink . $this->config['PHP_SELF'] . '?res=' . $threadResno;
		// Return the reply button HTML
		$replyButton = '[<a href="' . $replyUrl . '">' . _T('reply_btn') . '</a>]';

		return $replyButton;
	}

}
