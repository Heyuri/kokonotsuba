<?php

namespace Kokonotsuba\post;

/**
 * How a post's user-supplied text columns (name, email, sub, com, category) are stored.
 *
 * Posts used to be stored as HTML: input was escaped at post time, line breaks became <br>,
 * and modules baked their own markup into the comment. Those rows are LEGACY_HTML and are
 * still emitted verbatim, because re-escaping them would show entities and tags to readers.
 * Everything written since is PLAIN_TEXT and gets escaped and formatted at render time.
 */
enum textFormat: int {
	/** Pre-refactor row: every text column already holds render-ready HTML. */
	case LEGACY_HTML = 0;

	/** Text columns hold exactly what the poster typed. */
	case PLAIN_TEXT = 1;

	/** As PLAIN_TEXT, except the comment is emitted unescaped (rawHtml module). */
	case RAW_HTML = 2;

	/**
	 * Resolve a stored column value, defaulting unknown or missing values to LEGACY_HTML.
	 *
	 * @param mixed $value Raw column value.
	 */
	public static function fromStored(mixed $value): self {
		return self::tryFrom((int)$value) ?? self::LEGACY_HTML;
	}

	/** True when the comment is already HTML and must not be escaped again. */
	public function commentIsHtml(): bool {
		return $this === self::LEGACY_HTML || $this === self::RAW_HTML;
	}

	/** True when name/email/sub/category are already HTML and must not be escaped again. */
	public function fieldsAreHtml(): bool {
		return $this === self::LEGACY_HTML;
	}
}
