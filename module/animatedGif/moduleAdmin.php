<?php

namespace Kokonotsuba\Modules\animatedGif;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\AuditableTrait;
use Kokonotsuba\module_classes\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getAttachmentUrl;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;

	public function getRequiredRole(): userRole {
		return userRole::LEV_JANITOR;
	}

	public function getName(): string {
		return 'Animated gif toggle tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->registerAttachmentHook('onRenderAttachment');
		$this->registerAdminHeaderHook('onGenerateModuleHeader');
	}
	
	private function onRenderAttachment(string &$attachmentProperties, array &$attachment): void {
		if ($this->canAnimateAttachment($attachment)) {
			// append animate gif button
			$attachmentProperties .= $this->generateAnimateGifButton($attachment);
		}
	}

	private function canAnimateAttachment(array $attachment): bool {
		return $attachment['fileExtension'] === 'gif';
	}

	private function generateAnimateGifButton(array $attachment): string {
		$animatedGifButtonUrl = $this->generateAnimatedGifUrl($attachment['postUid'], $attachment['fileId']);
		$flag = ($attachment['isAnimated']) ? 'title="Use still image of GIF">g' : 'title="Use animated GIF">G';
		
		return '<span class="adminFunctions adminGIFFunction attachmentButton">[<a href="' . htmlspecialchars($animatedGifButtonUrl) . '" ' . $flag . '</a>]</span>';
	}

	private function generateAnimatedGifUrl(int $postUid, int $fileId): string {
		// generate animated gif url
		$animatedGifUrl = $this->getModulePageURL(
			[
				'postUid' => $postUid,
				'fileId' => $fileId
			],
			false,
			true
		);

		// return url
		return $animatedGifUrl;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// include anigif mod script
		$this->includeScript('animatedGif.js', $moduleHeader);
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $this->moduleContext->request->getParameter('postUid', 'GET');
		
		// get file id from request
		$fileId = $this->moduleContext->request->getParameter('fileId', 'GET');

		// throw exception if post uid is blank
		if ($postUid === null || $postUid <= 0) {
			throw new BoardException("No post selected.");
		}

		// throw exception if file id is blank		
		if($fileId === null || $fileId <= 0) {
			throw new BoardException("No attachment selected.");
		}

		// get post
		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);
		
		// throw exception if post isn't found
		if (!$post) {
			throw new BoardException("ERROR: Post does not exist.");
		}

		// throw exception if the post has no attachments
		if (empty($post->getAttachments())) {
			throw new BoardException('ERROR: No attachments on post.');
		}

		// now select the attachment on this post
		$attachment = $post->getAttachments()[$fileId] ?? null;

		// if attachment isn't found or blank then throw exception
		if($attachment === null || $attachment <= 0) {
			throw new BoardException("ERROR: Attachment not found in post!");
		}

		// alright now we're through so far
		// set isAnimated flag
		$isAnimated = &$attachment['isAnimated'];

		// if the attachment is already animated then the action is to disable the is_animated flag
		if($isAnimated) {
			$this->moduleContext->fileService->disableAnimatedFile($fileId);
		}
		// however if its not animated then its time to animate the attachment
		else {
			$this->moduleContext->fileService->animateFile($fileId);
		}

		// toggle/invert the value so it aligns with the switch
		$isAnimated = !$isAnimated;

		$logMessage = $isAnimated
			? 'Animated GIF activated on No. ' . htmlspecialchars($post->getNumber())
			: 'Animated GIF deactivated on No. ' . htmlspecialchars($post->getNumber());

		$this->logAction($logMessage, $post->getBoardUID());

		// get the board of the post
		$board = searchBoardArrayForBoard($post->getBoardUID());

		// ===== AJAX handling updated to use helper =====
		if($this->moduleContext->request->isAjax()) {
			// get url
			$attachmentUrl = $this->getAnimatedAttachmentUrl($attachment, $isAnimated);

			// send json first
			sendAjaxAndDetach([
				'active' => $isAnimated,
				'attachmentUrl' => $attachmentUrl,
				'newGifButton' => $this->generateAnimateGifButton($attachment)
			]);

			// rebuild after client already received JSON
			$board->rebuildBoard();
			exit;
		}
		// ===== end AJAX handling =====

		// rebuild board
		$board->rebuildBoard();

		redirect($this->moduleContext->request->getReferer());
	}

	private function getAnimatedAttachmentUrl(array $attachment, bool $isAnimated): string {
		// if its animated then get the true attachment url
		if($isAnimated) {
			// return attachment url
			return getAttachmentUrl($attachment, false);
		}
		// otherwise get the thumb url
		else {
			// return thumb url
			return getAttachmentUrl($attachment, true);
		}
	}

}