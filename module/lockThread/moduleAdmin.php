<?php

namespace Kokonotsuba\Modules\lockThread;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_LOCK');
	}

	public function getName(): string {
		return 'Thread locking tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ThreadAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->addLockButtonToAdminControls($modControlSection, $post);
			}
		);
	}

	public function addLockButtonToAdminControls(&$modfunc, $post) {
		$status = new FlagHelper($post['status']);

		$lockThreadLink = $this->getModulePageURL(
			[
				'post_uid' => $post['post_uid']
			],
			true,
			true
		);

		$modfunc.= '<span class="adminFunctions adminLockFunction">[<a href="' . $lockThreadLink . '"' . ($status->value('stop') ? ' title="Unlock thread">l' : ' title="Lock thread">L').'</a>]</span>';
	}

	public function ModulePage() {		
		$post = $this->moduleContext->postRepository->getPostByUid($_GET['post_uid']);

		$board = searchBoardArrayForBoard($post['boardUID']);

		if(!$post['is_op']) {
			throw new BoardException('ERROR: Cannot lock reply.');
		}

		if(!$post) {
			throw new BoardException('ERROR: Post does not exist.');
		}

		$status = new FlagHelper($post['status']);
		
		$status->toggle('stop');

		$this->moduleContext->postRepository->setPostStatus($post['post_uid'], $status->toString());
		
		$logMessage = $status->value('stop') ? "Locked thread No. {$post['no']}" : "Unlock thread No. {$post['no']}";
		
		$this->moduleContext->actionLoggerService->logAction($logMessage, $board->getBoardUID());
		
		$board->rebuildBoard();

		redirect('back', 0);
	}
}