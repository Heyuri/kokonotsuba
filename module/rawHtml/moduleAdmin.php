<?php

namespace Kokonotsuba\Modules\rawHtml;

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

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
			$this,
			'PostFormAdmin',
			function(string &$postFormAdminSection) {
				$this->renderPostFormCheckbox($postFormAdminSection);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'RegistBegin',
			function(array &$registInfo) {
				$this->onRegistBegin($registInfo['com']);
			}
		);
	}

	private function renderPostFormCheckbox(string &$adminPostFormCheckbox): void {
		// cookie for whether the checkbox is selected
		$overidePostIdCookie = $_COOKIE['rawHtml'] ?? null;

		// string for 'checked'
		$checked = $overidePostIdCookie ? 'checked=""' : '';

		// checkbox html
		$adminPostFormCheckbox .= '
			<span class="postFormAdminFunc rawHtml">
				<label title="Inserts post comment unsanitized. It may potentially break things if you\'re not careful!"><input name="formRawHtml" type="checkbox" value="on" ' . htmlspecialchars($checked) . '>Raw HTML</label>
			</span>';
	}

	private function onRegistBegin(string &$comment): void {
		// Raw HTML checkbox from request
		$formRawHtml = !empty($_POST['formRawHtml']) ?? null;

		// if a post has been submitted with it selected, then set the cookie so its persistent
		if($formRawHtml) {
			setcookie("rawHtml", "1", time() + 86400, "/");
		}
		// clear the cookie if it wasn't selected
		else {
			setcookie("rawHtml", "", time() - 3600, "/");
		}

        // html decode the comment html
        $comment = htmlspecialchars_decode($comment);
	}
}