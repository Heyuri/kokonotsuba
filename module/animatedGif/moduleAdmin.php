<?php

namespace Kokonotsuba\Modules\animatedGif;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;


class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR;
	}

	public function getName(): string {
		return 'Animated gif toggle tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateAttachment',
			function(
				string &$attachmentProperties, 
				string &$attachmentImage, 
				string &$attachmentUrl, 
				array &$attachment,
			) {
				$this->onRenderAttachment($attachmentProperties, $attachment);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);
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
		
		return '<span class="adminFunctions adminGIFFunction">[<a href="' . htmlspecialchars($animatedGifButtonUrl) . '" ' . $flag . '</a>]</span>';
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
		// generate the script header
		$jsHtml = $this->generateScriptHeader('animatedGif.js', true);

		// then append it to the header
		$moduleHeader .= $jsHtml;
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $_GET['postUid'] ?? null;
		
		// get file id from request
		$fileId = $_GET['fileId'] ?? null;

		// throw exception if post uid is blank
		if ($postUid === null || $postUid <= 0) {
			throw new BoardException("No post selected.");
		}

		// throw exception if file id is blank		
		if($fileId === null || $fileId <= 0) {
			throw new BoardException("No attachment selected.");
		}

		// get post
		$post = $this->moduleContext->postRepository->getPostByUid($postUid);
		
		// throw exception if post isn't found
		if (!$post) {
			throw new BoardException("ERROR: Post does not exist.");
		}

		// throw exception if the post has no attachments
		if (empty($post['attachments'])) {
			throw new BoardException('ERROR: No attachments on post.');
		}

		// now select the attachment on this post
		$attachment = $post['attachments'][$fileId] ?? null;

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
			? 'Animated GIF activated on No. ' . htmlspecialchars($post['no'])
			: 'Animated GIF deactivated on No. ' . htmlspecialchars($post['no']);

		$this->moduleContext->actionLoggerService->logAction($logMessage, $post['boardUID']);

		// get the board of the post
		$board = searchBoardArrayForBoard($post['boardUID']);

		// ===== AJAX handling updated to use helper =====
		if (
			isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
		) {
			// get url
			$attachmentUrl = $this->getAnimatedAttachmentUrl($attachment, $isAnimated);

			// send json first
			$this->sendAjaxAndDetach([
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

		redirect('back', 0);
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