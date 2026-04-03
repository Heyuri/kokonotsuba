<?php

namespace Kokonotsuba\Modules\autoSage;

require_once __DIR__ . '/autoSageLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\ToggleActionTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

// auto sage module made for kokonotsuba by deadking
class moduleAdmin extends abstractModuleAdmin {
	use ToggleActionTrait;
	use AuditableTrait;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_AUTO_SAGE');
    }

	public function getName(): string {
		return 'Autosage tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	protected function getToggleFlagKey(): string { return 'as'; }
	protected function getToggleActiveLabel(): string { return 'as'; }
	protected function getToggleInactiveLabel(): string { return 'AS'; }
	protected function getToggleActiveTitle(): string { return 'Un-autosage'; }
	protected function getToggleInactiveTitle(): string { return 'Autosage'; }
	protected function getToggleCssClass(): string { return 'adminAutoSageFunction'; }
	protected function getToggleActionName(): string { return 'autosage'; }
	protected function getToggleJsFile(): string { return 'autosage.js'; }
	protected function getToggleTemplateName(): string { return 'autoSageTemplate'; }

	protected function getToggleIndicatorHtml(): string {
		return getAutoSageIndicator();
	}

	protected function getToggleUrlParams(Post $post): array {
		return ['post_uid' => $post->getUid()];
	}

	public function initialize(): void {
		$this->registerToggleHooks();
	}

	public function ModulePage() {
		$post = $this->moduleContext->postRepository->getPostByUid($this->moduleContext->request->getParameter('post_uid', 'GET'), true);

		if (!$post->isOp()) { 
			throw new BoardException('ERROR: Cannot autosage reply.');
		}

		$board = searchBoardArrayForBoard($post->getBoardUID());

		if (!$post) {
			throw new BoardException('ERROR: Post does not exist.');
		}
		
		$status = $post->getFlags();
		
		$status->toggle('as');
		
		$this->moduleContext->postRepository->setPostStatus($post->getUid(), $status->toString());
		
		$logMessage = $status->value('as') ? "Autosaged No. {$post->getNumber()}" : "Took off autosage on No. {$post->getNumber()}";
		
		$this->logAction($logMessage, $board->getBoardUID());

		// ===== AJAX handling updated to use helper =====
		if($this->moduleContext->request->isAjax()) {
			// whether the post-action thread is AS'd or not
			$isAutosaged = $status->value('as');

			// send json first
			sendAjaxAndDetach([
				'active' => $isAutosaged
			]);

			// rebuild after client already received JSON
			$board->rebuildBoard();
			exit;
		}
		// ===== end AJAX handling =====

		$board->rebuildBoard();	
		
		redirect($this->moduleContext->request->getReferer());
	}
}
