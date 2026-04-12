<?php

namespace Kokonotsuba\renderers;

use Kokonotsuba\board\board;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\attachment\attachment;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\resolveThumbName;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\constructAttachment;
use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\isArchiveFile;
use function Kokonotsuba\libraries\getAttachmentUrl;
use function Puchiko\strings\truncateText;
use function Puchiko\strings\formatFileSize;
use function Puchiko\strings\sanitizeStr;

class attachmentRenderer {
	public function __construct(
		private board $board,
		private moduleEngine $moduleEngine,
		private templateEngine $templateEngine
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
		$fileBarData = $this->handleFileBar($fileData, $imageURL);
		$imageBar = $fileBarData['bar'];

		// save the file info bar before module hooks append buttons to it
		$fileInfoBar = $imageBar;

		// check if the image exists
		$imageExists = $this->checkIfAttachmentExists($fileData, $fileAttachment, $isDeleted, $isAttachmentDeleted);

		// Build image html
		$imageHtml = $this->generateImageHTML(
			$fileData['fileExtension'],
			$index,
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

		// extract the buttons that modules appended to the bar
		$attachmentButtons = substr($imageBar, strlen($fileInfoBar));

		// run attachment widget hook point (structured widget entries)
		$attachmentWidgets = [];
		$this->moduleEngine->dispatch('AttachmentWidget', [&$attachmentWidgets, &$fileData]);

		if($adminMode) {
			$this->moduleEngine->dispatch('ModerateAttachmentWidget', [&$attachmentWidgets, &$fileData]);
		}

		$attachmentButtons .= $this->buildAttachmentWidgetHtml($attachmentWidgets);

		// run attachment indicator hooks (rendered directly in the visible bar)
		if($adminMode) {
			$this->moduleEngine->dispatch('ModerateAttachmentIndicator', [&$fileInfoBar, &$fileData]);
		}

		// wrap in attachment container
		$attachmentHtml = $this->wrapAttachmentContent(
			$imageHtml, 
			$fileInfoBar,
			$fileBarData['fileSize'],
			$fileBarData['fileDimensions'],
			$attachmentButtons,
			$multipleAttachments
		);

		// return html
		return $attachmentHtml;
	}

	private function wrapAttachmentContent(
		string $imageHtml, 
		string $fileInfoBar,
		string $fileSize,
		string $fileDimensions,
		string $attachmentButtons,
		bool $multipleAttachments
	): string {
		// css classes for the attachment container
		$attachmentClasses = 'attachmentContainer';

		// append the multi-attachment css class if the post has more than 1 attachment
		if($multipleAttachments) {
			$attachmentClasses .= ' multiAttachment';
		}

		// render attachment buttons through template if there are any
		$buttonsHtml = '';
		if(!empty($attachmentButtons)) {
			$buttonsHtml = $this->templateEngine->ParseBlock('ATTACHMENT_BUTTONS', [
				'{$BUTTONS}' => $attachmentButtons,
			]);
		}

		// render attachment container
		return $this->templateEngine->ParseBlock('ATTACHMENT_CONTAINER', [
			'{$ATTACHMENT_CLASSES}' => $attachmentClasses,
			'{$FILE_INFO_BAR}' => $fileInfoBar,
			'{$FILE_SIZE}' => $fileSize,
			'{$FILE_DIMENSIONS}' => $fileDimensions,
			'{$ATTACHMENT_BUTTONS}' => $buttonsHtml,
			'{$IMAGE_HTML}' => $imageHtml,
		]);
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

	private function handleFileBar(?array $fileData, string $imageURL): array {
		// return blank if the file data is null
		if($fileData === null) {
			return ['bar' => '', 'fileSize' => '', 'fileDimensions' => ''];
		}

		// format file size
		$formattedFileSize = formatFileSize($fileData['fileSize']);

		// image dimensions
		$fileDimensions = ($this->board->getConfigValue('SHOW_IMGWH') && ($fileData['fileWidth'] || $fileData['fileHeight'])) 
			? ', ' . $fileData['fileWidth'] . 'x' . $fileData['fileHeight'] 
			: '';

		// generate file bar html 
		$imageBar = $this->buildAttachmentBar(
			$fileData['storedFileName'], 
			$fileData['fileExtension'], 
			$fileData['fileName'], 
			$imageURL);

		// return generated file bar with size info
		return [
			'bar' => $imageBar,
			'fileSize' => sanitizeStr($formattedFileSize),
			'fileDimensions' => sanitizeStr($fileDimensions),
		];
	}

	/**
	 * Generates the appropriate HTML <a><img></a> tag for a post image or thumbnail,
	 * depending on the file type, thumbnail availability, and file deletion status.
	 */
	private function generateImageHTML(string $ext,
		int $index,
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
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $index, $ext, 200, 150);
		}
		// Case: File does not exist, use placeholder image
		elseif (!$imageExists) {
			$thumbURL = $this->board->getConfigValue('STATIC_URL') . 'image/nofile.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $index, $ext, 200, 150);
		}
		// Case: Thumbnail exists and dimensions are known
		elseif ($tw && $th && !empty($thumbName)) {
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $index, $ext, $tw, $th, 'Click to show full image');
		}
		// Case: Special handling for SWF files
		elseif ($ext === "swf") {
			$thumbURL = $this->board->getConfigValue('SWF_THUMB');
			return $this->buildImageTag($imageURL, $thumbURL, 'SWF Embed', $index, $ext, 128, 128);
		}
		// Case: Handling for audio files
		elseif (!is_null($mimeType) && str_contains($mimeType, 'audio')) {
			// get audio thumbnail
			$thumbURL = $this->board->getConfigValue('AUDIO_THUMB');
			
			// then build image tag
			return $this->buildImageTag($imageURL, $thumbURL, 'Audio file', $index, $ext, 128, 128);
		} elseif (!is_null($mimeType) && isArchiveFile($ext, $mimeType)) {
			// get archive thumbnail
			$thumbURL = $this->board->getConfigValue('ARCHIVE_THUMB');

			// then build archive thumb image tag
			return $this->buildImageTag($imageURL, $thumbURL, 'Archive file', $index, $ext, 128, 128);
		}
		// Case: No thumbnail available, use generic placeholder
		elseif (!$thumbName) {
			$thumbURL = $this->board->getConfigValue('STATIC_URL') . 'image/nothumb.gif';
			return $this->buildImageTag($imageURL, $thumbURL, $imgsize, $index, $ext);
		}

