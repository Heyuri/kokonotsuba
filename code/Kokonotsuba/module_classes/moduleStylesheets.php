<?php

namespace Kokonotsuba\module_classes;

use function Puchiko\strings\sanitizeStr;

/**
 * Module stylesheets collected while the ModuleHeader hooks run and printed right after
 * globalBase.css, so a theme sheet still overrides a module rule exactly as it did when the
 * rule lived in globalBase.css. One link per url however many pages a request draws.
 */
final class moduleStylesheets {
	/** @var array<string, true> */
	private static array $urls = [];

	public static function add(string $url): void {
		self::$urls[$url] = true;
	}

	public static function html(): string {
		$html = '';

		foreach (array_keys(self::$urls) as $url) {
			$html .= '<link rel="stylesheet" href="' . sanitizeStr($url) . '">' . "\n\t";
		}

		return $html;
	}
}
