<?php

namespace Kokonotsuba\board;

/**
 * The reader's overboard board selection.
 *
 * The cookie stores a blacklist rather than the selection itself, so a board listed after the
 * cookie was written shows up without the reader doing anything. Everything that reads the
 * cookie (the overboard, thread watcher alerts) goes through here so the two never disagree on
 * how a malformed or stale value is read, and key() gives a canonical name to the selection for
 * anything that wants to cache by it.
 */
final class overboardBoardFilter {
	public const COOKIE_NAME = 'overboard_black_list';
	public const COOKIE_LIFETIME = 86400 * 30;

	/** @var int[] Listed board UIDs, sorted and unique. */
	private array $listed;

	/** @var int[] Blacklisted board UIDs, sorted and unique. May name boards no longer listed. */
	private array $blacklist;

	private function __construct(array $listed, array $blacklist) {
		$this->listed = self::normalize($listed);
		$this->blacklist = self::normalize($blacklist);
	}

	/**
	 * Build the filter from the raw cookie value. Anything unreadable is treated as no cookie.
	 *
	 * @param int[] $listedBoards
	 */
	public static function fromCookie(?string $raw, array $listedBoards): self {
		return new self($listedBoards, self::parseCookie($raw));
	}

	/**
	 * Build the filter from the boards the reader ticked on the filter form.
	 *
	 * @param int[] $selectedBoards
	 * @param int[] $listedBoards
	 */
	public static function fromSelection(array $selectedBoards, array $listedBoards): self {
		$listed = self::normalize($listedBoards);
		$selected = self::normalize($selectedBoards);

		return new self($listed, array_diff($listed, $selected));
	}

	/** @param int[] $listedBoards */
	public static function unfiltered(array $listedBoards): self {
		return new self($listedBoards, []);
	}

	/**
	 * Read the blacklist out of a raw cookie value.
	 *
	 * @return int[] Sorted, unique board UIDs. Empty when the cookie is missing or malformed.
	 */
	public static function parseCookie(?string $raw): array {
		if ($raw === null || $raw === '') {
			return [];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}

		return self::normalize($decoded);
	}

	/** @return int[] Listed boards not on the blacklist, sorted. */
	public function allowedBoards(): array {
		return array_values(array_diff($this->listed, $this->blacklist));
	}

	/** @return int[] */
	public function blacklistedBoards(): array {
		return $this->blacklist;
	}

	/** True when every listed board is shown, whatever stale entries the cookie may hold. */
	public function isUnfiltered(): bool {
		return array_intersect($this->listed, $this->blacklist) === [];
	}

	/** Canonical name for this selection: the same boards always give the same key. */
	public function key(): string {
		return implode(',', $this->allowedBoards());
	}

	/** The value to store in the cookie. */
	public function toCookieValue(): string {
		return json_encode($this->blacklist);
	}

	/**
	 * Keep numeric entries as ints, drop the rest, dedupe and sort.
	 *
	 * @return int[]
	 */
	private static function normalize(array $boardUIDs): array {
		$out = [];
		foreach ($boardUIDs as $uid) {
			if (is_int($uid) || (is_string($uid) && is_numeric($uid))) {
				$out[(int)$uid] = true;
			}
		}
		$out = array_keys($out);
		sort($out);

		return $out;
	}
}
