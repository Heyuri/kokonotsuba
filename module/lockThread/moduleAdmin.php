<?php

namespace Kokonotsuba\Modules\lockThread;

require_once __DIR__ . '/lockThreadLibrary.php';

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

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateThreadWidget',
			function(array &$widgetArray, array &$post) {
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

	private function addLockButtonToAdminControls(&$modfunc, $post) {
		$status = new FlagHelper($post['status']);

		$lockThreadLink = $this->generateLockUrl($post['post_uid']);

		$modfunc.= '<span class="adminFunctions adminLockFunction">[<a href="' . htmlspecialchars($lockThreadLink) . '"' . ($status->value('stop') ? ' title="Unlock thread">l' : ' title="Lock thread">L').'</a>]</span>';
	}

	private function onRenderThreadWidget(array &$widgetArray, array &$post): void {
		// generate lock url
		$lockUrl = $this->generateLockUrl($post['post_uid']);

		// get the post status
		$postStatus = new FlagHelper($post['status']);

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
		
		// ===== AJAX handling updated to use helper =====
		if(isJavascriptRequest()) {
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

		redirect('back', 0);
	}
}