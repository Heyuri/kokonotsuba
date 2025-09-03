<?php

namespace Kokonotsuba\Modules\posterID;

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return userRole::LEV_MODERATOR;
	}

	public function getName(): string {
		return 'Post ID mod tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'PostFormAdmin',
			function(string &$postFormAdminSection) {
				$this->renderPostFormCheckbox($postFormAdminSection);
			}
		);
	}

	private function renderPostFormCheckbox(string &$adminPostFormCheckbox): void {
		$adminPostFormCheckbox .= '
			<span class="postFormAdminFunc overidePostId">
				<label class="filterSelectBoardItem" title="If enabled, your post\'s hash will be generated normally"><input name="formModIdOveride" type="checkbox" value="on">Hide ID</label>
			</span>';
	}
}