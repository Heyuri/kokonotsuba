<?php

class attachmentRenderer {
	public function __construct(
		private board $board,
		private moduleEngine $moduleEngine
	) {}

	public function generateAttachmentHtml(
		array $fileData, 
		bool $isDeleted, 
		bool $adminMode, 
		int $index = 0,
		bool $multipleAttachments = false,
	): string {
		// check whether only the attachment is currently deleted
		$isAttachmentDeleted = $fileData['isDeleted'];

		// thumb filename + extension
		$thumbName = resolveThumbName($fileData);

		// Get image URL
		$imageURL = $this->generateImageUrl( 
			$fileData,
			false,
			$isDeleted || $isAttachmentDeleted);

		// get the thumbnail URL
		$thumbURL = $this->generateImageUrl(
			$fileData, 
			true, 
			$isDeleted || $isAttachmentDeleted);

		// build file attachment
		$fileAttachment = constructAttachment($fileData['fileId'], 
			$fileData['postUid'], 
			$fileData['boardUID'], 
			$fileData['fileName'], 
			$fileData['storedFileName'], 
			$fileData['fileExtension'], 
			$fileData['fileMd5'], 
			$fileData['fileWidth'], 
			$fileData['fileHeight'], 
			$fileData['thumbWidth'],
			$fileData['thumbHeight'],
			$fileData['fileSize'], 
			$fileData['mimeType'], 
			$fileData['isHidden'], 
			$fileData['isDeleted'],
			$fileData['timestampAdded'],
			false);

		// Attachment bar (if any)
		$imageBar = $this->handleFileBar($fileData, $imageURL);

		// check if the image exists
		$imageExists = $this->checkIfAttachmentExists($fileData, $fileAttachment, $isDeleted, $isAttachmentDeleted);

		// Build image html
		$imageHtml = $this->generateImageHTML(
			$fileData['fileExtension'], 
			$fileData['mimeType'],
			$fileData['thumbWidth'], 
			$fileData['thumbHeight'], 
			$fileData['fileSize'],
			$thumbName,
			$thumbURL,
			$imageURL,
			$imageExists,
			(!$adminMode && $isAttachmentDeleted));
			
		// run attachment hook point
		$this->moduleEngine->dispatch('Attachment', [&$imageBar, &$imageHtml, &$imageURL, &$fileData]);

		// run moderation attachment hook point
		if($adminMode) {
			$this->moduleEngine->dispatch('ModerateAttachment', [&$imageBar, &$imageHtml, &$imageURL, &$fileData]);
		}

		// wrap in attachment container
		$attachmentHtml = $this->wrapAttachmentContent(
			$imageHtml, 
			$imageBar, 
			$index, 
			$multipleAttachments
		);

		// return html
		return $attachmentHtml;
	}

	private function wrapAttachmentContent(
		string $imageHtml, 
		string $imageBar, 
		int $index, 
		bool $multipleAttachments
	): string {
		// init attachment html
		$attachmentHtml = '';

		// css classes for the attachment container
		$attachmentClasses = 'attachmentContainer';

		// append the multi-attachment css class if the post has more than 1 attachment
		if($multipleAttachments) {
			$attachmentClasses .= ' multiAttachment';
		}

		// begin container wrap
		// data-attachment-index is so js knows whether its the first, second, and so on, attachment of the post
		$attachmentHtml .= '<div class="' . $attachmentClasses . '" data-attachment-index="' . htmlspecialchars($index) . '">';

		// append attachment info html
		$attachmentHtml .= '<div class="filesize">' . $imageBar . '</div>';

		// append main attachment html (image/thumbnail itself)
		$attachmentHtml .= $imageHtml;

		// end container wrap
		$attachmentHtml .= '</div>';

		// now, return the attachment container/html
		return $attachmentHtml;
	}

	private function checkIfAttachmentExists(
		array $attachmentData, 
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
		// if its being served through the web server like normal then use function to check if it exists
		else {
			$imageExists = attachmentFileExists($attachmentData);
		}

		// return result
		return $imageExists;
	}

