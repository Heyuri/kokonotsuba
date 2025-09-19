<?php
/*
* Post renderer for Kokonotsuba!
* Handles post html output
*/

class postRenderer {
	private readonly mixed $FileIO;

	public function __construct(
		private readonly IBoard $board, 
		private readonly array $config, 
		private readonly moduleEngine $moduleEngine, 
		private readonly ?templateEngine $templateEngine,
		private array $quoteLinksFromBoard) {
			$this->FileIO = PMCLibrary::getFileIOInstance();
		}

	public function setQuoteLinks(array $quoteLinks): void {
		// update the quote links property
		$this->quoteLinksFromBoard = $quoteLinks;
	}

	public function render(
		array $post,
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
		string $crossLink = ''
	) {
		// Prepare post data
		$data = $this->preparePostData($post);

		// Define if it's the thread's OP or a reply
		$isThreadOp = $data['is_op'] ? true : false;
		$isThreadReply = !$isThreadOp;  // Inverse of $isThreadOp
		
		// Apply quote and quote link
		$data['com'] = generateQuoteLinkHtml($this->quoteLinksFromBoard, $data, $threadResno, $this->board->getConfigValue('USE_QUOTESYSTEM'), $this->board);
		$data['com'] = quote_unkfunc($data['com']);

		// Post position config
		$postPositionEnabled = $this->config['RENDER_REPLY_NUMBER'];
	
		// Process category links
		$categoryHTML = $this->processCategoryLinks($data['category'], $crossLink);

		// get file properties
		$fileData = $data['fileData'];

		// whether theres an attachment
		$hasAttachment = !empty($fileData);

		// this post is deleted
		$isDeleted = $data['open_flag'] && $adminMode;

		// this post's attachment was deleted
		$fileOnlyDeleted = $data['open_flag'] && $data['file_only_deleted'];

		// handle attachment related rendering
		[$imageBar, $imageURL, $imageHtml] = $hasAttachment ? $this->generateAttachmentHtml($fileData, $isDeleted, $fileOnlyDeleted, $adminMode) : ['', '', ''];

		// File size warning (if necessary)
		$warnBeKill = '';
		if ($this->config['STORAGE_LIMIT'] && $killSensor) {
			$warnBeKill = '<div class="warning">'._T('warn_sizelimit').'</div>';
		}

		$templateValues['{$POSTINFO_EXTRA}'] = '';

		// Admin controls hook (if admin mode is on)
		if ($adminMode) {
			$modFunc = '';
			
			if($isThreadOp) {
				$this->moduleEngine->dispatch('ThreadAdminControls', [&$modFunc, &$post]);
			} else {
				$this->moduleEngine->dispatch('ReplyAdminControls', [&$modFunc, &$post]);
			}

			$this->moduleEngine->dispatch('PostAdminControls', [&$modFunc, &$post]);
			
			$postFormExtra .= $modFunc;
		}

		if ($isThreadOp) {
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
			$this->config['NOTICE_SAGE']
		);

		// Generate the quote and reply buttons
		$quoteButton = $this->generateQuoteButton($threadResno, $data['no']);
		$replyButton = $threadMode ? $this->generateReplyButton($crossLink, $threadResno) : '';

		// Bind the template values based on whether it's a reply or OP
		if ($isThreadReply) {
			$templateValues = $this->renderReplyPost(
				$data, 
				$postPositionEnabled,
				$templateValues, 
				$threadResno,
				$nameHtml, 
				$categoryHTML,
				$quoteButton, 
				$imageBar, 
				$warnBeKill, 
				$postFormExtra,
				$imageHtml,
				$imageURL
			);
		} elseif ($isThreadOp) {
			$templateValues = $this->renderOpPost(
				$data, 
				$fileData,
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
				$warnHidePost,
				$imageHtml,
				$imageURL
			);
		}

		// Dispatch Post event, which is a hook point that will affect every post
		$this->moduleEngine->dispatch('Post', [&$templateValues, $post, $threadPosts, $this->board]);

		// Dispatch specific hook and return template
		if ($isThreadReply) {
			$this->moduleEngine->dispatch('ThreadReply', [&$templateValues, $post, $threadPosts, $isThreadReply]);
			return $this->templateEngine->ParseBlock('REPLY', $templateValues);
		} elseif ($isThreadOp) {
			$this->moduleEngine->dispatch('OpeningPost', [&$templateValues, $post, $threadPosts, $isThreadReply]);
			return $this->templateEngine->ParseBlock('OP', $templateValues);
		}

	}

