<?php

namespace Kokonotsuba\Modules\emotes;

/** Swaps :code: emotes for their images in one pass, leaving markup alone. */
final class emoteReplacer {
	/** @var array<string, string> lowercased code => img tag */
	private array $images = [];

	private ?string $pattern = null;

	/** @param array<string, string> $emotes code => image filename */
	public function __construct(array $emotes, string $baseEmoteUrl) {
		$quoted = [];
		foreach ($emotes as $code => $file) {
			$code = (string)$code;
			$key = strtolower($code);
			if ($code === '' || isset($this->images[$key])) {
				continue;
			}
			$this->images[$key] = "<img title=\":$code:\" class=\"emote\" src=\"{$baseEmoteUrl}{$file}\" alt=\":$code:\">";
			$quoted[] = preg_quote($code, '/');
		}
		if ($quoted !== []) {
			// longest first, so a code that starts with another code is not cut short
			usort($quoted, fn(string $a, string $b): int => strlen($b) <=> strlen($a));
			// a tag is matched and put back as it is, so a code inside an attribute is left alone
			$this->pattern = '/<[^>]+>|:(' . implode('|', $quoted) . '):/i';
		}
	}

	public function replace(string $html): string {
		if ($this->pattern === null || !str_contains($html, ':')) {
			return $html;
		}

		return preg_replace_callback($this->pattern, function (array $m): string {
			return isset($m[1]) ? ($this->images[strtolower($m[1])] ?? $m[0]) : $m[0];
		}, $html);
	}
}
