<?php

namespace Kokonotsuba\Modules\autoSage;

require_once __DIR__ . '/autoSageLibrary.php';

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
			$this->getRequiredRole(),
			'ManagePostsThreadControls',
			function(string &$modControlSection, array &$post) {
				$this->renderAutoSageButton($modControlSection, $post);
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

	private function renderAutoSageButton(string &$modfunc, array $post) {
		$status = new FlagHelper($post['status']);

		$autoSageLink = $this->generateAutoSageUrl($post['post_uid']);

		$modfunc.= '<span class="adminFunctions adminAutosageFunction">[<a href="' . htmlspecialchars($autoSageLink) . '"' . ($status->value('as') ? ' title="Allow age">as' : ' title="Autosage">AS') . '</a>]</span>';
	}

	private function onRenderThreadWidget(array &$widgetArray, array &$post): void {
		// get post status
		$postStatus = new FlagHelper($post['status']);

		// get autosage label
		$autoSageLabel = $this->getAutoSageLabel($postStatus);
		
		// generate autosage url
		$autoSageUrl = $this->generateAutoSageUrl($post['post_uid']);

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

		// ===== AJAX handling updated to use helper =====
		if(isJavascriptRequest()) {
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
		
		redirect('back', 1);
	}
}
