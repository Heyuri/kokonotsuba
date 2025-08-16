<?php

namespace Kokonotsuba\Modules\viewIp;

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_VIEW_IP_ADDRESS', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'viewIp mod tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);
	}

	private function onRenderPostAdminControls(string &$modControlSection, array &$post): void {
		// Return early if the user is viewing the manage posts screen
		// This is so the IP doesnt show up in the func column
		if($this->isManagePostsRoute()) {
			return;
		}
		
		$postLink = $this->getModulePageURL(['ip_address' => $post['host']], false, true);
		
		// generate the <a> link that shows the IP address and redirect link to manage posts
		$ipButton = '[<a href="' . htmlspecialchars($postLink) . '">' . htmlspecialchars($post['host']) . '</a>]';
		
		// append it to the hook point
		$modControlSection .= $ipButton;
	}

	private function isManagePostsRoute(): bool {
		// Get the mode
		$mode = $_GET['mode'] ?? '';
		
		// Check if its manage posts
		if($mode === 'managePosts') {
			return true;
		} else {
			return false;
		}
	}

	public function ModulePage(): void {
		$boardUrl = $this->moduleContext->board->getBoardURL(true);

		$ip_address = $_GET['ip_address'] ?? '';

		$allBoardUids = [];

		foreach(GLOBAL_BOARD_ARRAY as $board) {
			$allBoardUids[] = $board->getBoardUID();
		}
		
		$boardList = implode(' ', $allBoardUids);
		
		$query = http_build_query(
			[
				'mode' => 'managePosts',
				'ip_address' => $ip_address,
				'board' => $boardList
			]);
		
		$url = $boardUrl . '?' . $query;

		redirect($url);
	}
}