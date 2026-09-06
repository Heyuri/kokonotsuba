<?php

namespace Kokonotsuba\template;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\board\board;
use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\html\drawAdminTheading;
use function Kokonotsuba\libraries\html\generateAdminLinkButtons;
use function Kokonotsuba\libraries\html\generateUserNavBar;
use function Kokonotsuba\libraries\html\getStaffNavCoreEntries;
use function Kokonotsuba\libraries\html\renderStaffNavCoreEntry;

class pageRenderer {
	private templateEngine $templateEngine;
	private moduleEngine $moduleEngine;
	private board $board;

	// depend on templateEngine
	public function __construct(templateEngine $templateEngine, moduleEngine $moduleEngine, IBoard $board, private readonly request $request) {
		$this->templateEngine = $templateEngine;
		$this->moduleEngine = $moduleEngine;
		$this->board = $board;
	}
	
	/**
	 * A template block wrapped in the board's header and footer.
	 *
	 * @param bool $isAdmin       Render the staff nav bar above the block.
	 * @param bool $withStaffHead Emit the staff head a live board gets: the CSRF meta tag, the
	 *                            ModuleAdminHeader hook and the [Moderate] window. Off by default,
	 *                            since most admin pages are tables with nothing to moderate; a page
	 *                            drawing real posts with their deletion checkboxes wants it on.
	 */
	public function ParsePage(string $templateBlock = '', array $templateValues = array(), bool $isAdmin = false, bool $withStaffHead = false): string {
		$htmlOutput = '';
		$thead = '';

		$config = $this->board->loadBoardConfig();

		$liveIndexFile = $config['LIVE_INDEX_FILE'];
		$staticIndexFile = $config['STATIC_INDEX_FILE'];
		
		$htmlOutput .= $this->board->getBoardHead($this->board->getBoardTitle(), 0, $withStaffHead);

		if($isAdmin) {
			// The built-in modes, from the same list the sticky staff nav is built from, so a
			// destination added there shows up in both. Live frontend is left out — the bar
			// below renders it alongside Return.
			$adminLinkHtml = '';
			$coreNavEntries = getStaffNavCoreEntries(
				$liveIndexFile,
				$this->board->getConfigValue('AuthLevels.CAN_VIEW_ACTION_LOG', userRole::LEV_MODERATOR),
				false
			);

			foreach ($coreNavEntries as $coreNavEntry) {
				$adminLinkHtml .= renderStaffNavCoreEntry($coreNavEntry);
			}

			// add the admin links to html output
			$htmlOutput .= generateAdminLinkButtons($liveIndexFile, $staticIndexFile, $this->moduleEngine, $adminLinkHtml, $this->request);

			// add admin threading
			$htmlOutput .= drawAdminTheading($thead, new staffAccountFromSession);
		} else {
			// Reader-facing pages (reports, blotter, ban notice, ...) get the same way back to
			// the board that staff pages get from their nav bar.
			$htmlOutput .= generateUserNavBar($staticIndexFile, $this->request);
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