	private function generateAttachmentHtml(array $fileData, bool $isDeleted, bool $fileOnlyDeleted, bool $adminMode): array {
		// add a dot (full stop) if the extension
		// (compatability)
		$fullStop = str_contains($fileData['fileExtension'], '.') ? '' : '.';

		// file name + extension
		$fullFileName = $fileData['storedFileName'] . $fullStop . $fileData['fileExtension'];

		// Get image URL
		$imageURL = $this->generateImageUrl($fileData['fileId'], 
			$fullFileName,
			false,
			$isDeleted || $fileOnlyDeleted);

		// get the thumbnail URL
		$thumbURL = $this->generateImageUrl($fileData['thumbnailFileId'],
			$fileData['thumbName'], 
			true, 
			$isDeleted || $fileOnlyDeleted);

		// build file attachment
		$fileAttachment = $this->constructAttachment($fileData['fileId'], 
			$fileData['postUid'], 
			$fileData['boardUID'], 
			$fileData['fileName'], 
			$fileData['storedFileName'], 
			$fileData['fileExtension'], 
			$fileData['fileMd5'], 
			$fileData['fileWidth'], 
			$fileData['fileHeight'], 
			$fileData['fileSize'], 
			$fileData['mimeType'], 
			$fileData['isHidden'], 
			false);


		// Attachment bar (if any)
		$imageBar = $this->handleFileBar($fileData, $imageURL);

		// check if the image exists
		$imageExists = $this->checkIfAttachmentExists($fullFileName, $fileAttachment, $isDeleted, $fileOnlyDeleted);

		// Build image html
		$imageHtml = $this->generateImageHTML($fileData['fileExtension'], 
			$fileData['thumbnailWidth'], 
			$fileData['thumbnailHeight'], 
			$fileData['fileSize'],
			$fileData['thumbName'],
			$thumbURL,
			$imageURL,
			$imageExists,
			(!$adminMode && $fileOnlyDeleted));
	
		// return html
		return [$imageBar, $imageURL, $imageHtml];
	}

	private function constructAttachment(int $fileId,
    	int $postUid,
    	string $boardUID,
    	string $fileName,
    	string $storedFileName,
    	string $fileExtension,
    	string $fileMd5,
    	int $fileWidth,
    	int $fileHeight,
    	string|int $fileSize,
    	string $mimeType,
    	bool $isHidden,
    	bool $isThumb
	): attachment {
		// create fileEntry instance
		$fileEntry = new fileEntry;

		// hydrate the object
		$fileEntry->hydrateFileEntry(
			$fileId,
			$postUid,
			$boardUID,
			$fileName,
			$storedFileName,
			$fileExtension,
			$fileMd5,
			$fileWidth,
			$fileHeight,
			$fileSize,
			$mimeType,
			$isHidden,
			$isThumb
		);

		// construct attachment
		$attachment = new attachment($fileEntry, $this->board);

		// return attachment
		return $attachment;
	}

	private function checkIfAttachmentExists(string $fullFileName, 
		attachment $fileAttachment, 
		bool $isDeleted,
		bool $fileOnlyDeleted,
	): bool {
		// if its being served live
		if($isDeleted || $fileOnlyDeleted) {
			// get the path
			$attachmentPath = $fileAttachment->getPath();

			// check if it exists
			$imageExists = file_exists($attachmentPath);
		} 
		// if its being served through the web server like normal then use FileIO to check if it exists
		else {
			$imageExists = $this->FileIO->imageExists($fullFileName, $this->board);
		}

		// return result
		return $imageExists;
	}

