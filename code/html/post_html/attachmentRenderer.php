<?php

class attachmentRenderer {
	public function __construct(
		private mixed $FileIO,
		private board $board,
		private moduleEngine $moduleEngine
	) {}

	public function generateAttachmentHtml(array $fileData, bool $isDeleted, bool $fileOnlyDeleted, bool $adminMode): array {
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
		$thumbURL = $this->generateImageUrl($fileData['fileId'],
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
			$fileData['thumbnailWidth'],
			$fileData['thumbnailHeight'],
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
		int $thumbnailFileWidth,
		int	$thumbnailFileHeight,
		string|int $fileSize,
		string $mimeType,
		bool $isHidden
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
			$thumbnailFileWidth,
			$thumbnailFileHeight,
			$fileSize,
			$mimeType,
			$isHidden
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
			$thumbURL = $this->board->getConfigValue('STATIC_URL') . 'image/filedeleted.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize);
		}
		// Case: File does not exist, use placeholder image
		elseif (!$imageExists) {
			$thumbURL = $this->board->getConfigValue('STATIC_URL') . 'image/nofile.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize);
		}
		// Case: Thumbnail exists and dimensions are known
		elseif ($tw && $th && $thumbName) {
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $tw, $th, 'Click to show full image');
		}
		// Case: Special handling for SWF files
		elseif ($ext === ".swf" || $ext === "swf") {
			$thumbURL = $this->board->getConfigValue('SWF_THUMB');
			return $this->buildImageTag($imageURL, $thumbURL, 'SWF Embed');
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
}