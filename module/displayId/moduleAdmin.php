<?php

namespace Kokonotsuba\Modules\displayId;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

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
			$this->getRequiredRole(),
			'PostFormAdmin',
			function(string &$postFormAdminSection) {
				$this->renderPostFormCheckbox($postFormAdminSection);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'RegistBegin',
			function() {
				$this->onRegistBegin();
			}
		);
	}

	private function renderPostFormCheckbox(string &$adminPostFormCheckbox): void {
		// cookie for whether the checkbox is selected
		$overidePostIdCookie = $this->moduleContext->cookieService->get('overidePostId');

		// string for 'checked'
		$checked = $overidePostIdCookie ? 'checked=""' : '';

		// checkbox html
		$adminPostFormCheckbox .= '
			<span class="postFormAdminFunc overidePostId">
				<label title="If enabled, your post\'s hash will be generated normally"><input name="formModIdOveride" type="checkbox" value="on" ' . htmlspecialchars($checked) . '>Hide ID</label>
			</span>';
	}

	private function onRegistBegin(): void {
		// Hide ID checkbox from request
		$formModIdOveride = !empty($this->moduleContext->request->getParameter('formModIdOveride', 'POST'));

		// if a post has been submitted with it selected, then set the cookie so its persistent
		if($formModIdOveride) {
			$this->moduleContext->cookieService->set('overidePostId', '1', time() + 86400, '/');
		}
		// clear the cookie if it wasn't selected
		else {
			$this->moduleContext->cookieService->delete('overidePostId', '/');
		}
	}
}