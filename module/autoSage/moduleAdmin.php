<?php

namespace Kokonotsuba\Modules\autoSage;

require_once __DIR__ . '/autoSageLibrary.php';

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
			$this->getRequiredRole(),
			'ManagePostsThreadControls',
			function(string &$modControlSection, Post &$post) {
				$this->renderAutoSageButton($modControlSection, $post, false);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ThreadAdminControls',
			function(string &$modControlSection, Post &$post) {
				$this->renderAutoSageButton($modControlSection, $post, true);
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

	private function renderAutoSageButton(string &$modfunc, Post $post, bool $noScript) {
		// only render the button for OP posts
		$status = $post->getFlags();

		// generate the autosage url
		$autoSageLink = $this->generateAutoSageUrl($post->getUid());

		// generate the button html and append it to the modfunc string
		$modfunc .= generateModerateButton(
			$autoSageLink,
			$status->value('as') ? 'as' : 'AS',
			$status->value('as') ? 'Un-autosage' : 'Autosage',
			'adminAutoSageFunction',
			$noScript
		);
	}

	private function onRenderThreadWidget(array &$widgetArray, Post &$post): void {
		// get post status
		$postStatus = $post->getFlags();

		// get autosage label
		$autoSageLabel = $this->getAutoSageLabel($postStatus);
		
		// generate autosage url
		$autoSageUrl = $this->generateAutoSageUrl($post->getUid());

		// build the widget entry
		$autoSageWidget = $this->buildWidgetEntry(
			$autoSageUrl, 
			'autosage', 
			$autoSageLabel, 
			''
		);

		// add the widget to the array
		$widgetArray[] = $autoSageWidget;
	}

	private function getAutoSageLabel(FlagHelper $postStatus): string {
		// autosage flag
		$isAutosaged = $postStatus->value('as');

		// if the thread is already autosaged then the action is to unautosage it
		if($isAutosaged) {
			return 'Un-autosage thread';
		}
		// if the thread isn't autosaged then the action is to autosage it
		else {
			return 'Autosage thread';
		}

	}

	private function generateAutoSageUrl(int $postUid): string {
		// get AS url
		$autoSageUrl = $this->getModulePageURL(
			[
				'post_uid' => $postUid
			],
			false,
			true
		);

		// return url
		return $autoSageUrl;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the autosage <template> html
		$templateHtml = $this->generateAutoSageTemplate();

		// generate toggle widget + js
		$this->generateToggleWidget($moduleHeader, 'autosage.js', $templateHtml);
	}

	private function generateAutoSageTemplate(): string {
		// get the autosage indicator tag
		$autoSageIndicator = getAutoSageIndicator();
		
		// generate the autosage template html
		$autoSageTemplateHtml = $this->generateTemplate('autoSageTemplate', $autoSageIndicator);

		// return AS template
		return $autoSageTemplateHtml;
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
		
		$this->moduleContext->actionLoggerService->logAction($logMessage, $board->getBoardUID());

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
