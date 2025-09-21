<?php

use Kokonotsuba\Root\Constants\userRole;

class pageRenderer {
	private ?templateEngine $templateEngine;
	private moduleEngine $moduleEngine;
	private board $board;

	// depend on templateEngine
	public function __construct(?templateEngine $templateEngine, moduleEngine $moduleEngine, IBoard $board) {
		$this->templateEngine = $templateEngine;
		$this->moduleEngine = $moduleEngine;
		$this->board = $board;
	}
	
	// get template block content with header and footer
	public function ParsePage(string $templateBlock = '', array $templateValues = array(), bool $isAdmin = false): string {
		$htmlOutput = '';
		$thead = '';

		$config = $this->board->loadBoardConfig();

		$liveIndexFile = $config['LIVE_INDEX_FILE'];
		$staticIndexFile = $config['STATIC_INDEX_FILE'];
		
		$htmlOutput .= $this->board->getBoardHead($this->board->getBoardTitle());

		if($isAdmin) {
			// admin link html
			// add hard-coded modes
			// there'll be a more modular way (that doesn't require modules) later 
			$adminLinkHtml = '';
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'actionLog', 'Action log', $this->board->getConfigValue('AuthLevels.CAN_VIEW_ACTION_LOG', userRole::LEV_MODERATOR));
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'account', 'Accounts', userRole::LEV_USER);
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'managePosts', 'Manage posts', userRole::LEV_JANITOR);
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'rebuild', 'Rebuild board', userRole::LEV_JANITOR);
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'boards', 'Boards', userRole::LEV_ADMIN);

			// add the admin links to html output
			$htmlOutput .= generateAdminLinkButtons($liveIndexFile, $staticIndexFile, $this->moduleEngine, $adminLinkHtml);

			// add admin threading
			$htmlOutput .= drawAdminTheading($thead, new staffAccountFromSession);
		}

		$htmlOutput .= $this->templateEngine->ParseBlock($templateBlock, $templateValues);

		$htmlOutput .= $this->board->getBoardFooter(false);

		return $htmlOutput;
	}

	/* parse block - so you dont need to call an instance of templateEngine on its own when using pageRenderer */
	public function ParseBlock(string $templateBlock = '', array $templateValues = array()) {
		$htmlOutput = '';

		$htmlOutput .= $this->templateEngine->ParseBlock($templateBlock, $templateValues);
	
		return $htmlOutput;
	}

	public function setTemplate(string $templateName): void {
		// set the template that templateEngine is using to the specified
		$this->templateEngine->setTemplateFile($templateName);
	}
}