	private function handleFileBar(?array $fileData, string $imageURL): string {
		// return blank if the file data is null
		if($fileData === null) {
			return '';
		}

		// format file size
		$formattedFileSize = formatFileSize($fileData['fileSize']);

		// generate file bar html 
		$imageBar = $this->buildAttachmentBar(
			$fileData['storedFileName'], 
			$fileData['fileExtension'], 
			$fileData['fileName'], 
			$formattedFileSize, 
			$fileData['fileWidth'], 
			$fileData['fileHeight'], 
			$imageURL);

		// return generated file bar
		return $imageBar;
	}

	/**
	 * Generates the appropriate HTML <a><img></a> tag for a post image or thumbnail,
	 * depending on the file type, thumbnail availability, and file deletion status.
	 */
	private function generateImageHTML(string $ext,
		?string $mimeType,   
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
			$thumbURL = $this->board->getConfigValue('STATIC_URL') . 'image/filedeleted.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, 200, 150);
		}
		// Case: File does not exist, use placeholder image
		elseif (!$imageExists) {
			$thumbURL = $this->board->getConfigValue('STATIC_URL') . 'image/nofile.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, 200, 150);
		}
		// Case: Thumbnail exists and dimensions are known
		elseif ($tw && $th && !empty($thumbName)) {
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $tw, $th, 'Click to show full image');
		}
		// Case: Special handling for SWF files
		elseif ($ext === "swf") {
			$thumbURL = $this->board->getConfigValue('SWF_THUMB');
			return $this->buildImageTag($imageURL, $thumbURL, 'SWF Embed', 128, 128);
		}
		// Case: Handling for audio files
		elseif (!is_null($mimeType) && str_contains($mimeType, 'audio')) {
			// get audio thumbnail
			$thumbURL = $this->board->getConfigValue('AUDIO_THUMB');
			
			// then build image tag
			return $this->buildImageTag($imageURL, $thumbURL, 'Audio file', 128, 128);
		} elseif (!is_null($mimeType) && isArchiveFile($ext, $mimeType)) {
			// get archive thumbnail
			$thumbURL = $this->board->getConfigValue('ARCHIVE_THUMB');

			// then build archive thumb image tag
			return $this->buildImageTag($imageURL, $thumbURL, 'Archive file', 128, 128);
		}
		// Case: No thumbnail available, use generic placeholder
		elseif (!$thumbName) {
			$thumbURL = $this->board->getConfigValue('STATIC_URL') . 'image/nothumb.gif';
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
		$imgwh_bar = ($this->board->getConfigValue('SHOW_IMGWH') && ($imgw || $imgh)) ? ', ' . $imgw . 'x' . $imgh : '';

		return _T('img_filename') . 
			'<a href="' . htmlspecialchars($imageURL) . '" target="_blank" rel="nofollow" onmouseover="this.textContent=\'' . htmlspecialchars($fnameJS) . '\';" onmouseout="this.textContent=\'' . htmlspecialchars($truncatedJS) . '\'">' . 
   			htmlspecialchars($truncated) . 
			'</a> <a href="' . htmlspecialchars($imageURL) . '" title="' . htmlspecialchars($fname) . '" download="' . htmlspecialchars($fname) . '">
			<div class="download"></div></a> 
			<span class="fileProperties">(' . htmlspecialchars($imgsize) . htmlspecialchars($imgwh_bar) . ')</span>';
	}

	public function generateImageUrl(
		array $attachment,
		bool $isThumb,
		bool $serveThroughPHP): string {
		// return empty string if attachment is empty
		if(empty($attachment)) {
			return '';
		}

		// url of the image to be served
		$imageURL = '';

		// serve through a module hook point with Content-Type http header
		if($serveThroughPHP) {
			// dipatch hook point
			// primarily just for the imageServer module
			$this->moduleEngine->dispatch('ImageUrl', [&$imageURL, $attachment['fileId'], $isThumb]);
		} 
		// otherwise just generate the regular URL to the image on the server
		else {
			// get the url directly to the image file  
			$imageURL = getAttachmentUrl($attachment, $isThumb);
		}

		// return generated image url
		return $imageURL;
	}
}