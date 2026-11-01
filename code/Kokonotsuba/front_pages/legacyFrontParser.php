<?php

namespace Kokonotsuba\front_pages;

/** Reads the old Heyuri front page's html/*.inc.html files and index.php for Utilities/frontPageImport-cli.php. */
final class legacyFrontParser {
	/** Date formats seen in old news headlines, tried in order. */
	private const DATE_FORMATS = ['!Y/m/d @ H:i T', '!m/d/y @ g:iA T'];

	/**
	 * A page's first h3, else its first h4.headline, as plain text; the slug when it has neither.
	 * The body is imported verbatim, since it draws its own heading.
	 */
	public static function pageTitle(string $slug, string $html): string {
		foreach (['~<h3[^>]*>(.*?)</h3>~s', '~<h4 class="headline">(.*?)</h4>~s'] as $pattern) {
			if (preg_match($pattern, $html, $match) && ($title = self::plainText($match[1])) !== '') {
				return $title;
			}
		}

		return $slug;
	}

	/**
	 * Entries of news.inc.html, oldest first. A table whose headline can't be read is reported in
	 * $errors by its element id and left out.
	 * @param string[] $errors
	 * @return list<array{subject: string, body: string, byline: string, email: string, createdAt: \DateTimeImmutable}>
	 */
	public static function parseNews(string $html, array &$errors = []): array {
		$chunks = preg_split('~(?=<table id="news[\d_]+")~', $html);
		$entries = [];

		foreach ($chunks as $chunk) {
			if (!preg_match('~^<table id="(news[\d_]+)"~', $chunk, $idMatch)) {
				continue;
			}

			$entry = self::parseNewsTable($chunk);
			if ($entry === null) {
				$errors[] = $idMatch[1];
				continue;
			}
			$entries[] = $entry;
		}

		return array_reverse($entries);
	}

	/** @return ?array{subject: string, body: string, byline: string, email: string, createdAt: \DateTimeImmutable} */
	private static function parseNewsTable(string $table): ?array {
		if (!preg_match('~<h4 class="headline">(?:\s*<span class="barctl">.*?</span>)?(.*?)</h4>~s', $table, $headline)
			|| !preg_match('~^(.*?)\s*<small>\s*by\s+(.*?)\s+-\s+(.+?)\s*</small>\s*$~s', trim($headline[1]), $parts)) {
			return null;
		}

		$bodyStart = strpos($table, '<td class="newsbody">');
		$bodyEnd = strrpos($table, '</td>');
		$createdAt = self::parseDate($parts[3]);
		$subject = self::plainText($parts[1]);

		if ($bodyStart === false || $bodyEnd === false || $bodyEnd < $bodyStart || $createdAt === null || $subject === '') {
			return null;
		}

		$bodyStart += strlen('<td class="newsbody">');
		[$byline, $email] = self::parseByline($parts[2]);

		return [
			'subject' => $subject,
			'body' => trim(substr($table, $bodyStart, $bodyEnd - $bodyStart)),
			'byline' => $byline,
			'email' => $email,
			'createdAt' => $createdAt,
		];
	}

	/**
	 * A lone mailto link gives a name and an address; anything else (plain text, several authors,
	 * a web link) becomes plain text with no address.
	 * @return array{string, string} byline, email
	 */
	private static function parseByline(string $html): array {
		if (preg_match('~^<a href="mailto:([^"]*)">([^<]*)</a>$~', trim($html), $match)) {
			$email = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

			return [self::plainText($match[2]), filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : ''];
		}

		return [self::plainText($html), ''];
	}

	private static function parseDate(string $text): ?\DateTimeImmutable {
		$text = preg_replace('~\s+~', ' ', trim($text));

		foreach (self::DATE_FORMATS as $format) {
			$date = \DateTimeImmutable::createFromFormat($format, $text);
			if ($date !== false) {
				return $date;
			}
		}

		return null;
	}

	/**
	 * The FRONTTEXTS subtitles defined in the old index.php, read with the tokenizer so the old file
	 * is never run. Entries stay raw HTML (one carries its own script); line breaks become one space
	 * so each fits the config editor's one-line inputs. An entry that interpolates a variable is left out.
	 * @return list<string>
	 */
	public static function parseSubtitles(string $php): array {
		$tokens = array_values(array_filter(
			token_get_all($php),
			static fn($token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
		));

		$start = null;
		foreach ($tokens as $i => $token) {
			if (is_array($token) && $token[0] === T_STRING && strcasecmp($token[1], 'define') === 0
				&& ($tokens[$i + 1] ?? null) === '('
				&& is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING
				&& trim($tokens[$i + 2][1], '\'"') === 'FRONTTEXTS'
				&& ($tokens[$i + 3] ?? null) === ','
				&& ($tokens[$i + 4] ?? null) === '[') {
				$start = $i + 5;
				break;
			}
		}
		if ($start === null) {
			return [];
		}

		$subtitles = [];
		$heredoc = null;
		$nowdoc = false;
		for ($i = $start, $depth = 1; $i < count($tokens) && $depth > 0; $i++) {
			$token = $tokens[$i];
			$id = is_array($token) ? $token[0] : $token;

			if ($heredoc !== null) {
				if ($id === T_END_HEREDOC) {
					$subtitles[] = is_string($heredoc) && !$nowdoc ? self::unescape($heredoc, false) : $heredoc;
					$heredoc = null;
				} elseif ($id === T_ENCAPSED_AND_WHITESPACE && $heredoc !== false) {
					$heredoc .= $token[1];
				} else {
					$heredoc = false;
				}
				continue;
			}

			match ($id) {
				'[' => $depth++,
				']' => $depth--,
				T_START_HEREDOC => [$heredoc, $nowdoc] = ['', str_contains($token[1], "'")],
				T_CONSTANT_ENCAPSED_STRING => $subtitles[] = self::stringLiteral($token[1]),
				default => null,
			};
		}

		$subtitles = array_filter($subtitles, static fn($subtitle): bool => is_string($subtitle));

		return array_values(array_filter(array_map(
			static fn(string $subtitle): string => trim((string)preg_replace('/\s*\R\s*/', ' ', $subtitle)),
			$subtitles
		), static fn(string $subtitle): bool => $subtitle !== ''));
	}

	/** The value of a quoted PHP string literal. */
	private static function stringLiteral(string $literal): string {
		$body = substr($literal, 1, -1);

		return $literal[0] === "'" ? strtr($body, ['\\\\' => '\\', "\\'" => "'"]) : self::unescape($body, true);
	}

	/** PHP's double-quoted escapes; unknown ones stay as written, and \" only unescapes inside quotes. */
	private static function unescape(string $body, bool $quoted): string {
		$simple = ['n' => "\n", 't' => "\t", 'r' => "\r", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$'];
		if ($quoted) {
			$simple['"'] = '"';
		}

		return (string)preg_replace_callback('/\\\\(u\{[0-9A-Fa-f]+\}|x[0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)/s', static function (array $match) use ($simple): string {
			$escape = $match[1];

			return match (true) {
				isset($simple[$escape]) => $simple[$escape],
				$escape[0] === 'u' && strlen($escape) > 1 => mb_chr((int)hexdec(substr($escape, 2, -1)), 'UTF-8') ?: $match[0],
				$escape[0] === 'x' && strlen($escape) > 1 => chr((int)hexdec(substr($escape, 1))),
				strspn($escape, "01234567") === strlen($escape) => chr(octdec($escape) & 0xFF),
				default => $match[0],
			};
		}, $body);
	}

	private static function plainText(string $html): string {
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim(preg_replace('~\s+~u', ' ', $text));
	}
}
