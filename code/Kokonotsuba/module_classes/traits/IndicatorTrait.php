<?php

namespace Kokonotsuba\module_classes\traits;

trait IndicatorTrait {
	protected function renderIndicator(string $name, string $content, string $extraClasses = '', bool $hidden = false, string $title = ''): string {
		$classes = 'indicator indicator-' . $name;

		if ($extraClasses !== '') {
			$classes .= ' ' . $extraClasses;
		}

		if ($hidden) {
			$classes .= ' indicatorHidden';
		}

		$titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title) . '"' : '';

		return '<span class="' . $classes . '"' . $titleAttr . '>' . $content . '</span>';
	}
}
