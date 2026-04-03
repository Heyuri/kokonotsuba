<?php

namespace Kokonotsuba\Modules\tripcode;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\PostControlHooksTrait;
use Kokonotsuba\userRole;
use Kokonotsuba\account\staffAccountFromSession;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\redirect;

require __DIR__ . '/capcode_src/capcodeModuleRenderer.php';
require __DIR__ . '/capcode_src/capcodeModuleRequestHandler.php';

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	// the url of the module page
	private string $modulePageUrl;

	// handles module requests
	private capcodeModuleRequestHandler $capcodeModuleRequestHandler;

	// handles admin page rendering for the module
	private capcodeModuleRenderer $capcodeModuleRenderer;

	public function getRequiredRole(): userRole {
		return $this->getConfig('ModuleSettings.CAN_MANAGE_CAPCODES', userRole::LEV_ADMIN);
	}

	public function getName(): string {
		return 'Capcode management admin tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		// init modulke page url
		$this->modulePageUrl = $this->getModulePageURL([], false);

		// init request handler
		$this->capcodeModuleRequestHandler = new capcodeModuleRequestHandler(
			$this->moduleContext->capcodeService,
			$this->moduleContext->actionLoggerService,
			$this->moduleContext->request
		);

		// init module page renderer
		$this->capcodeModuleRenderer = new capcodeModuleRenderer(
			$this,
			$this->moduleContext->board, 
			$this->moduleContext->moduleEngine, 
			$this->moduleContext->capcodeService, 
			$this->moduleContext->adminPageRenderer,
			$this->modulePageUrl,
			$this->moduleContext->request
		);

		// add links listener 
		$this->registerLinksAboveBarHook('onRenderLinksAboveBar');
	}

	public function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a title="' . _T('admin_nav_capcodes_title') . '" href="' . htmlspecialchars($this->modulePageUrl) . '">' . _T('admin_nav_capcodes') . '</a></li>';
	}

	public function ModulePage(): void {
		// Account session values
		$staffAccountFromSession = new staffAccountFromSession;

		// get staff id and role level
		$accountId = $staffAccountFromSession->getUID();
		
		// request vs draw
		if ($this->moduleContext->request->isPost()) {
			$this->capcodeModuleRequestHandler->handleModPageRequests($accountId);

			redirect($this->modulePageUrl);
		} 
		else {
			// draw the overview of the user capcodes
			$this->capcodeModuleRenderer->drawModPage();
		}
	}
}