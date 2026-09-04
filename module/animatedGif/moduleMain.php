<?php
// animated image module made for kokonotsuba by deadking
// plays animated GIF, WebP and PNG uploads inline instead of their still thumbnail

namespace Kokonotsuba\Modules\animatedGif;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\thumb\imageProbe;
use Kokonotsuba\module_classes\traits\IndicatorTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentsAfterInsertListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeHtmlTrait;
use Kokonotsuba\module_classes\traits\listeners\PostFormFileListenerTrait;
use RuntimeException;

use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\getAttachmentFilePath;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use AttachmentsAfterInsertListenerTrait;
	use AttachmentListenerTrait;
	use IncludeHtmlTrait;
	use IndicatorTrait;
	use PostFormFileListenerTrait;

	/** Formats that carry more than one frame and can be played in place of a thumbnail. */
	private const ANIMATABLE_EXTENSIONS = ['gif', 'webp', 'png'];

	public function getName(): string {
		return 'Kokonotsuba Animated Images';
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
		$file .= '<noscript><div id="anigifContainer"><label id="anigifLabel" title="Plays animated GIF, WebP and PNG uploads in place of their thumbnail"><input type="checkbox" name="anigif" id="anigif" value="on">Animated image</label></div></noscript>';
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

		// if toggled then mark every attachment that actually holds more than one frame
		if ($anigifRequested) {
			$this->animateAllAttachments($attachments);
		}
	}

	private function animateAllAttachments(array &$attachments): void {
		$sizeLimit = (int)$this->getModuleConfig('MAX_SIZE_FOR_ANIMATED_GIF', 2000);

		foreach($attachments as &$att) {
			// skip anything that isn't an animation, rather than abandoning the whole batch:
			// one still image among the uploads must not stop the rest being marked
			if(!self::isAnimatedAttachment($att, $sizeLimit)) {
				continue;
			}

			// the file id targets the row the animated flag is set on
			$this->animateGif($att['fileId']);
		}
	}

	/**
	 * Whether an attachment is an animation this module can play in place of its thumbnail.
	 * The file itself is read, so a still GIF or a WebP that only looks animated is rejected.
	 *
	 * @param int $sizeLimitKb Skip files at or above this size, which the renderer refuses
	 *                         to play inline anyway. Reading one to find out costs time
	 *                         proportional to its size, so the cheap test goes first.
	 */
	public static function isAnimatedAttachment(array $attachment, int $sizeLimitKb = 0): bool {
		if(!in_array($attachment['fileExtension'] ?? '', self::ANIMATABLE_EXTENSIONS, true)) {
			return false;
		}

		if($sizeLimitKb > 0 && (int)($attachment['fileSize'] ?? 0) >= $sizeLimitKb * 1024) {
			return false;
		}

		$filePath = getAttachmentFilePath($attachment);

		return $filePath !== '' && is_file($filePath) && imageProbe::isAnimated($filePath);
	}

	/** Label for the animation indicator, named for the format it belongs to. */
	public static function animationLabel(array $attachment): string {
		$format = match ($attachment['fileExtension'] ?? '') {
			'webp' => 'WebP',
			'png' => 'PNG',
			default => 'GIF',
		};

		return '[Animated ' . $format . ']';
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
		// stop early unless this is a format that can be played inline
		if (!in_array($attachment['fileExtension'], self::ANIMATABLE_EXTENSIONS, true)) {
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

		// max file size for an animation (in kilobytes)
		$maxGifFileSize = $this->getModuleConfig('MAX_SIZE_FOR_ANIMATED_GIF');
		
		// this is so large GIFs don't get loaded into the page
		// e.g a 50mb gif getting embedded straight into the page increases load time by a ton and causes problems for users with low bandwidth
		// So we limit it
		if ($fileSize >= $maxGifFileSize * 1024) {
			return;
		}

		$isAnimated = (bool) $attachment['isAnimated'];

		// replace image src url in order to directly display the animation (only when animated)
		if ($isAnimated) {
			$attachmentImage = preg_replace('/<img src=".*"/U', '<img src="' . $attachmentUrl . '"', $attachmentImage);
		}
		
		// always render the indicator wrapper, hidden when not active
		$attachmentProperties .= $this->renderIndicator('animatedGifLabel', self::animationLabel($attachment), 'animatedGIFLabel imageOptions', !$isAnimated);
	}
}
