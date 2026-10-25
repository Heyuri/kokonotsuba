<?php

namespace Kokonotsuba\Modules\sideNav;

use Kokonotsuba\template\pageRenderer;

use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\sanitizeStr;

/**
 * Draws the side nav from a layout, for the page and for the admin preview.
 *
 * A 'boards' group lists every listed board and a 'main' group the home, overboard and hide nav
 * entries. Those are automatic links: a stored row with a source ('board:{uid}', 'home', ...) only
 * overrides one (its label, position or hidden flag), and any without a row is appended.
 */
class sideNavRenderer {
	/** A main group's automatic links, in their default order. */
	private const MAIN_SOURCES = ['home', 'overboard', 'hide'];

	/**
	 * @param array $boards listed boards
	 * @param string[] $styleNames the board's style names, the default first
	 */
	public function __construct(
		private readonly pageRenderer $renderer,
		private readonly array $boards,
		private readonly string $homeUrl,
		private readonly string $overboardUrl,
		private readonly array $styleNames,
	) {}

	/** @param array $layout groups with title, kind, id and links (label, url, source, hidden) */
	public function render(array $layout): string {
		$groupsHtml = '';
		foreach ($this->resolve($layout) as $group) {
			$items = array_map([$this, 'linkItem'], array_filter($group['links'], fn(array $link) => empty($link['hidden'])));
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

	/**
	 * The layout with each group's automatic links filled in: stored ones get their defaults, ones
	 * for a board no longer listed are dropped, and missing ones are appended under an "auto-" key.
	 * Every link gets a key (its id when stored). Running it twice changes nothing.
	 */
	public function resolve(array $layout): array {
		$boards = [];
		foreach ($this->boards as $board) {
			$boards['board:' . $board->getBoardUID()] = $board;
		}

		foreach ($layout as $g => $group) {
			$expected = match ($group['kind']) {
				'boards' => array_keys($boards),
				'main' => self::MAIN_SOURCES,
				default => [],
			};

			$links = [];
			$present = [];
			foreach ($group['links'] as $link) {
				$link['key'] ??= (string) $link['id'];
				$source = $link['source'] ?? null;
				if ($source !== null) {
					if (!in_array($source, $expected, true) || isset($present[$source])) {
						continue;
					}
					$present[$source] = true;
					$link += $this->sourceDefaults($source, $boards);
				}
				$links[] = $link;
			}

			foreach ($expected as $source) {
				if (!isset($present[$source])) {
					$links[] = ['key' => 'auto-' . str_replace(':', '-', $source), 'id' => null, 'source' => $source, 'label' => '', 'url' => '', 'hidden' => 0]
						+ $this->sourceDefaults($source, $boards);
				}
			}

			$layout[$g]['links'] = $links;
		}

		return $layout;
	}

	/** The same templates with %%TOKEN%% slots, for drawing the nav in the browser. */
	public function templates(): array {
		return [
			'nav' => $this->nav('%%GROUPS%%'),
			'group' => $this->renderer->ParseBlock('SIDE_NAV_GROUP', ['{$GROUP_ID}' => '%%ID%%', '{$TITLE}' => '%%TITLE%%', '{$ITEMS}' => '%%ITEMS%%']),
			'item' => $this->item('%%LABEL%%', '%%URL%%', '%%LINK_TITLE%%'),
			'hide' => $this->renderer->ParseBlock('SIDE_NAV_HIDE_ITEM', ['{$LABEL}' => '%%LABEL%%']),
		];
	}

	/**
	 * An automatic link's defaults. default_label and link_title are HTML (board titles are stored
	 * as HTML), default_url is plain.
	 */
	private function sourceDefaults(string $source, array $boards): array {
		return match ($source) {
			'home' => ['default_label' => _T('head_home'), 'default_url' => $this->homeUrl, 'link_title' => _T('head_home')],
			'overboard' => ['default_label' => _T('sidenav_overboard'), 'default_url' => $this->overboardUrl, 'link_title' => _T('sidenav_overboard')],
			'hide' => ['default_label' => _T('sidenav_hide'), 'default_url' => '', 'link_title' => _T('sidenav_hide')],
			default => [
				'default_label' => $boards[$source]->getBoardTitle(),
				'default_url' => $boards[$source]->getBoardURL(),
				'link_title' => sanitizeStr('/' . $boards[$source]->getBoardIdentifier() . '/'),
			],
		};
	}

	private function linkItem(array $link): string {
		$source = $link['source'] ?? null;
		$label = $link['label'] !== '' ? sanitizeStr($link['label']) : ($link['default_label'] ?? '');

		if ($source === 'hide') {
			return $this->renderer->ParseBlock('SIDE_NAV_HIDE_ITEM', ['{$LABEL}' => $label]);
		}
		if ($source !== null) {
			return $this->item($label, sanitizeStr($link['default_url']), str_starts_with($source, 'board:') ? $link['link_title'] : $label);
		}
		return $this->item($label, sanitizeStr($link['url']), $label);
	}

	private function nav(string $groupsHtml): string {
		return $this->renderer->ParseBlock('SIDE_NAV', [
			'{$ARIA_LABEL}' => sanitizeStr(_T('sidenav_title')),
			'{$STYLE_LABEL}' => _T('sidenav_style'),
			'{$STYLE_OPTIONS}' => $this->styleOptions(),
			'{$GROUPS}' => $groupsHtml,
		]);
	}

	/** Options as the footer selector lists them: Random, the board's styles, then the header's Import custom sheet. */
	private function styleOptions(): string {
		$options = '';
		foreach (['Random', ...$this->styleNames, 'Import custom'] as $i => $name) {
			$name = sanitizeStr($name);
			$options .= '<option value="' . $name . '"' . ($i === 1 ? ' selected' : '') . '>' . $name . '</option>';
		}
		return $options;
	}

	/** All arguments are HTML. */
	private function item(string $label, string $url, string $title): string {
		return $this->renderer->ParseBlock('SIDE_NAV_ITEM', ['{$URL}' => $url, '{$LABEL}' => $label, '{$LINK_TITLE}' => $title]);
	}
}
