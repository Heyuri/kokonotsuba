<?php

namespace Kokonotsuba\Modules\adminDel;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

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
			$this,
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);
	}

	private function onRenderPostAdminControls(string &$modFunc, array &$post): void {
		$postUid = $post['post_uid'];
		$muteMinutes = $this->JANIMUTE_LENGTH;
		$plural = $muteMinutes == 1 ? '' : 's';

		$board = searchBoardArrayForBoard($post['boardUID']);
		
		$url = fn(array $params) => $this->getModulePageURL($params);

		$addControl = function(string $action, string $label, string $title, string $class) use (&$modFunc, $postUid, $url) {
			$buttonUrl = $url(['action' => $action, 'post_uid' => $postUid]);
			$modFunc .= '<span class="adminFunctions ' . $class . '">[<a href="' . $buttonUrl . '" title="' . $title . '">' . $label . '</a>]</span>';
		};

		$addControl('del', 'D', 'Delete', 'adminDeleteFunction');

		if (!empty($post['ext'])) {
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
	
	public function ModulePage() {
		$post = $this->moduleContext->postRepository->getPostByUid($_GET['post_uid']);
		
		$board = searchBoardArrayForBoard($post['boardUID']);
		
		$boardUID = $board->getBoardUID();

		if (!$post) {
			throw new BoardException('ERROR: That post does not exist.');
		}
		
		switch ($_GET['action']??'') {
			case 'del':
				$this->moduleContext->moduleEngine->dispatch('PostOnDeletion', array($post['post_uid'], 'backend'));
				$this->moduleContext->postService->removePosts([$post['post_uid']]);
				$this->moduleContext->actionLoggerService->logAction('Deleted post No.'.$post['no'], $boardUID);
				break;
		case 'delmute':
				$this->moduleContext->moduleEngine->dispatch('PostOnDeletion', array($post['post_uid'], 'backend'));
				$this->moduleContext->postService->removePosts([$post['post_uid']]);
				$ip = $post['host'];
				$starttime = $_SERVER['REQUEST_TIME'];
				$expires = $starttime + intval($this->JANIMUTE_LENGTH) * 60;
				$reason = $this->JANIMUTE_REASON;

				if ($ip) {
					$this->appendGlobalBan($ip, $starttime, $expires, $reason);
				}

				$this->moduleContext->actionLoggerService->logAction('Muted '.$ip.' and deleted post No.'.$post['no'], $boardUID);

				break;
			case 'imgdel':
				$this->moduleContext->attachmentService->removeAttachments([$post['post_uid']]);

				$postStatus = new FlagHelper($post['status']);
				$postStatus->toggle('fileDeleted');

				$this->moduleContext->postRepository->setPostStatus($post['post_uid'], $postStatus->toString());

				$this->moduleContext->actionLoggerService->logAction('Deleted file for post No.'.$post['no'], $boardUID);
				break;
			default:
				throw new BoardException('ERROR: Invalid action.');
				break;
		}
		// Will be implemented later
		//deleteThreadCache($post['thread_uid']);

		// if its a thread, rebuild all board pages
		if($post['is_op']) {
			$board->rebuildBoard();
		} else {
			// otherwise just rebuild the page the reply is on
			$thread_uid = $post['thread_uid'];

			$threads = $this->moduleContext->threadService->getThreadListFromBoard($board);

			$pageToRebuild = getPageOfThread($thread_uid, $threads, $board->getConfigValue('PAGE_DEF', 15));
			
			$board->rebuildBoardPage($pageToRebuild);
		}
		
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			// Return JSON for AJAX requests
			header('Content-Type: application/json');
			echo json_encode([
				'success' => true,
				'is_op' => $post['is_op']
			]);
			exit;
		} else {
			// Fallback for non-JS users: redirect
			redirect('back', 0);
		}

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
}