	private function getFilePropertiesFromData(array $data): ?array {
		// thumbnail extension
		$thumbnailExtension = $this->board->getConfigValue('THUMB_SETTING.Format');

		// init post uid
		$postUid = $data['post_uid'];

		// init board uid
		$boardUID = $data['boardUID'];

		// if it has an attachment from the files table then render it accordingly
		if(!empty($data['file_id'])) {
			// md5 file hash
			$fileMd5 = $data['file_md5'];

			// file name on disk
			$storedFileName = $data['stored_filename'];
			
			// file extension
			$fileExtension = $data['file_ext'];

			// file name
			$fileName = $data['file_name'];

			// file size
			$fileSize = (string) $data['file_size'] . ' KB';

			// file width
			$fileWidth = $data['file_width'];

			// file height
			$fileHeight = $data['file_height'];

			// thumbnail width
			$thumbnailWidth = $data['thumb_file_width'] ?? 0;

			// thumbnail height
			$thumbnailHeight = $data['thumb_file_height'] ?? 0;

			// thumbnail file name
			$thumbnailStoredFileName = $data['thumb_stored_filename'] ?? '';

			// Get thumbnail name
			$thumbName = $thumbnailStoredFileName . '.' . $thumbnailExtension;

			// file id
			$fileId = $data['file_id'] ?? 0;

			// thumbnail file id
			$thumbnailFileId = $data['thumb_file_id'] ?? 0;

			// is hidden
			$isHidden = $data['main_is_hidden'] ?? false;

			// this is using the new and improved file system
			$isLegacy = false;
		} 
		// Legacy post row file system + values
		else if(!empty($data['ext'])) {
			// md5 file hash
			$fileMd5 = $data['md5chksum'];

			// file name on disk
			$storedFileName = $data['tim'];
			
			// file extension
			$fileExtension = $data['ext'];

			// file name
			$fileName = $data['fname'];

			// file size
			$fileSize = $data['imgsize'];

			// file width
			$fileWidth = $data['imgw'];

			// file height
			$fileHeight = $data['imgh'];

			// thumbnail width
			$thumbnailWidth = $data['tw'] ?? 0;

			// thumbnail height
			$thumbnailHeight = $data['th'] ?? 0;

			// thumbnail file name
			$thumbnailStoredFileName = $data['tim'] . 's';

			// Get thumbnail name
			$thumbName = $this->FileIO->resolveThumbName($storedFileName, $this->board);

			// is hidden
			$isHidden = false;

			// file id
			$fileId = 0;

			// thumb id
			$thumbnailFileId = 0;

			// this is using the legacy post file stuff
			$isLegacy = true;
		} 
		// there is no attachments on this post
		else {
			// return null
			return null;
		}

		// then return the file data,
		// either above outcomes will be rendered with the same array keys
		return [
			'storedFileName' => $storedFileName,
			'fileExtension' => $fileExtension,
			'fileName' => $fileName,
			'fileSize' => $fileSize,
			'fileWidth' => $fileWidth,
			'fileHeight' => $fileHeight,
			'fileMd5' => $fileMd5,
			'thumbnailWidth' => $thumbnailWidth,
			'thumbnailHeight' => $thumbnailHeight,
			'thumbnailFileId' => $thumbnailFileId,
			'thumbnailStoredFileName' => $thumbnailStoredFileName,
			'thumbnailExtension' => $thumbnailExtension,
			'thumbName' => $thumbName,
			'fileId' => $fileId,
			'isHidden' => $isHidden,
			'mimeType' => '',// temp blanl
			'isLegacy' => $isLegacy,
			'postUid' => $postUid,
			'boardUID' => $boardUID,
		];
	}
	
	private function handleFileBar(?array $fileData, string $imageURL): string {
		// return blank if the file data is null
		if($fileData === null) {
			return '';
		}

		// generate file bar html 
		$imageBar = $this->buildAttachmentBar(
			$fileData['storedFileName'], 
			$fileData['fileExtension'], 
			$fileData['fileName'], 
			$fileData['fileSize'], 
			$fileData['fileWidth'], 
			$fileData['fileHeight'], 
			$imageURL);

		// return generated file bar
		return $imageBar;
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
		string $postFormExtra,
		string $imageHtml
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
				$imageHtml,
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
		?array $fileData,
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
		string $imageHtml,
		string $imageURL
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
				$imageBar ?? '',
				$imageHtml ?? '',
				$fileData['fileName'] ?? '',
				$fileData['fileExtension'] ?? '',
				$fileData['fileSize'] ?? '',
				$fileData['fileWidth'] ?? 0,
				$fileData['fileHeight'] ?? 0,
				$imageURL ?? '',
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
		$tim = $post['tim'] ?? '';
		$open_flag = $post['open_flag'] ?? 0;
		$file_only_deleted = $post['file_only_deleted'] ?? 0;
		$status = new FlagHelper($post['status']);
		
	
		// Mailto formatting
		if ($this->config['CLEAR_SAGE']) {
			$email = preg_replace('/^sage( *)/i', '', $email);
		}
		if ($this->config['ALLOW_NONAME'] == 2 && $email) {
			$now = "<a href=\"mailto:$email\">$now</a>";
		}

		// get the file data
		$fileData = $this->getFilePropertiesFromData($post);

		// Return everything needed
		return [
			'email' => $email,
			'name' => $name,
			'open_flag' => $open_flag,
			'file_only_deleted' => $file_only_deleted,
			'tripcode' => $tripcode,
			'secure_tripcode' => $secure_tripcode,
			'capcode' => $capcode,
			'now' => $now,
			'com' => $com,
			'no' => $post['no'],
			'is_op' => $post['is_op'],
			'post_position' => $post['post_position'],
			'sub' => $post['sub'],
			'status' => $status,
			'tim' => $tim,
			'category' => $post['category'],
			'post_uid' => $post['post_uid'],
			'boardUID' => $post['boardUID'],
			// file
			'fileData' => $fileData
		];
	}

