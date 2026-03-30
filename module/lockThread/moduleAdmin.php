<?php

namespace Kokonotsuba\Modules\lockThread;

require_once __DIR__ . '/lockThreadLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

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
			'ManagePostsThreadControls',
			function(string &$modControlSection, Post &$post) {
				$this->addLockButtonToAdminControls($modControlSection, $post, false);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ThreadAdminControls',
			function(string &$modControlSection, Post &$post) {
				$this->addLockButtonToAdminControls($modControlSection, $post, true);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateThreadWidget',
			function(array &$widgetArray, Post &$post) {
				$this->onRenderThreadWidget($widgetArray, $post);
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

	private function addLockButtonToAdminControls(string &$modfunc, Post $post, bool $noScript) {
		$status = $post->getFlags();

		$lockThreadLink = $this->generateLockUrl($post->getUid());

		$modfunc .= generateModerateButton(
			$lockThreadLink,
			$status->value('stop') ? 'l' : 'L',
			$status->value('stop') ? 'Unlock thread' : 'Lock thread',
			'adminLockFunction',
			$noScript
		);
	}

	private function onRenderThreadWidget(array &$widgetArray, Post &$post): void {
		// generate lock url
		$lockUrl = $this->generateLockUrl($post->getUid());

		// get the post status
		$postStatus = $post->getFlags();

		// get the lock label
		$lockLabel = $this->generateLockLabel($postStatus);

		// build the widget entry
		$lockWidget = $this->buildWidgetEntry(
			$lockUrl, 
			'lock', 
			$lockLabel, 
			''
		);

		// add the widget to the array
		$widgetArray[] = $lockWidget;
	}

	private function generateLockLabel(FlagHelper $postStatus): string {
		// if the locked or not
		$isLocked = $postStatus->value('stop');

		// if the thread is already locked then the action is to unlock it
		if($isLocked) {
			return 'Unlock thread';
		}
		// if the thread isn't locked then the action is to lock it
		else {
			return 'Lock thread';
		}
	}

	private function generateLockUrl(int $postUid): string {
		// generate lock thread url
		$lockThreadLink = $this->getModulePageURL(
			[
				'post_uid' => $postUid
			],
			false,
			true
		);

		// return url
		return $lockThreadLink;
	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the lock img <template> html
		$templateHtml = $this->generateLockTemplate();

		// generate toggle widget + js
		$this->generateToggleWidget($moduleHeader, 'lock.js', $templateHtml);
	}
	
	private function generateLockTemplate(): string {
		// get static url
		$staticUrl = $this->getConfig('STATIC_URL');

		// get the locked indicator tag
		$lockIndicator = getLockIndicator($staticUrl);
		
		// get lock icon template
		$lockIconTemplate = $this->generateTemplate('lockIconTemplate', $lockIndicator);

		// return template
		return $lockIconTemplate;
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
		
		$this->moduleContext->actionLoggerService->logAction($logMessage, $board->getBoardUID());
		
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