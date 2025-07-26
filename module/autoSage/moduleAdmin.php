<?php

namespace Kokonotsuba\Modules\autoSage;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

// auto sage module made for kokonotsuba by deadking
class moduleAdmin extends abstractModuleAdmin {
    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_AUTO_SAGE');
    }

	public function getName(): string {
		return 'Autosage tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'ThreadAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->renderAutoSageButton($modControlSection, $post);
			}
		);
	} 

	public function renderAutoSageButton(string &$modfunc, array $post) {
		$status = new FlagHelper($post['status']);

		$autoSageLink = $this->getModulePageURL(
			[
				'post_uid' => $post['post_uid']
			]
		);

		$modfunc.= '<span class="adminFunctions adminAutosageFunction">[<a href="' . $autoSageLink . '"' . ($status->value('as') ? ' title="Allow age">as' : ' title="Autosage">AS') . '</a>]</span>';
	}

	public function ModulePage() {
		$post = $this->moduleContext->postRepository->getPostByUid($_GET['post_uid']);

		if (!$post['is_op']) { 
			throw new BoardException('ERROR: Cannot autosage reply.');
		}

		$board = searchBoardArrayForBoard($post['boardUID']);

		if (!$post) {
			throw new BoardException('ERROR: Post does not exist.');
		}
		
		$status = new FlagHelper($post['status']);
		
		$status->toggle('as');
		
		$this->moduleContext->postRepository->setPostStatus($post['post_uid'], $status->toString());
		
		$logMessage = $status->value('as') ? "Autosaged No. {$post['no']}" : "Took off autosage on No. {$post['no']}";
		
		$this->moduleContext->actionLoggerService->logAction($logMessage, $board->getBoardUID());
		
		$board->rebuildBoard();	
		
		redirect('back', 1);
	}
}
