<?php

namespace Kokonotsuba\Modules\globalMessage;

// include helper functions
include_once __DIR__ . '/globalMessageLibrary.php';

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

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
		$linkHtml .= '<li class="adminNavLink"><a title="Manage the global warning/message that will appear across all boards" href="' . $this->myPage . '">Manage global message</a></li>';
	}	

	public function ModulePage() {
		$action = $_GET['action'] ?? '';

		if ($action === 'setmessage' && isset($_POST['submit'])) {
			$message = $_POST['content'] ?? '';
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