		// Default fallback (shouldn't be reached under normal conditions)
		return '';
	}

	/**
	 * Builds an HTML anchor tag wrapping an image, with optional sizing and tooltip.
	 */
	private function buildImageTag(
		?string $imageURL, 
		?string $thumbURL, 
		?string $altText, 
		int $index,
		?string $extension,
		?int $width = null, 
		?int $height = null, 
		?string $title = null
	): string {
		// build width/height attribute string
		$imgDimensions = ($width && $height) ? 'width="' . $width . '" height="' . $height . '"' : '';

		return $this->templateEngine->ParseBlock('ATTACHMENT_IMAGE', [
			'{$IMAGE_URL}' => $imageURL,
			'{$THUMB_URL}' => $thumbURL,
			'{$ALT_TEXT}' => $altText,
			'{$ATTACHMENT_INDEX}' => htmlspecialchars($index),
			'{$EXTENSION}' => htmlspecialchars($extension),
			'{$IMG_DIMENSIONS}' => $imgDimensions,
			'{$IMG_TITLE}' => $title ?? '',
		]);
	}

	/**
	 * Builds the attachment/file download bar with filename and size info.
	*/
	private function buildAttachmentBar(string $tim, string $ext, string $fname, string $imageURL): string {
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
		$fnameJS = str_replace('&#039;', '\\&#039;', sanitizeStr($fname));
		$truncatedJS = str_replace('&#039;', '\\&#039;', sanitizeStr($truncated));

		return $this->templateEngine->ParseBlock('ATTACHMENT_BAR', [
			'{$IMG_FILENAME_LABEL}' => _T('img_filename'),
			'{$IMAGE_URL}' => sanitizeStr($imageURL),
			'{$FNAME_JS}' => $fnameJS,
			'{$TRUNCATED_JS}' => $truncatedJS,
			'{$TRUNCATED}' => sanitizeStr($truncated),
			'{$FNAME}' => sanitizeStr($fname),
		]);
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

	private function buildAttachmentWidgetHtml(array $widgets): string {
		$html = '';

		foreach ($widgets as $w) {
			$href   = $w['href'] ?? '';
			$action = $w['action'] ?? '';
			$label  = $w['label'] ?? '';
			$params = $w['params'] ?? [];

			$targetAttr = '';
			if (isset($params['target'])) {
				$targetAttr = ' target="' . htmlspecialchars($params['target']) . '"';
				unset($params['target']);
			}

			$paramAttrs = '';
			foreach ($params as $key => $value) {
				$paramAttrs .= ' data-param-' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$value) . '"';
			}

			$html .= '<span class="indicator indicator-' . htmlspecialchars($action) . '">'
				. '<a href="' . htmlspecialchars($href) . '"'
				. ' data-action="' . htmlspecialchars($action) . '"'
				. $targetAttr
				. $paramAttrs
				. '>' . htmlspecialchars($label) . '</a></span>';
		}

		return $html;
	}
}