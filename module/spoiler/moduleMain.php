<?php

namespace Kokonotsuba\Modules\spoiler;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\IndicatorTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentsAfterInsertListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeHtmlTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
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
		return 'Kokonotsuba Spoiler';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->listenAttachmentsAfterInsert('onAttachmentsAfterInsert');

		$this->listenAttachment('onRenderAttachment');

		$this->listenPostFormFile('onRenderPostFormFile');

		// signal presence to clipboard.js so it adds per-file spoiler checkboxes
		$this->registerHeaderHtml('<template id="spoilerData"></template>');
	}

	private function onRenderPostFormFile(string &$file): void {
		// noscript fallback: render one spoiler checkbox per possible file slot
		// so non-JS users submitting multiple files can mark each one individually
		$attachmentLimit = (int) $this->getConfig('ATTACHMENT_UPLOAD_LIMIT', 1);

		$checkboxes = '';
		for ($i = 0; $i < $attachmentLimit; $i++) {
			$label = $attachmentLimit > 1 ? 'Spoiler (' . ($i + 1) . ')' : 'Spoiler';
			$checkboxes .= '<label title="Mark file as spoiler">'
				. '<input type="checkbox" name="spoiler[' . $i . ']" id="spoiler' . $i . '" value="on"> '
				. $label
				. '</label> ';
		}

		$file .= '<noscript><div id="spoilerContainer">' . $checkboxes . '</div></noscript>';
	}

	private function onAttachmentsAfterInsert(?array &$attachments): void {
		if (empty($attachments)) {
			return;
		}

		$this->handlePostSpoiler($attachments);
	}

	private function handlePostSpoiler(array &$attachments): void {
		// spoiler[N] is sent per-file; N maps to the 0-based insertion order
		$spoilerMap = $this->moduleContext->request->getParameter('spoiler', 'POST');

		if (empty($spoilerMap) || !is_array($spoilerMap)) {
			return;
		}

		// attachments are keyed by fileId; re-index to match form submission order
		$attachmentValues = array_values($attachments);

		foreach ($attachmentValues as $i => $att) {
			if (isset($spoilerMap[$i])) {
				$fileId = $att['fileId'];

				if (is_null($fileId) || $fileId <= 0) {
					throw new RuntimeException('Invalid file ID during spoiler handling');
				}

				$this->moduleContext->fileService->spoilerFile($fileId);
			}
		}
	}

	private function onRenderAttachment(
		string &$attachmentProperties,
		string &$attachmentImage,
		string &$attachmentUrl,
		array &$attachment
	): void {
		// skip if not spoilered
		$isSpoilered = (bool) ($attachment['isSpoilered'] ?? false);

		if (!$isSpoilered) {
			return;
		}

		// also stop if the attachment file doesn't exist on disk
		if (!attachmentFileExists($attachment)) {
			return;
		}

		// and finally, return early if its deleted and viewer isn't staff
		if ($attachment['isDeleted'] && !isActiveStaffSession()) {
			return;
		}

		// replace the thumbnail src with the spoiler image
		$spoilerImageUrl = $this->getConfig('STATIC_URL') . 'image/spoiler_image.png';
		$attachmentImage = preg_replace('/<img src="[^"]*"/U', '<img src="' . sanitizeStr($spoilerImageUrl) . '"', $attachmentImage);

		// replace thumbnail dimensions with 255x255
		$attachmentImage = preg_replace('/width="\d+"/', 'width="255"', $attachmentImage);
		$attachmentImage = preg_replace('/height="\d+"/', 'height="255"', $attachmentImage);

		// render spoiler label indicator
		$attachmentProperties .= $this->renderIndicator('spoilerLabel', '[Spoiler]', 'spoilerLabel imageOptions');
	}
}
