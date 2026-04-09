<?php

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';
require_once __DIR__ . '/stickyRepository.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\ToggleActionTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\generateModerateForm;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	use ToggleActionTrait;
	use AuditableTrait;

	private stickyRepository $stickyRepository;

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

	protected function shouldRegisterThreadAdminControls(): bool { return false; }

	protected function getToggleUrlParams(Post $post): array {
		return ['thread_uid' => $post->getThreadUid()];
	}

	public function initialize(): void {
		$databaseSettings = \getDatabaseSettings();
		$this->stickyRepository = new stickyRepository(
			databaseConnection::getInstance(),
			$databaseSettings['THREAD_TABLE']
		);

		$this->registerToggleHooks();
	}

	protected function renderToggleButton(string &$modfunc, Post $post, bool $noScript): void {
		$isActive = $this->stickyRepository->isSticky($post->getThreadUid());
		$url = $this->generateToggleActionUrl($post);

		$modfunc .= generateModerateForm(
			$url,
			$isActive ? $this->getToggleActiveLabel() : $this->getToggleInactiveLabel(),
			$isActive ? $this->getToggleActiveTitle() : $this->getToggleInactiveTitle(),
			$this->getToggleCssClass(),
			$noScript
		);
	}

	protected function onRenderToggleWidget(array &$widgetArray, Post &$post): void {
		$isActive = $this->stickyRepository->isSticky($post->getThreadUid());
		$url = $this->getModulePageURL([], false, true);
		$label = $isActive ? $this->getToggleActiveTitle() : $this->getToggleInactiveTitle();

		$widgetArray[] = $this->buildWidgetEntry(
			$url,
			$this->getToggleActionName(),
			$label,
			'',
			['post_uid' => $post->getUid()]
		);
	}

	protected function handleModuleRequest(): void {
		// Accept post_uid (from widget JS) or thread_uid (from admin control forms)
		$postUid = $this->moduleContext->request->getParameter('post_uid');
		if ($postUid) {
			$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);
			if (!$post) {
				throw new BoardException('ERROR: Post does not exist.');
			}
			$thread_uid = $post->getThreadUid();
		} else {
			$thread_uid = $this->moduleContext->request->getParameter('thread_uid');
		}
	
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

		// toggle sticky status in the threads table
		$isStickied = $this->stickyRepository->toggleSticky($thread_uid);
	
		// post op number of the thread
		$post_op_number = $threadData->getOpNumber();
	
		// board uid of the thread
		$boardUid = $threadData->getBoardUID();

		$this->logAction(
			'Changed sticky status on post No.' . $post_op_number . ' (' . ($isStickied ? 'true' : 'false') . ')',
			$boardUid
		);
	
		$board = searchBoardArrayForBoard($boardUid);
	
		// ===== AJAX handling updated to use helper =====
		if($this->moduleContext->request->isAjax()) {
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