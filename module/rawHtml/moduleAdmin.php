<?php

namespace Kokonotsuba\Modules\rawHtml;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_RAW_HTML', userRole::LEV_ADMIN);
	}

	public function getName(): string {
		return 'Raw html insertion mod tool';
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
			function(array &$registInfo) {
				$this->onRegistBegin($registInfo['com']);
			}
		);
	}

	private function renderPostFormCheckbox(string &$adminPostFormCheckbox): void {
		// cookie for whether the checkbox is selected
		$rawHtmlCookie = $this->moduleContext->cookieService->get('rawHtml');

		// string for 'checked'
		$checked = $rawHtmlCookie ? 'checked=""' : '';

		// checkbox html
		$adminPostFormCheckbox .= '
			<span class="postFormAdminFunc rawHtml">
				<label title="Inserts post comment unsanitized. It may potentially break things if you\'re not careful!"><input name="formRawHtml" type="checkbox" value="on" ' . htmlspecialchars($checked) . '>Raw HTML</label>
			</span>';
	}

	private function onRegistBegin(string &$comment): void {
		// Raw HTML checkbox from request
		$formRawHtml = !empty($this->moduleContext->request->getParameter('formRawHtml', 'POST'));

		// if a post has been submitted with it selected, then set the cookie so its persistent
		if($formRawHtml) {
			$this->moduleContext->cookieService->set('rawHtml', '1', time() + 86400, '/');

            // html decode the comment html
            $comment = htmlspecialchars_decode($comment);
		}
		// clear the cookie if it wasn't selected
		else {
			$this->moduleContext->cookieService->delete('rawHtml', '/');
		}
	}
}