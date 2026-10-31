<?php

namespace Kokonotsuba\Modules\staffNav;

require_once __DIR__ . '/staffNavRenderer.php';

use Kokonotsuba\board\boardRebuilder;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\html\getFrontAdminNavEntriesFromConfig;
use function Kokonotsuba\libraries\html\getStaffNavCoreEntries;
use function Puchiko\strings\sanitizeStr;

/**
 * The sticky staff nav.
 *
 * A bar at the top of the nav section, above the board list, carrying everything the nav above
 * admin pages carries (minus Return, which is what the board itself is), so staff can reach the
 * admin pages from wherever they are — a board page, a thread, a module page, the admin panel.
 * Whether the section sticks to the top of the viewport is the Persistent navigation setting's
 * business, not this module's.
 *
 * The destinations are the built-in modes (getStaffNavCoreEntries) plus whatever modules answer
 * the 'StaffNavLinks' hook with; a module may file its entry under a drop-down by naming a group.
 *
 * It is rendered into the page, from the TopNavSection hook, on every page PHP renders for a
 * signed-in staff member — the client is never asked to put it there. The one exception is HTML
 * being written to a static file: that file is served to everyone, so a bar baked into it would
 * show readers what the staff nav holds and show a janitor whatever the admin who rebuilt the
 * board can see. Those pages get nothing, not even the stylesheet.
 *
 * The bar carries js-only, so it stays a scripted feature: without JS it never appears rather
 * than appearing as a bar whose drop-downs cannot open.
 */
class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	/** Anyone with a staff session gets the bar; each entry is gated by whoever contributed it. */
	public function getRequiredRole(): userRole {
		return userRole::LEV_USER;
	}

	public function getName(): string {
		return 'Staff navigation bar';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		$this->listenProtected('ModuleHeader', function (string &$moduleHeader) {
			$this->onGenerateModuleHeader($moduleHeader);
		});

		$this->listenProtected('TopNavSection', function (string &$topNavSection) {
			$this->onRenderTopNavSection($topNavSection);
		});
	}

	/** Whether this page is being rendered for the staff member who asked for it. */
	private function servesThisReader(): bool {
		return !boardRebuilder::isRenderingStaticHtml();
	}

	/** The bar's stylesheet, and the script that opens its drop-downs. */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		if (!$this->servesThisReader()) {
			return;
		}

		$moduleHeader .= '<link rel="stylesheet" href="'
			. sanitizeStr($this->getConfig('STATIC_URL') . 'css/module/staffNav.css') . '">';

		$this->includeScript('staffNav.js?v=11', $moduleHeader);
	}

	/** The finished bar, above the board list, on every page rendered for a signed-in staff member. */
	private function onRenderTopNavSection(string &$topNavSection): void {
		if (!$this->servesThisReader()) {
			return;
		}

		$renderer = new staffNavRenderer($this->moduleContext->adminPageRenderer);
		$topNavSection .= $renderer->render($this->collectEntries(), $this->moduleContext->staffAccountFromSession);
	}

	/** Everywhere this staff member can go: the built-in modes, then module entries. */
	private function collectEntries(): array {
		// The board's own live frontend, spelled out in full: the bar shows up on thread pages
		// and the overboard too, where the bare "koko.php" the admin bar links to would resolve
		// against the wrong directory.
		$entries = getStaffNavCoreEntries(
			$this->moduleContext->board->getBoardURL(true),
			$this->getConfig('AuthLevels.CAN_VIEW_ACTION_LOG', userRole::LEV_MODERATOR),
			frontAdminEntries: getFrontAdminNavEntriesFromConfig($this->moduleContext->board->loadBoardConfig())
		);

		$this->moduleContext->moduleEngine->dispatch('StaffNavLinks', [&$entries]);

		return $entries;
	}
}
