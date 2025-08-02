<?php

namespace Kokonotsuba\Modules\rebuild;

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	private readonly string $myPage;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_MANAGE_REBUILD');
    }

	public function getName(): string {
		return 'Rebuild tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->myPage = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);
	}

	public function onRenderLinksAboveBar(&$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a href="' . $this->myPage . '">Manage rebuild</a></li>';
	}

	public function ModulePage() {
		$formSubmit = $_POST['formSubmit'] ?? false;
		if($formSubmit) {
			$boardUIDsToRebuild = $_POST['rebuildBoardUIDs'] ?? false;
			
			$boardsToRebuild = $this->moduleContext->boardService->getBoardsFromUIDs($boardUIDsToRebuild);
			
			rebuildBoardsByArray($boardsToRebuild);

			$moduleUrlForRedirect = $this->getModulePageURL([], false);

			redirect($moduleUrlForRedirect);
			/* Add more things here. TODO: Add thread cache rebuilding when those are added */
		} else {
			$templateValues = [
				'{$REBUILD_CHECK_LIST}' => generateRebuildListCheckboxHTML(GLOBAL_BOARD_ARRAY),
				'{$MODULE_URL}' => $this->myPage];


			$adminRebuildPage = $this->moduleContext->adminPageRenderer->ParseBlock('ADMIN_REBUILD_PAGE', $templateValues);
			echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminRebuildPage], true);
		}
	}
}