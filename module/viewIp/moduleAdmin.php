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
		$postLink = $this->getModulePageURL(['ip_addess' => $post['host']]);
		
		$ipButton = '[<a href="' . $postLink . '">' . htmlspecialchars($post['host']) . '</a>]';
		
		$modControlSection .= $ipButton;
	}

	public function ModulePage(): void {
		$boardUrl = $this->moduleContext->board->getBoardURL(true);

		$ip_address = $_GET['ip_address'] ?? '';

		$query = http_build_query(['mode' => 'managePosts', 'ip_address' => $ip_address]);
		
		$url = $boardUrl . '?' . $query;

		redirect($url);
	}
}