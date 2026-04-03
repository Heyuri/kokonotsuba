<?php

namespace Kokonotsuba\Modules\lockThread;

require_once __DIR__ . '/lockThreadLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\ToggleActionTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	use ToggleActionTrait;
	use AuditableTrait;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_LOCK');
	}

	public function getName(): string {
		return 'Thread locking tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	protected function getToggleFlagKey(): string { return 'stop'; }
	protected function getToggleActiveLabel(): string { return 'l'; }
	protected function getToggleInactiveLabel(): string { return 'L'; }
	protected function getToggleActiveTitle(): string { return 'Unlock thread'; }
	protected function getToggleInactiveTitle(): string { return 'Lock thread'; }
	protected function getToggleCssClass(): string { return 'adminLockFunction'; }
	protected function getToggleActionName(): string { return 'lock'; }
	protected function getToggleJsFile(): string { return 'lock.js'; }
	protected function getToggleTemplateName(): string { return 'lockIconTemplate'; }

	protected function getToggleIndicatorHtml(): string {
		return getLockIndicator($this->getConfig('STATIC_URL'));
	}

	protected function getToggleUrlParams(Post $post): array {
		return ['post_uid' => $post->getUid()];
	}

	public function initialize(): void {
		$this->registerToggleHooks();
	}

	public function ModulePage() {		
		$post = $this->moduleContext->postRepository->getPostByUid($this->moduleContext->request->getParameter('post_uid', 'GET'), true);

		$board = searchBoardArrayForBoard($post->getBoardUID());

		if(!$post->isOp()) {
			throw new BoardException('ERROR: Cannot lock reply.');
		}

		if(!$post) {
			throw new BoardException('ERROR: Post does not exist.');
		}

		$status = $post->getFlags();
		
		$status->toggle('stop');

		$this->moduleContext->postRepository->setPostStatus($post->getUid(), $status->toString());
		
		$logMessage = $status->value('stop') ? "Locked thread No. {$post->getNumber()}" : "Unlock thread No. {$post->getNumber()}";
		
		$this->logAction($logMessage, $board->getBoardUID());
		
		// ===== AJAX handling updated to use helper =====
		if($this->moduleContext->request->isAjax()) {
			// whether the post-action thread is locked or not
			$isLocked = $status->value('stop');

			// send json first
			sendAjaxAndDetach([
				'active' => $isLocked
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