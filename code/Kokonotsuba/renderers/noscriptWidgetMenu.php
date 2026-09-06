<?php

namespace Kokonotsuba\renderers;

/**
 * The bracketed link row a menu falls back to when JavaScript is off.
 *
 * The dropdowns are built client-side from hidden widget refs, so without JS a post or attachment
 * would have no actions at all. This draws the same entries as plain [Label] links. Entries whose
 * href only exists for a JS handler ('#', empty, javascript:) are left out rather than rendered as
 * links that do nothing.
 */
final class noscriptWidgetMenu {
	/**
	 * @param array $widgets Entries as built by abstractModule::buildWidgetEntry().
	 * @return string Space-separated [<a>Label</a>] links, or '' when none has a real href.
	 */
	public static function render(array $widgets): string {
		$links = [];

		foreach ($widgets as $w) {
			$href = trim((string)($w['href'] ?? ''));
			$label = (string)($w['label'] ?? '');

			if (!self::isNavigable($href) || $label === '') {
				continue;
			}

			$targetAttr = '';
			if (!empty($w['params']['target'])) {
				$targetAttr = ' target="' . htmlspecialchars((string)$w['params']['target']) . '"';
			}

			$links[] = '[<a href="' . htmlspecialchars($href) . '"' . $targetAttr . '>' . htmlspecialchars($label) . '</a>]';
		}

		return implode(' ', $links);
	}

	/** Whether a href leads somewhere on its own, without a JS handler. */
	private static function isNavigable(string $href): bool {
		return $href !== '' && $href !== '#' && stripos($href, 'javascript:') !== 0;
	}
}
