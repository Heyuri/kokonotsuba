<?php
// animated gif module made for kokonotsuba by deadking
// "forked" from the siokara mod for pixmicat

namespace Kokonotsuba\Modules\animatedGif;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\IndicatorTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentsAfterInsertListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeHtmlTrait;
use Kokonotsuba\module_classes\traits\listeners\PostFormFileListenerTrait;
use RuntimeException;

use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use AttachmentsAfterInsertListenerTrait;
	use AttachmentListenerTrait;
	use IncludeHtmlTrait;
	use IndicatorTrait;
	use PostFormFileListenerTrait;

	public function getName(): string {
		return 'Kokonotsuba Animated GIF';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->listenAttachmentsAfterInsert('onAttachmentsAfterInsert');

		$this->listenAttachment('onRenderAttachment');

		$this->listenPostFormFile('onRenderPostFormFile');

		// size limit data in <head> for JS to read
		$animatedGifSizeLimit = $this->getConfig('MAX_SIZE_FOR_ANIMATED_GIF', 2000);
		$this->registerHeaderHtml('<template id="anigifData" data-size-limit="' . sanitizeStr($animatedGifSizeLimit) . '"></template>');
	}

	private function onRenderPostFormFile(string &$file): void {
		// noscript fallback checkbox for no-JS users
		$file .= '<noscript><div id="anigifContainer"><label id="anigifLabel" title="Makes GIF thumbnails animated"><input type="checkbox" name="anigif" id="anigif" value="on">Animated GIF</label></div></noscript>';
	}

	private function onAttachmentsAfterInsert(?array &$attachments): void {
		// return early if no attachments
		if(empty($attachments)) {
			return;
		}

		$this->handlePostAnimatedGif($attachments);
	}

	private function handlePostAnimatedGif(array &$attachments): void {
		// whether anigif was toggled
		$anigifRequested = $this->moduleContext->request->hasParameter('anigif', 'POST');
		
		// if toggled then loop through attachments and toggle each gif to be animated
		// non-GIFs are skipped
		if ($anigifRequested) {
			$this->animateAllAttachments($attachments);
		}
	}

	private function animateAllAttachments(array &$attachments): void {
		// loop over all attachments and mark them as animated if their extension + mime type is an animated gif
		foreach($attachments as &$att) {
			// file extension
			$fileExtension = $att['fileExtension'];
			
			// mime type
			$mimeType = $att['mimeType'];

			// Don't accept it if its not even a GIF
			if($mimeType !== 'image/gif' && $fileExtension === 'gif') {
				return;
			}

			// get the file id to pass to animate gif method
			// file id is used to target the file entry with an UPDATE query to mark it as animated
			$fileId = $att['fileId'];

			// all good
			// now animate the attachment
			$this->animateGif($fileId);
		}
	}

	private function animateGif(int $fileId): void {
		// throw runtime exception if the file id is 0, negative or null
		if(is_null($fileId) || !$fileId || $fileId <= 0) {
			throw new RuntimeException;
		}

		// use fileService to update the entry to have its `is_animated` value marked as 1/true
		$this->moduleContext->fileService->animateFile($fileId);
	}

	private function onRenderAttachment(
		string &$attachmentProperties, 
		string &$attachmentImage, 
		string &$attachmentUrl, 
		array &$attachment
	): void {
		// stop module early if its not a gif		
		if ($attachment['fileExtension'] !== 'gif') {
			return;
		}

		// also stop if the attachment file doesn't exist on disk
		if (!attachmentFileExists($attachment)) {
			return;
		}

		// and finally, return early if its deleted
		if ($attachment['isDeleted'] && !isActiveStaffSession()) {
			return;
		}

		// file size in bytes
		$fileSize = $attachment['fileSize'];

		// max file size for animated gif (in kilobytes)
		$maxGifFileSize = $this->getConfig('ModuleSettings.MAX_SIZE_FOR_ANIMATED_GIF');
		
		// this is so large GIFs don't get loaded into the page
		// e.g a 50mb gif getting embedded straight into the page increases load time by a ton and causes problems for users with low bandwidth
		// So we limit it
		if ($fileSize >= $maxGifFileSize * 1024) {
			return;
		}

		$isAnimated = (bool) $attachment['isAnimated'];

		// replace image src url in order to directly display the gif (only when animated)
		if ($isAnimated) {
			$attachmentImage = preg_replace('/<img src=".*"/U', '<img src="' . $attachmentUrl . '"', $attachmentImage);
		}
		
		// always render animated gif label wrapper, hidden when not active
		$attachmentProperties .= $this->renderIndicator('animatedGifLabel', '[Animated GIF]', 'animatedGIFLabel imageOptions', !$isAnimated);
	}
}
