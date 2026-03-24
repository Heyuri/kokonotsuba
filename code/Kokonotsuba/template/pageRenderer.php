<?php

namespace Kokonotsuba\template;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\board\board;
use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawAdminTheading;
use function Kokonotsuba\libraries\html\generateAdminLinkButtons;
use function Kokonotsuba\libraries\html\generateAdminNavLink;

class pageRenderer {
	private templateEngine $templateEngine;
	private moduleEngine $moduleEngine;
	private board $board;

	// depend on templateEngine
	public function __construct(templateEngine $templateEngine, moduleEngine $moduleEngine, IBoard $board) {
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
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'actionLog', _T('admin_nav_action_log'), $this->board->getConfigValue('AuthLevels.CAN_VIEW_ACTION_LOG', userRole::LEV_MODERATOR), _T('admin_nav_action_log_title'));
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'account', _T('admin_nav_accounts'), userRole::LEV_USER, _T('admin_nav_accounts_title'));
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'managePosts', _T('admin_nav_posts'), userRole::LEV_JANITOR, _T('admin_nav_posts_title'));
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'rebuild', _T('admin_nav_rebuild'), userRole::LEV_JANITOR, _T('admin_nav_rebuild_title'));
			$adminLinkHtml .= generateAdminNavLink($liveIndexFile, 'boards', _T('admin_nav_boards'), userRole::LEV_ADMIN, _T('admin_nav_boards_title'));

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