<?php

namespace Kokonotsuba\renderers\post;

use function Puchiko\strings\sanitizeStr;

/**
 * The tag shown on a post, resolved against the board's tag list.
 *
 * An OP with no tag gets the board's default; a tag the board no longer lists is drawn
 * as [?] so the post is not silently untagged.
 */
final class postTag {
	private function __construct(
		public readonly string $label,
		public readonly string $title,
	) {}

	public static function none(): self {
		return new self('', '');
	}

	/**
	 * @param array  $config The board config: ENABLE_TAGS, DEFAULT_TAG, TAGS.
	 * @param string $rawTag The tag as stored on the post.
	 * @param bool   $isOp   Whether the default tag applies when none is stored.
	 */
	public static function resolve(array $config, string $rawTag, bool $isOp): self {
		if (empty($config['ENABLE_TAGS'])) {
			return self::none();
		}

		$tag = $rawTag !== '' ? $rawTag : ($isOp ? (string)($config['DEFAULT_TAG'] ?? '') : '');

		if ($tag === '') {
			return self::none();
		}

		if (!isset($config['TAGS'][$tag])) {
			return new self('[?]', '???');
		}

		return new self(sanitizeStr($tag), sanitizeStr((string)$config['TAGS'][$tag]));
	}
}
