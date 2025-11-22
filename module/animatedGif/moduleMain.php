<?php
// animated gif module made for kokonotsuba by deadking
// "forked" from the siokara mod for pixmicat

namespace Kokonotsuba\Modules\animatedGif;

use board;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleMain;
use PMCLibrary;
use RuntimeException;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'Kokonotsuba Animated GIF';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {

		$this->moduleContext->moduleEngine->addListener('AttachmentsAfterInsert', 
			function (?array &$attachments) {
				$this->onAttachmentsAfterInsert($attachments); 
			}
		);

		$this->moduleContext->moduleEngine->addListener('Attachment', function(
			string &$attachmentProperties, 
			string &$attachmentImage, 
			string &$attachmentUrl, 
			array &$attachment,
		) {
			$this->onRenderAttachment($attachmentProperties, $attachmentImage, $attachmentUrl, $attachment);
		});

		$this->moduleContext->moduleEngine->addListener('PostFormFile', function(string &$formFileSection) {
			$this->onRenderPostFormFile($formFileSection);
		});
	}

	private function onRenderPostFormFile(string &$file): void {
		$file.= '<div id="anigifContainer"><label id="anigifLabel" title="Makes GIF thumbnails animated"><input type="checkbox" name="anigif" id="anigif" value="on">Animated GIF</label></div>';
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
		$anigifRequested = isset($_POST['anigif']);
		
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

		// Only process attachments that are marked as animated
		if (!$attachment['isAnimated']) {
			return;
		}

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
		// replace image src url in order to directly display the gif
		$attachmentImage = preg_replace('/<img src=".*"/U', '<img src="' . $attachmentUrl . '"', $attachmentImage);
		
		// append animated gif label to properties
		$attachmentProperties .= '<span class="animatedGIFLabel imageOptions">[Animated GIF]</span>';
	}
}
