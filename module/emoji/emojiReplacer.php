<?php

namespace Kokonotsuba\Modules\emoji;

use function Puchiko\strings\sanitizeStr;

/** Swaps emoji characters for their images in one pass over the comment. */
final class emojiReplacer {
	/** @var array<string, string> character => img tag */
	private array $map = [];

	/** @param array<string, string> $emojis character => image name */
	public function __construct(array $emojis, string $staticUrl) {
		$baseUrl = sanitizeStr($staticUrl) . 'image/emoji/';
		foreach ($emojis as $char => $name) {
			$char = (string)$char;
			if ($char === '') {
				continue;
			}
			$this->map[$char] = '<img class="emoji" src="' . $baseUrl . sanitizeStr($name) . '.gif" title="' . sanitizeStr($name) . '" alt="' . sanitizeStr($char) . '">';
		}
	}

	public function replace(string $html): string {
		// strtr walks the text once, taking the longest key at each position, so a joined
		// sequence wins over its base character and a replacement is never scanned again
		return $this->map === [] ? $html : strtr($html, $this->map);
	}
}
