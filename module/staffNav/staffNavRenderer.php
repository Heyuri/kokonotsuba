<?php

namespace Kokonotsuba\Modules\staffNav;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Puchiko\strings\sanitizeStr;

/**
 * Draws the staff nav bar from its entries, on boards (moduleAdmin) and the front page (moduleFront).
 * Needs the STAFF_NAV_* templates from this module's templates/ on the renderer's search path.
 */
final class staffNavRenderer {
	public function __construct(
		private readonly pageRenderer|templateEngine $renderer,
	) {}

	/**
	 * The whole bar, from the same blocks the client builds its own from.
	 *
	 * @param array $entries Entries in the StaffNavLinks shape.
	 */
	public function render(array $entries, staffAccountFromSession $staffAccount): string {
		$user = $this->signedInUser($staffAccount);
		$itemsHtml = '';

		foreach ($this->groupEntries($this->normalise($entries)) as $slot) {
			$itemsHtml .= isset($slot['group'])
				? $this->renderGroup($slot['group'], $slot['entries'])
				: $this->renderItem($slot['entry']);
		}

		return $this->renderer->ParseBlock('STAFF_NAV_BAR', [
			'{$ARIA_LABEL}' => sanitizeStr(_T('staffnav_title')),
			'{$ITEMS}' => $itemsHtml,
			'{$USER_NAME}' => $user === null ? '' : sanitizeStr($user['name']),
			'{$USER_TITLE}' => $user === null ? '' : sanitizeStr($user['title']),
			'{$USER_CLASS}' => $user === null ? 'indicatorHidden' : '',
		]);
	}

	/**
	 * @return array<int, array{key: string, label: string, url: string, title: string, group: string, count: int, method: string}>
	 */
	private function normalise(array $entries): array {
		$normalised = [];
		foreach ($entries as $entry) {
			$normalised[] = [
				'key' => (string) ($entry['key'] ?? ''),
				'label' => (string) ($entry['label'] ?? ''),
				'url' => (string) ($entry['url'] ?? ''),
				'title' => (string) ($entry['title'] ?? ''),
				'group' => (string) ($entry['group'] ?? ''),
				'count' => max(0, (int) ($entry['count'] ?? 0)),
				'method' => (string) ($entry['method'] ?? 'get'),
			];
		}

		return $normalised;
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
		$isPost = $entry['method'] === 'post';

		return $this->renderer->ParseBlock($isPost ? 'STAFF_NAV_FORM_ITEM' : 'STAFF_NAV_ITEM', [
			'{$URL}' => sanitizeStr($entry['url']),
			'{$LINK_TITLE}' => sanitizeStr($entry['title']),
			'{$LABEL}' => sanitizeStr($entry['label']),
			'{$COUNT}' => '(' . $entry['count'] . ')',
			'{$COUNT_CLASS}' => $entry['count'] === 0 ? 'indicatorHidden' : '',
			'{$CSRF_TOKEN}' => $isPost ? getCsrfHiddenInput() : '',
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

		return $this->renderer->ParseBlock('STAFF_NAV_GROUP', [
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
	private function signedInUser(staffAccountFromSession $staffAccount): ?array {
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