	/**
	 * Generates the appropriate HTML <a><img></a> tag for a post image or thumbnail,
	 * depending on the file type, thumbnail availability, and file deletion status.
	 */
	private function generateImageHTML(string $ext,   
		int $tw, 
		int $th, 
		string  $imgsize, 
		string $thumbName, 
		string $thumbURL,
		string $imageURL,
		bool $imageExists,
		bool $fileDeleted): string {

		// Case: File has been deleted, use placeholder image
		if ($fileDeleted) {
			$thumbURL = $this->config['STATIC_URL'] . 'image/filedeleted.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize);
		}
		// Case: File does not exist, use placeholder image
		elseif (!$imageExists) {
			$thumbURL = $this->config['STATIC_URL'] . 'image/nofile.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize);
		}
		// Case: Thumbnail exists and dimensions are known
		elseif ($tw && $th && $thumbName) {
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $tw, $th, 'Click to show full image');
		}
		// Case: Special handling for SWF files
		elseif ($ext === ".swf" || $ext === "swf") {
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
			return '<a href="'.$crossLink.$this->config['LIVE_INDEX_FILE'].'?mode=module&load=mod_searchcategory&c='.urlencode($c).'">'.$c.'</a>';
		}, array_filter($categories)));
	}

	/**
	 * Builds the attachment/file download bar with filename and size info.
	*/
	private function buildAttachmentBar(string $tim, string $ext, string $fname, string $imgsize, int $imgw, int $imgh, string $imageURL): string {
		// add a dot (full stop) if the extension
		// (compatability)
		$fullStop = str_contains($ext, '.') ? '' : '.';
		// if the filename isn't set, then use unix timestamp
		if (!isset($fname)) $fname = $tim;

		// Max file name length before truncating
		$maxLength = 40;

		// truncate the file name as per maxLength
		$truncated = truncateText($fname, $maxLength);

		$truncated .= $fullStop . $ext;
		$fname .= $fullStop . $ext;

		// Escape single quotes for JavaScript
		$fnameJS = str_replace('&#039;', '\\&#039;', $fname);
		$truncatedJS = str_replace('&#039;', '\\&#039;', $truncated);

		// Image info dimensions
		$imgwh_bar = ($this->config['SHOW_IMGWH'] && ($imgw || $imgh)) ? ', ' . $imgw . 'x' . $imgh : '';

		return _T('img_filename') . 
			'<a href="' . htmlspecialchars($imageURL) . '" target="_blank" rel="nofollow" onmouseover="this.textContent=\'' . htmlspecialchars($fnameJS) . '\';" onmouseout="this.textContent=\'' . htmlspecialchars($truncatedJS) . '\'">' . 
   			htmlspecialchars($truncated) . 
			'</a> <a href="' . htmlspecialchars($imageURL) . '" title="' . htmlspecialchars($fname) . '" download="' . htmlspecialchars($fname) . '">
			<div class="download"></div></a> 
			<span class="fileProperties">(' . htmlspecialchars($imgsize) . htmlspecialchars($imgwh_bar) . ')</span>';
	}

	private function generateImageUrl(int $fileId, 
		string $fullFileName,
		bool $isThumb, 
		bool $serveThroughPHP): string {
		// url of the image to be served
		$imageURL = '';

		// serve through a module hook point with Content-Type http header
		if($serveThroughPHP) {
			// dipatch hook point
			// primarily just for the imageServer module
			$this->moduleEngine->dispatch('ImageUrl', [&$imageURL, $fileId, $isThumb]);
		} 
		// otherwise just generate the regular URL to the image on the server
		else {
			// get the image url directly to the image file
			$imageURL = $this->FileIO->getImageURL($fullFileName, $this->board);
		}

		// return generated image url
		return $imageURL;
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
		$replyUrl = $crossLink . $this->config['LIVE_INDEX_FILE'] . '?res=' . $threadResno;
		// Return the reply button HTML
		$replyButton = '[<a href="' . $replyUrl . '">' . _T('reply_btn') . '</a>]';

		return $replyButton;
	}
}
