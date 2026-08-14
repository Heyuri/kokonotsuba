<?php

namespace Kokonotsuba\Modules\displayId;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostFormAdminListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;
use Kokonotsuba\userRole;

class moduleAdmin extends abstractModuleAdmin {
	use PostFormAdminListenerTrait, RegistBeginListenerTrait;

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
		$this->listenPostFormAdmin('renderPostFormCheckbox');

		$this->listenRegistBegin('onRegistBegin');
	}

	private function renderPostFormCheckbox(string &$adminPostFormCheckbox): void {
		// cookie for whether the checkbox is selected
		$showModIdCookie = $this->moduleContext->cookieService->get('showModId');

		// string for 'checked'
		$checked = $showModIdCookie ? 'checked=""' : '';

		// checkbox html
		$adminPostFormCheckbox .= '
			<span class="postFormAdminFunc showModId">
				<label title="If enabled, your post\'s ID will show your staff rank instead of a normally generated hash"><input name="formShowModId" type="checkbox" value="on" ' . htmlspecialchars($checked) . '>Show mod ID</label>
			</span>';
	}

	private function onRegistBegin(): void {
		// Show mod ID checkbox from request
		$formShowModId = !empty($this->moduleContext->request->getParameter('formShowModId', 'POST'));

		// if a post has been submitted with it selected, then set the cookie so its persistent
		if($formShowModId) {
			$this->moduleContext->cookieService->set('showModId', '1', time() + 86400, '/');
		}
		// clear the cookie if it wasn't selected
		else {
			$this->moduleContext->cookieService->delete('showModId', '/');
		}
	}
}