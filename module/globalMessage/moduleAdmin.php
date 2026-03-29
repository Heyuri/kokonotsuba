<?php

namespace Kokonotsuba\Modules\globalMessage;

// include helper functions
include_once __DIR__ . '/globalMessageLibrary.php';

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\rebuildAllBoards;

class moduleAdmin extends abstractModuleAdmin {
	private readonly string $myPage;
	private readonly string $globalMessageFile;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_EDIT_GLOBAL_MESSAGE');
    }

	public function getName(): string {
		return 'Global Message management tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}
	
	public function initialize(): void {
		$this->globalMessageFile = $this->getConfig('ModuleSettings.GLOBAL_TXT');

		if(!file_exists($this->globalMessageFile)) touch($this->globalMessageFile);
		
		$this->myPage = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);
	}

	public function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a title="' . _T('admin_nav_global_message_title') . '" href="' . $this->myPage . '">' . _T('admin_nav_global_message') . '</a></li>';
	}	

	public function ModulePage() {
		$action = $this->moduleContext->request->getParameter('action', 'GET', '');

		if ($action === 'setmessage' && $this->moduleContext->request->hasParameter('submit', 'POST')) {
			$message = $this->moduleContext->request->getParameter('content', 'POST', '');
			writeToGlobalMsg($this->globalMessageFile, $message);
			rebuildAllBoards();
		}

		$templateValues = [
			'{$CURRENT_GLOBAL_MESSAGE}' => getCurrentGlobalMsg($this->globalMessageFile),
			'{$MODULE_URL}' => $this->myPage
		];

		$globalMessagePageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('GLOBALMSG_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $globalMessagePageHtml], true);
	}
}

