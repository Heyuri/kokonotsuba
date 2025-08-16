<?php

namespace Kokonotsuba\Modules\sticky;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_STICKY', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Sticky tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'ThreadAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderThreadAdminControls($modControlSection, $post);
			}
		);
	}

    public function onRenderThreadAdminControls(string &$modfunc, array $post): void {
		$fh = new FlagHelper($post['status']);
		$stickyTitle = $fh->value('sticky') ? 'Unsticky' : 'Sticky post';
		$toggleLabel = $fh->value('sticky') ? 's' : 'S'; 

		$stickyButtonUrl = $this->getModulePageURL(
			[
				'post_uid' => $post['post_uid']
			],
			true,
			true
		);

		$modfunc .= '<span class="adminFunctions adminStickyFunction">[<a href="' . $stickyButtonUrl . '" title="' . $stickyTitle . '">'.$toggleLabel.'</a>]</span>';
	}

    public function ModulePage(): void {
		$post_uid = $_GET['post_uid'];
		$post = $this->moduleContext->postRepository->getPostByUID($post_uid);

		if (!$post) {
			throw new BoardException('ERROR: Post does not exist.');
		}

		if (!$post['is_op']) {
			throw new BoardException('ERROR: Cannot sticky a reply.');
		}

		$flags = new FlagHelper($post['status']);
		$flags->toggle('sticky');
		$this->moduleContext->postRepository->setPostStatus($post['post_uid'], $flags->toString());

		// Reset bump if sticky is removed
		if (!$flags->value('sticky')) {
			$this->moduleContext->threadRepository->bumpThread($post['thread_uid']);
		}

		$this->moduleContext->actionLoggerService->logAction(
			'Changed sticky status on post No.' . $post['no'] . ' (' . ($flags->value('sticky') ? 'true' : 'false') . ')',
			$post['boardUID']
		);

		$board = searchBoardArrayForBoard($post['boardUID']);

		$board->rebuildBoard();

		redirect('back', 1);
	}
}