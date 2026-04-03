<?php

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\FlagHelper;
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
		return $this->getConfig('AuthLevels.CAN_STICKY', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Sticky tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	protected function getToggleFlagKey(): string { return 'sticky'; }
	protected function getToggleActiveLabel(): string { return 's'; }
	protected function getToggleInactiveLabel(): string { return 'S'; }
	protected function getToggleActiveTitle(): string { return 'Unsticky thread'; }
	protected function getToggleInactiveTitle(): string { return 'Sticky thread'; }
	protected function getToggleCssClass(): string { return 'adminStickyFunction'; }
	protected function getToggleActionName(): string { return 'sticky'; }
	protected function getToggleJsFile(): string { return 'sticky.js'; }
	protected function getToggleTemplateName(): string { return 'stickyIconTemplate'; }

	protected function shouldRegisterThreadAdminControls(): bool { return false; }

	protected function getToggleIndicatorHtml(): string {
		return getStickyIndicator($this->getConfig('STATIC_URL'));
	}

	protected function getToggleUrlParams(Post $post): array {
		return ['thread_uid' => $post->getThreadUid()];
	}

	public function initialize(): void {
		$this->registerToggleHooks();
	}

	public function ModulePage(): void {
		$thread_uid = $this->moduleContext->request->getParameter('thread_uid', 'GET');
	
		// no thread uid selected - throw exception
		if($thread_uid === null) {
			throw new BoardException("No thread was selected!");
		}
		
		// get the thread and associated data (thread data, posts, etc)
		$threadData = $this->moduleContext->threadService->getThreadData($thread_uid, true);
		
		// throw an exception if the thread doesn't exist
		if (!$threadData) {
			throw new BoardException('ERROR: Thread does not exist.');
		}
	
		// fetch the opening post
		$openingPost = $this->moduleContext->postRepository->getOpeningPostFromThread($thread_uid);
	
		// bool column for if the thread is stickied
		$is_sticky = $threadData->isSticky();
	
		// if it's already sticky'd, then unsticky it
		if($is_sticky) {
			$this->moduleContext->threadRepository->unStickyThread($thread_uid);
		}
		// sticky the thread
		else {
			$this->moduleContext->threadRepository->stickyThread($thread_uid);
		}
	
		// toggles OP post status too so we don't have to refactor too much code for rendering in the time being
		$flags = $openingPost->getFlags();
		$flags->toggle('sticky');
		$this->moduleContext->postRepository->setPostStatus($openingPost->getUid(), $flags->toString());
	
		// post op number of the thread
		$post_op_number = $threadData->getOpNumber();
	
		// board uid of the thread
		$boardUid = $threadData->getBoardUID();
	
		$this->logAction(
			'Changed sticky status on post No.' . $post_op_number . ' (' . ($is_sticky ? 'false' : 'true') . ')',
			$boardUid
		);
	
		$board = searchBoardArrayForBoard($boardUid);
	
		// ===== AJAX handling updated to use helper =====
		if($this->moduleContext->request->isAjax()) {
			// whether the post-action thread is stickied or not
			$isStickied = $flags->value('sticky');

			// send json first
			sendAjaxAndDetach([
				'active' => $isStickied
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