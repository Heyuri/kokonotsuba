<?php

namespace Kokonotsuba\Modules\sideNav;

use Kokonotsuba\template\pageRenderer;

use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\sanitizeStr;

/** Draws the side nav from a layout, for the page and for the admin preview. */
class sideNavRenderer {
	/**
	 * @param array $boards listed boards
	 */
	public function __construct(
		private readonly pageRenderer $renderer,
		private readonly array $boards,
		private readonly string $homeUrl,
		private readonly string $overboardUrl,
	) {}

	/** @param array $layout groups with title, kind, id and links (label, url) */
	public function render(array $layout): string {
		$groupsHtml = '';
		foreach ($layout as $group) {
			$items = $group['kind'] === 'boards' ? $this->boardItems() : array_map(function (array $link) {
				$label = sanitizeStr($link['label']);
				return $this->item($label, sanitizeStr($link['url']), $label);
			}, $group['links']);

			if (!$items) {
				continue;
			}

			$groupsHtml .= $this->renderer->ParseBlock('SIDE_NAV_GROUP', [
				'{$GROUP_ID}' => (int) ($group['id'] ?? 0),
				'{$TITLE}' => sanitizeStr($group['title']),
				'{$ITEMS}' => implode('', $items),
			]);
		}

		return $this->nav($groupsHtml);
	}

	/** The same templates with %%TOKEN%% slots, for drawing the nav in the browser. */
	public function templates(): array {
		return [
			'nav' => $this->nav('%%GROUPS%%'),
			'group' => $this->renderer->ParseBlock('SIDE_NAV_GROUP', ['{$GROUP_ID}' => '%%ID%%', '{$TITLE}' => '%%TITLE%%', '{$ITEMS}' => '%%ITEMS%%']),
			'item' => $this->item('%%LABEL%%', '%%URL%%', '%%LINK_TITLE%%'),
		];
	}

	/** Listed boards as plain data, for the browser. */
	public function boardData(): array {
		return array_map(fn($board) => [
			'title' => $board->getBoardTitle(),
			'url' => $board->getBoardURL(),
			'identifier' => $board->getBoardIdentifier(),
		], $this->boards);
	}

	private function nav(string $groupsHtml): string {
		return $this->renderer->ParseBlock('SIDE_NAV', [
			'{$ARIA_LABEL}' => sanitizeStr(_T('sidenav_title')),
			'{$MAIN_LABEL}' => _T('sidenav_main'),
			'{$HOME_URL}' => sanitizeStr($this->homeUrl),
			'{$HOME_LABEL}' => _T('head_home'),
			'{$HIDE_LABEL}' => _T('sidenav_hide'),
			'{$OVERBOARD_URL}' => sanitizeStr($this->overboardUrl),
			'{$OVERBOARD_LABEL}' => _T('sidenav_overboard'),
			'{$STYLE_LABEL}' => _T('sidenav_style'),
			'{$GROUPS}' => $groupsHtml,
		]);
	}

	/** @return string[] */
	private function boardItems(): array {
		// board titles are stored as HTML, like everywhere else they are printed
		return array_map(fn($board) => $this->item(
			$board->getBoardTitle(),
			sanitizeStr($board->getBoardURL()),
			sanitizeStr('/' . $board->getBoardIdentifier() . '/')
		), $this->boards);
	}

	/** All arguments are HTML. */
	private function item(string $label, string $url, string $title): string {
		return $this->renderer->ParseBlock('SIDE_NAV_ITEM', ['{$URL}' => $url, '{$LABEL}' => $label, '{$LINK_TITLE}' => $title]);
	}
}
