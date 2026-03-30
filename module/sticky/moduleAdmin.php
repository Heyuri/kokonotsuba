<?php

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

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
			$this->getRequiredRole(),
			'ManagePostsThreadControls',
			function(string &$modControlSection, Post &$post) {
				$this->onRenderThreadAdminControls($modControlSection, $post);
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

	private function onRenderThreadAdminControls(string &$modfunc, Post $post): void {
		[$stickyTitle, $toggleLabel] = $this->getStickyAttributes($post);

		$stickyButtonUrl = $this->generateStickyUrl($post->getThreadUid());

		$modfunc .= '<span class="adminFunctions adminStickyFunction">[<a href="' . htmlspecialchars($stickyButtonUrl) . '" title="' . $stickyTitle . '">'.$toggleLabel.'</a>]</span>';
	}

	private function onRenderThreadWidget(array &$widgetArray, Post &$post): void {
		// get status
		$postStatus = $post->getFlags();

		// get sticky label
		$stickyLabel = $this->generateStickyLabel($postStatus);
		
		// generate sticky url
		$stickyUrl = $this->generateStickyUrl($post->getThreadUid());

		// build the widget entry
		$stickyWidget = $this->buildWidgetEntry(
			$stickyUrl, 
			'sticky', 
			$stickyLabel, 
			''
		);

		// add the widget to the array
		$widgetArray[] = $stickyWidget;
	}

	private function getStickyAttributes(Post $post): array {
		// Create a helper to inspect the post's status flags
		$stickyFlag = $post->getFlags();

		// Determine the title to display based on current sticky state
		$stickyTitle = $this->generateStickyLabel($stickyFlag);

		// Determine the toggle label used in the UI
		$toggleLabel = $stickyFlag->value('sticky') ? 's' : 'S'; 

		// Return both the title and toggle label
		return [$stickyTitle, $toggleLabel];
	}

	private function generateStickyLabel(FlagHelper $postStatus): string {
		// whether the thread is stickied or not
		$isSticky = $postStatus->value('sticky');
		
		// if we're already sticky'd then we need to unsticky
		if($isSticky) {
			return 'Unsticky thread';
		} 
		// not sticky'd - so the action is to sticky
		else {
			return 'Sticky thread';
		}
	}

	private function generateStickyUrl(string $thread_uid): string {
		// generate the sticky thread url
		$url = $this->getModulePageURL(
					[
						'thread_uid' => $thread_uid
					],
					false,
					true
				);
		
		// return url
		return $url;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the sticky img <template> html
		$templateHtml = $this->generateStickyTemplate();

		$this->generateToggleWidget($moduleHeader, 'sticky.js', $templateHtml);
	}
	
	private function generateStickyTemplate(): string {
		// get static url
		$staticUrl = $this->getConfig('STATIC_URL');

		// get the sticky indicator tag
		$stickyIndicator = getStickyIndicator($staticUrl);
		
		//get sticky indicator
		$stickyIconTemplate = $this->generateTemplate('stickyIconTemplate', $stickyIndicator);

		// return template
		return $stickyIconTemplate;
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
		$flags = new FlagHelper($openingPost->getStatus());
		$flags->toggle('sticky');
		$this->moduleContext->postRepository->setPostStatus($openingPost->getUid(), $flags->toString());
	
		// post op number of the thread
		$post_op_number = $threadData->getOpNumber();
	
		// board uid of the thread
		$boardUid = $threadData->getBoardUID();
	
		$this->moduleContext->actionLoggerService->logAction(
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