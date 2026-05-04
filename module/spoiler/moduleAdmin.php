<?php

namespace Kokonotsuba\Modules\spoiler;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getAttachmentUrl;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;
	use IncludeScriptTrait;

	public function getRequiredRole(): userRole {
		return userRole::LEV_JANITOR;
	}

	public function getName(): string {
		return 'Spoiler toggle tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->listenProtected('ModerateAttachmentWidget', function(array &$widgetArray, array &$fileData) {
			$this->onRenderAttachmentWidget($widgetArray, $fileData);
		});
        
        // include front-end script for click-to-reveal behaviour
		$this->registerScript('spoiler.js');
	}

	private function onRenderAttachmentWidget(array &$widgetArray, array &$fileData): void {
		$url = $this->generateSpoilerUrl($fileData['postUid'], $fileData['fileId']);
		$isSpoilered = (bool) ($fileData['isSpoilered'] ?? false);
		$label = $isSpoilered ? 'Remove spoiler' : 'Mark as spoiler';
		$widgetArray[] = $this->buildWidgetEntry($url, 'toggleSpoiler', $label, '');
	}

	private function generateSpoilerUrl(int $postUid, int $fileId): string {
		return $this->getModulePageURL(
			[
				'postUid' => $postUid,
				'fileId'  => $fileId,
			],
			false,
			true
		);
	}

	public function handleModuleRequest(): void {
		$postUid = $this->moduleContext->request->getParameter('postUid', 'POST');
		$fileId  = $this->moduleContext->request->getParameter('fileId', 'POST');

		if ($postUid === null || $postUid <= 0) {
			throw new BoardException('No post selected.');
		}

		if ($fileId === null || $fileId <= 0) {
			throw new BoardException('No attachment selected.');
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

		if (!$post) {
			throw new BoardException('ERROR: Post does not exist.');
		}

		if (empty($post->getAttachments())) {
			throw new BoardException('ERROR: No attachments on post.');
		}

		$attachment = $post->getAttachmentById($fileId);

		if ($attachment === null || $attachment <= 0) {
			throw new BoardException('ERROR: Attachment not found in post!');
		}

		$isSpoilered = (bool) ($attachment['isSpoilered'] ?? false);

		if ($isSpoilered) {
			$this->moduleContext->fileService->unspoilerFile($fileId);
		} else {
			$this->moduleContext->fileService->spoilerFile($fileId);
		}

		$isSpoilered = !$isSpoilered;

		$logMessage = $isSpoilered
			? 'Spoiler enabled on No. ' . htmlspecialchars($post->getNumber())
			: 'Spoiler removed on No. ' . htmlspecialchars($post->getNumber());

		$this->logAction($logMessage, $post->getBoardUID());

		$board = searchBoardArrayForBoard($post->getBoardUID());

		if ($this->moduleContext->request->isAjax()) {
			$spoilerImageUrl = $this->getConfig('STATIC_URL') . 'image/spoiler_image.png';
			$isAnimated = !empty($attachment['isAnimated']);
			$thumbUrl = $isSpoilered
				? $spoilerImageUrl
				: ($isAnimated ? getAttachmentUrl($attachment, false) : getAttachmentUrl($attachment, true));

			$label       = $isSpoilered ? 'Remove spoiler' : 'Mark as spoiler';
			$newButtonUrl = $this->generateSpoilerUrl($attachment['postUid'], $attachment['fileId']);

			sendAjaxAndDetach([
				'active'          => $isSpoilered,
				'thumbUrl'        => $thumbUrl,
				'thumbWidth'      => $isSpoilered ? 255 : (int) $attachment['thumbWidth'],
				'thumbHeight'     => $isSpoilered ? 255 : (int) $attachment['thumbHeight'],
				'newSpoilerButton' => $this->renderAttachmentButton($newButtonUrl, 'toggleSpoiler', $label, $isSpoilered ? 'sp' : 'SP'),
			]);

			$board->rebuildBoard();
			exit;
		}

		$board->rebuildBoard();

		redirect($this->moduleContext->request->getReferer());
	}
}
