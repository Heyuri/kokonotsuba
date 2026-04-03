<?php

namespace Kokonotsuba\Modules\rebuild;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\generateRebuildListCheckboxHTML;
use function Kokonotsuba\libraries\rebuildBoardsByArray;
use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

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

		$this->registerLinksAboveBarHook('onRenderLinksAboveBar');
	}

	public function onRenderLinksAboveBar(&$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a title="' . _T('admin_nav_rebuild_multiple_title') . '" href="' . $this->myPage . '">' . _T('admin_nav_rebuild_multiple') . '</a></li>';
	}

	public function ModulePage() {
		$formSubmit = $this->moduleContext->request->getParameter('formSubmit', 'POST', false);
		if($formSubmit) {
			$boardUIDsToRebuild = $this->moduleContext->request->getParameter('rebuildBoardUIDs', 'POST', false);
			
			$boardsToRebuild = $this->moduleContext->boardService->getBoardsFromUIDs($boardUIDsToRebuild);
			
			rebuildBoardsByArray($boardsToRebuild, true);

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