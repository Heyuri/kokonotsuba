<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\renderers\noscriptWidgetMenu;

/**
 * The dropdown menu on a post, collected as widget entries and drawn once at the end.
 *
 * Entries are kept twice: as the hidden anchor refs static/js/postWidget.js builds the menu
 * from, and as the bracketed [Label] row shown without JavaScript.
 */
final class postMenu {
	private string $refs = '';
	private string $noscript = '';

	/** @param array[] $widgets Entries as buildWidgetEntry() shapes them. */
	public function append(array $widgets): void {
		$this->refs .= self::refsHtml($widgets);

		$noscript = noscriptWidgetMenu::render($widgets);

		if ($noscript !== '') {
			$this->noscript .= ($this->noscript === '' ? '' : ' ') . $noscript;
		}
	}

	public function toHtml(): string {
		$html = '<div class="postMenu">';

		if ($this->noscript !== '') {
			$html .= '<noscript><span class="noscriptMenu">' . $this->noscript . '</span></noscript>';
		}

		$html .= '<a class="menuToggle js-only" role="button" aria-label="Post menu">▶</a>';
		$html .= '<div class="widgetRefs" hidden>' . $this->refs . '</div>';
		$html .= '</div>';

		return $html;
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
