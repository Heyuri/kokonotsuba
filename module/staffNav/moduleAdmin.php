<?php

namespace Kokonotsuba\Modules\staffNav;

use Kokonotsuba\board\boardRebuilder;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
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

		$topNavSection .= $this->renderBar($this->collectEntries(), $this->getSignedInUser());
	}

	// ─── Building the bar ─────────────────────────────────────────

	/**
	 * Everywhere this staff member can go, normalised.
	 *
	 * @return array<int, array{key: string, label: string, url: string, title: string, group: string, count: int}>
	 */
	private function collectEntries(): array {
		// The board's own live frontend, spelled out in full: the bar shows up on thread pages
		// and the overboard too, where the bare "koko.php" the admin bar links to would resolve
		// against the wrong directory.
		$entries = getStaffNavCoreEntries(
			$this->moduleContext->board->getBoardURL(true),
			$this->getConfig('AuthLevels.CAN_VIEW_ACTION_LOG', userRole::LEV_MODERATOR)
		);

		$this->moduleContext->moduleEngine->dispatch('StaffNavLinks', [&$entries]);

		$normalised = [];
		foreach ($entries as $entry) {
			$normalised[] = [
				'key' => (string) ($entry['key'] ?? ''),
				'label' => (string) ($entry['label'] ?? ''),
				'url' => (string) ($entry['url'] ?? ''),
				'title' => (string) ($entry['title'] ?? ''),
				'group' => (string) ($entry['group'] ?? ''),
				'count' => max(0, (int) ($entry['count'] ?? 0)),
			];
		}

		return $normalised;
	}

	/**
	 * The whole bar, from the same blocks the client builds its own from.
	 *
	 * @param array      $entries Entries from collectEntries().
	 * @param array|null $user    Signed-in staff member, or null.
	 */
	private function renderBar(array $entries, ?array $user): string {
		$itemsHtml = '';

		foreach ($this->groupEntries($entries) as $slot) {
			$itemsHtml .= isset($slot['group'])
				? $this->renderGroup($slot['group'], $slot['entries'])
				: $this->renderItem($slot['entry']);
		}

		return $this->moduleContext->adminPageRenderer->ParseBlock('STAFF_NAV_BAR', [
			'{$ARIA_LABEL}' => sanitizeStr(_T('staffnav_title')),
			'{$ITEMS}' => $itemsHtml,
			'{$USER_NAME}' => $user === null ? '' : sanitizeStr($user['name']),
			'{$USER_TITLE}' => $user === null ? '' : sanitizeStr($user['title']),
			'{$USER_CLASS}' => $user === null ? 'indicatorHidden' : '',
		]);
	}

	/**
	 * Entries in the order they should appear: the plain links first, in the order they were
	 * contributed, then every drop-down together at the end.
	 *
	 * Keeping the drop-downs in one run means the bar reads as links then menus, rather than
	 * links and menus alternating wherever a module happened to be loaded.
	 *
	 * @return array<int, array{entry?: array, group?: string, entries?: array}>
	 */
	private function groupEntries(array $entries): array {
		$links = [];
		$groups = [];

		foreach ($entries as $entry) {
			$group = $entry['group'];

			if ($group === '') {
				$links[] = ['entry' => $entry];
				continue;
			}

			if (!isset($groups[$group])) {
				$groups[$group] = ['group' => $this->translateGroup($group), 'entries' => []];
			}

			$groups[$group]['entries'][] = $entry;
		}

		return array_merge($links, array_values($groups));
	}

	private function renderItem(array $entry): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('STAFF_NAV_ITEM', [
			'{$URL}' => sanitizeStr($entry['url']),
			'{$LINK_TITLE}' => sanitizeStr($entry['title']),
			'{$LABEL}' => sanitizeStr($entry['label']),
			'{$COUNT}' => '(' . $entry['count'] . ')',
			'{$COUNT_CLASS}' => $entry['count'] === 0 ? 'indicatorHidden' : '',
		]);
	}

	/** A drop-up, tallying what is waiting behind the entries it hides. */
	private function renderGroup(string $groupLabel, array $entries): string {
		$itemsHtml = '';
		$total = 0;

		foreach ($entries as $entry) {
			$total += $entry['count'];
			$itemsHtml .= $this->renderItem($entry);
		}

		return $this->moduleContext->adminPageRenderer->ParseBlock('STAFF_NAV_GROUP', [
			'{$LABEL}' => sanitizeStr($groupLabel),
			'{$ITEMS}' => $itemsHtml,
			'{$COUNT}' => '(' . $total . ')',
			'{$COUNT_CLASS}' => $total === 0 ? 'indicatorHidden' : '',
		]);
	}

	/**
	 * Who the bar says you are signed in as: the plain name and role, "hachikuji (Admin)".
	 *
	 * @return array{name: string, title: string}|null Null when nobody is signed in.
	 */
	private function getSignedInUser(): ?array {
		$staffAccount = $this->moduleContext->staffAccountFromSession;
		$role = $staffAccount->getRoleLevel();

		if ($role === userRole::LEV_NONE) {
			return null;
		}

		$username = $staffAccount->getUsername();
		$roleName = $role->displayRoleName();

		return [
			'name' => _T('staffnav_user', $username, $roleName),
			'title' => _T('admin_logged_in_as', $username, $roleName),
		];
	}

	/**
	 * A group key's display name.
	 *
	 * _T() hands back the key it was given when there is no translation for it, which is how an
	 * unknown group falls back to its own name rather than showing a raw language key.
	 */
	private function translateGroup(string $group): string {
		if ($group === '') {
			return '';
		}

		$languageKey = 'staffnav_group_' . $group;
		$label = _T($languageKey);

		return $label === $languageKey ? $group : $label;
	}
}
