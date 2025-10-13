<?php

namespace Kokonotsuba\Modules\adminDel;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;
use staffAccountFromSession;

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class moduleAdmin extends abstractModuleAdmin {
	private readonly int $JANIMUTE_LENGTH;
	private readonly string $JANIMUTE_REASON;
	private readonly string $GLOBAL_BANS;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_DELETE', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Deletion tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->JANIMUTE_LENGTH = $this->getConfig('ModuleSettings.JANIMUTE_LENGTH');
		$this->JANIMUTE_REASON = $this->getConfig('ModuleSettings.JANIMUTE_REASON');
		$this->GLOBAL_BANS = getBackendGlobalDir() . $this->getConfig('GLOBAL_BANS');

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);
	}

	private function onRenderPostAdminControls(string &$modFunc, array &$post): void {
		// whether the post is deleted or not
		$openFlag = $post['open_flag'] ?? 0;

		// whether it was a file only deletion
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		// don't render anything if the post is already deleted
		if($openFlag && !$onlyFileDeleted) {
			return;
		}
		
		$postUid = $post['post_uid'];
		$muteMinutes = $this->JANIMUTE_LENGTH;
		$plural = $muteMinutes == 1 ? '' : 's';

		$board = searchBoardArrayForBoard($post['boardUID']);
		
		$url = fn(array $params) => $this->getModulePageURL($params, false, true);

		$addControl = function(string $action, string $label, string $title, string $class) use (&$modFunc, $postUid, $url) {
			$buttonUrl = $url(['action' => $action, 'post_uid' => $postUid]);
			$modFunc .= '<span class="adminFunctions ' . htmlspecialchars($class) . '">[<a href="' . htmlspecialchars($buttonUrl) . '" title="' . htmlspecialchars($title) . '">' . htmlspecialchars($label) . '</a>]</span>';
		};

		$addControl('del', 'D', 'Delete', 'adminDeleteFunction');

		if (!empty($post['ext']) && !$onlyFileDeleted) {
			// this check needs to stay inside this if statement or else it'll read from disk for every post
			if($this->moduleContext->FileIO->imageExists($post['tim'] . $post['ext'], $board)) {
				$addControl('imgdel', 'DF', 'Delete file', 'adminDeleteFileFunction');
			}
		}

		$addControl(
			'delmute',
			'DM',
			'Delete and mute for ' . $muteMinutes . ' minute' . $plural,
			'adminDeleteMuteFunction'
		);

	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// can view deleted poss
		$canViewDeleted = getRoleLevelFromSession()->isAtLeast($this->getConfig('AuthLevels.CAN_DELETE_ALL'));

		// add requiredForAll js for the live frontend
		// this js will add the deletedPost/deletedFile classes and deletion indicator to posts on the livefrontend
		if($canViewDeleted) {
			// generate the url path of the deleted posts javascript
			$jsFileUrl = $this->generateJavascriptUrl('postDeletion.js');
		} 
		// otherwise, include the old js for post deletion
		// This just hides the post with css and shows small pop-ups indicating success or faliure
		else {
			// generate old deletion js url
			$jsFileUrl = $this->generateJavascriptUrl('basicPostDeletion.js');
		}

		// generate the script html for including the deleted posts js
		// defer
		$jsHtml = $this->generateScriptHtml($jsFileUrl, true);

		// then append it to the header
		$moduleHeader .= $jsHtml;
	}

	public function ModulePage() {
		$staffAccountFromSession = new staffAccountFromSession;

		$accountId = $staffAccountFromSession->getUID();

		$post = $this->moduleContext->postRepository->getPostByUid($_GET['post_uid']);

		// whether the post has been deleted
		$isDeleted = !empty($post['open_flag']) && empty($post['file_only_deleted']);

		$board = searchBoardArrayForBoard($post['boardUID']);
		
		$boardUID = $board->getBoardUID();

		if (!$post) {
			throw new BoardException('ERROR: That post does not exist.');
		}

		// throw an error if the post was already deleted
		if ($isDeleted) {
			throw new BoardException('Post already deleted!');
		}
		
		switch ($_GET['action']??'') {
			case 'del':
				$this->moduleContext->moduleEngine->dispatch('PostOnDeletion', array($post['post_uid'], 'backend'));
				$this->moduleContext->postService->removePosts([$post['post_uid']], $accountId);
				$this->moduleContext->actionLoggerService->logAction('Deleted post No.'.$post['no'], $boardUID);
				break;
		case 'delmute':
				$this->moduleContext->moduleEngine->dispatch('PostOnDeletion', array($post['post_uid'], 'backend'));
				$this->moduleContext->postService->removePosts([$post['post_uid']], $accountId);
				$ip = $post['host'];
				$starttime = $_SERVER['REQUEST_TIME'];
				$expires = $starttime + intval($this->JANIMUTE_LENGTH) * 60;
				$reason = $this->JANIMUTE_REASON;

				if ($ip) {
					$this->appendGlobalBan($ip, $starttime, $expires, $reason);
				}

				$this->moduleContext->actionLoggerService->logAction('Muted '.$ip.' and deleted post No.'.$post['no'] . ' ' . $board->getBoardTitle() . ' (' . $board->getBoardUID() . ')', GLOBAL_BOARD_UID);

				break;
			case 'imgdel':
				$this->moduleContext->deletedPostsService->deleteFilesFromPosts([$post], $accountId);

				$this->moduleContext->actionLoggerService->logAction('Deleted file for post No.'.$post['no'], $boardUID);
				break;
			default:
				throw new BoardException('ERROR: Invalid action.');
				break;
		}
		// Will be implemented later
		//deleteThreadCache($post['thread_uid']);

		// AJAX first: send JSON, flush to client, then rebuild in the background of this request.
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			// Return JSON for AJAX requests
			header('Content-Type: application/json');
			echo json_encode([
				'success' => true,
				'is_op' => $post['is_op'],
				'deleted_link' => $this->getDeletedPostViewUrl($post)
			]);
		
			// Let the client go on; we keep working server-side.
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_write_close();
			}
			if (function_exists('fastcgi_finish_request')) {
				fastcgi_finish_request();
			} else {
				// Best-effort flush for non-FPM SAPIs
				ob_flush();
				flush();
			}
		
			// ===== rebuild after the response has been sent =====
			// if its a thread, rebuild all board pages
			if ($post['is_op']) {
				$board->rebuildBoard();
			} else {
				// otherwise just rebuild the page the reply is on
				$thread_uid = $post['thread_uid'];
			
				$threads = $this->moduleContext->threadService->getThreadListFromBoard($board);
			
				$pageToRebuild = getPageOfThread($thread_uid, $threads, $board->getConfigValue('PAGE_DEF', 15));

				// make sure it isn't above the static page limit - this prevents a potential DOS vulnerability where a request can trigger a resource intensive operation
				$pageToRebuild = min($pageToRebuild, $this->getConfig('STATIC_HTML_UNTIL'));
			
				$board->rebuildBoardPage($pageToRebuild);
			}
			exit;
		}

		// Non-AJAX fallback: do the rebuild first, then redirect (unchanged)
		if ($post['is_op']) {
			$board->rebuildBoard();
		} else {
			$thread_uid = $post['thread_uid'];
		
			$threads = $this->moduleContext->threadService->getThreadListFromBoard($board);
		
			$pageToRebuild = getPageOfThread($thread_uid, $threads, $board->getConfigValue('PAGE_DEF', 15));

			// make sure it isn't above the static page limit - this prevents a potential DOS vulnerability where a request can trigger a resource intensive operation
			$pageToRebuild = min($pageToRebuild, $this->getConfig('STATIC_HTML_UNTIL'));
		
			$board->rebuildBoardPage($pageToRebuild);
		}

		// Fallback for non-JS users: redirect
		redirect('back', 0);
	}

	private function appendGlobalBan($ip, $starttime, $expires, $reason) {
		$needsNewline = file_exists($this->GLOBAL_BANS) && filesize($this->GLOBAL_BANS) > 0;

		$f = fopen($this->GLOBAL_BANS, 'a');
		if (!$f) {
			return;
		}

		if ($needsNewline) {
			fwrite($f, "\n");
		}

		fwrite($f, "$ip,$starttime,$expires,$reason");
		fclose($f);
	}

	private function getDeletedPostViewUrl(array $post): string {
		// post uid
		$postUid = $post['post_uid'];

		// fetch the deleted post by post uid
		$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowByPostUid($postUid);

		// get the deleted post id for the url
		$deletedPostId = $deletedPost['deleted_post_id'];

		// base url
		$baseUrl = getCurrentUrlNoQuery();

		// parameters for the link
		$urlParameters = [
			'pageName' => 'viewMore',
			'deletedPostId' => $deletedPostId,
			'moduleMode' => 'admin',
			'mode' => 'module',
			'load' => 'deletedPosts'
		];

		// build the query parameters
		$queryParameters = http_build_query($urlParameters);

		// construct the link
		$viewUrl = $baseUrl . '?' . $queryParameters;

		// return the url
		return $viewUrl;
	}
}