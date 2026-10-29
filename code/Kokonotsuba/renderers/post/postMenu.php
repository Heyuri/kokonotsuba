<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\renderers\noscriptWidgetMenu;

/**
 * The dropdown menu on a post, collected as widget entries and drawn once at the end.
 *
 * Entries are kept twice: as the hidden anchor refs static/js/postWidget.js builds the menu
 * from, and as the bracketed [Label] row shown without JavaScript. Entries sharing a group
 * ('edit', 'deletion', ...) are drawn together, even when staff and reader hooks added them.
 */
final class postMenu {
	/** Groups shared across modules, passed as buildWidgetEntry()'s $group. */
	public const GROUP_EDIT = 'edit';
	public const GROUP_DELETION = 'deletion';
	public const GROUP_REPORT = 'report';
	public const GROUP_NOTES = 'notes';

	/** @var array[] */
	private array $widgets = [];

	/** @param array[] $widgets Entries as buildWidgetEntry() shapes them. */
	public function append(array $widgets): void {
		array_push($this->widgets, ...$widgets);
	}

	public function toHtml(): string {
		$widgets = self::grouped($this->widgets);
		$noscript = noscriptWidgetMenu::render($widgets);

		$html = '<div class="postMenu">';

		if ($noscript !== '') {
			$html .= '<noscript><span class="noscriptMenu">' . $noscript . '</span></noscript>';
		}

		$html .= '<a class="menuToggle js-only" role="button" aria-label="Post menu">▶</a>';
		$html .= '<div class="widgetRefs" hidden>' . self::refsHtml($widgets) . '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Pull every entry of a group up to where that group's first entry sits, keeping order
	 * inside the group. Ungrouped entries stay where they were.
	 */
	private static function grouped(array $widgets): array {
		$slots = [];

		foreach ($widgets as $widget) {
			$group = (string)($widget['group'] ?? '');

			if ($group === '') {
				$slots[] = [$widget];
				continue;
			}

			// the group's slot is opened by its first entry
			$key = 'group:' . $group;
			if (!isset($slots[$key])) {
				$slots[$key] = [];
			}
			$slots[$key][] = $widget;
		}

		return array_merge(...array_values($slots));
	}

	/** One empty anchor per entry, its fields carried as data attributes for the JS. */
	private static function refsHtml(array $widgets): string {
		$html = '';

		foreach ($widgets as $widget) {
			$paramAttrs = '';

			foreach ($widget['params'] ?? [] as $key => $value) {
				$paramAttrs .= ' data-param-' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$value) . '"';
			}

			$html .= '<a href="' . htmlspecialchars($widget['href'] ?? '')
				. '" data-action="' . htmlspecialchars($widget['action'] ?? '')
				. '" data-label="' . htmlspecialchars($widget['label'] ?? '')
				. '" data-subMenu="' . htmlspecialchars($widget['subMenu'] ?? '')
				. '"' . $paramAttrs . '></a>';
		}

		return $html;
	}
}
