<?php

namespace Kokonotsuba\Modules\tripcode;

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;
use staffAccountFromSession;

require __DIR__ . '/capcode_src/capcodeModuleRenderer.php';
require __DIR__ . '/capcode_src/capcodeModuleRequestHandler.php';

class moduleAdmin extends abstractModuleAdmin {
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
			$this->moduleContext->actionLoggerService
		);

		// init module page renderer
		$this->capcodeModuleRenderer = new capcodeModuleRenderer(
			$this,
			$this->moduleContext->board, 
			$this->moduleContext->moduleEngine, 
			$this->moduleContext->capcodeService, 
			$this->moduleContext->adminPageRenderer,
			$this->modulePageUrl
		);

		// add links listener 
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);
	}

	public function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink" title="Manage user capcodes and view staff capcodes"><a href="' . htmlspecialchars($this->modulePageUrl) . '">Manage capcodes</a></li>';
	}

	public function ModulePage(): void {
		// Account session values
		$staffAccountFromSession = new staffAccountFromSession;

		// get staff id and role level
		$accountId = $staffAccountFromSession->getUID();
		
		// request vs draw
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->capcodeModuleRequestHandler->handleModPageRequests($accountId);

			redirect($this->modulePageUrl);
		} 
		else {
			// draw the overview of the user capcodes
			$this->capcodeModuleRenderer->drawModPage();
		}
	}
}