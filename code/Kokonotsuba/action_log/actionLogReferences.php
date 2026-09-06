<?php

namespace Kokonotsuba\action_log;

/**
 * The things a log line points at, and how to reach them.
 *
 * A log line marks whatever it is talking about with a reference token - reference('ban', 12,
 * 'ban #12') - and whoever owns that kind of thing registers a resolver for it, which is what
 * turns the token into a link when the action log is drawn. A reference nothing resolves renders
 * as the label it was written with, so an entry outlives the module that wrote it.
 */
class actionLogReferences {
	/** A reference as it is stored: {{kind:id|label}}. */
	private const TOKEN_PATTERN = '/\{\{([a-z0-9_.-]+):([A-Za-z0-9_.-]+)\|([^{}|]*)\}\}/';

	/** @var array<string, callable(string, int): ?string> */
	private array $resolvers = [];

	/** @var array<string, string> Kind => regex whose first group is the id. */
	private array $patterns = [];

	/**
	 * Build a reference to embed in a log line.
	 *
	 * Falls back to the bare label when the kind or the id cannot be written as a token, so a
	 * caller never has to check.
	 */
	public static function reference(string $kind, int|string $id, string $label): string {
		$kind = strtolower(trim($kind));
		$id = trim((string) $id);

		// The label is the fallback text, so anything that would break the token comes out of it
		$label = trim(str_replace(['{', '}', '|'], '', $label));

		if (!preg_match('/^[a-z0-9_.-]+$/', $kind) || !preg_match('/^[A-Za-z0-9_.-]+$/', $id) || $label === '') {
			return $label;
		}

		return '{{' . $kind . ':' . $id . '|' . $label . '}}';
	}

	/**
	 * Say where a kind of reference points.
	 *
	 * @param callable(string, int): ?string $resolver Given the id and the entry's board UID,
	 *                                                 returns a URL, or null for no link.
	 */
	public function register(string $kind, callable $resolver): void {
		$this->resolvers[strtolower(trim($kind))] = $resolver;
	}

	/**
	 * Link something the log writes as prose rather than as a token, so entries already in the
	 * table become clickable too.
	 *
	 * @param string $pattern Regex matching the whole phrase, with the id in its first group.
	 */
	public function registerPattern(string $kind, string $pattern): void {
		$this->patterns[strtolower(trim($kind))] = $pattern;
	}

	public function has(string $kind): bool {
		return isset($this->resolvers[strtolower(trim($kind))]);
	}

	/** One log line as HTML: escaped throughout, with resolvable references turned into links. */
	public function toHtml(string $text, int $boardUid): string {
		// Escaping first leaves the token syntax intact - none of {{ : | }} is escaped - so the
		// label is already safe by the time it is put in the anchor.
		$escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		// Split on the tokens so the prose patterns only ever see prose: a label that happens to
		// read like one is left alone, and neither pass can match inside the other's anchor.
		$parts = preg_split(self::TOKEN_PATTERN, $escaped, -1, PREG_SPLIT_DELIM_CAPTURE);
		$html = '';

		// each token contributes its three captures after the text that preceded it
		for ($i = 0; $i < count($parts); $i += 4) {
			$html .= $this->linkPatterns($parts[$i], $boardUid);

			if (!isset($parts[$i + 3])) {
				break;
			}

			$html .= $this->link($parts[$i + 1], $parts[$i + 2], $parts[$i + 3], $boardUid);
		}

		return $html;
	}

	/** One reference as an anchor, or its bare label when nothing resolves it. */
	private function link(string $kind, string $id, string $label, int $boardUid): string {
		$url = $this->resolve($kind, $id, $boardUid);

		if ($url === null) {
			return $label;
		}

		return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $label . '</a>';
	}

	/** The registered prose patterns, applied to one stretch of escaped text. */
	private function linkPatterns(string $text, int $boardUid): string {
		foreach ($this->patterns as $kind => $pattern) {
			$text = preg_replace_callback(
				$pattern,
				fn(array $matches): string => $this->link($kind, $matches[1], $matches[0], $boardUid),
				$text
			);
		}

		return $text;
	}

	/** The plain text of a log line, references reduced to their labels. */
	public function toText(string $text): string {
		return preg_replace(self::TOKEN_PATTERN, '$3', $text);
	}

	private function resolve(string $kind, string $id, int $boardUid): ?string {
		if (!isset($this->resolvers[$kind])) {
			return null;
		}

		$url = ($this->resolvers[$kind])($id, $boardUid);

		return ($url ?? '') === '' ? null : (string) $url;
	}
}
