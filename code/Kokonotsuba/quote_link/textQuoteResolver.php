<?php

namespace Kokonotsuba\quote_link;

use Kokonotsuba\post\textFormat;

/**
 * Resolves a text, file name or ">No." quote to the post it refers to: the nearest earlier
 * post of the same thread that the matcher accepts. This is what the page script asks for
 * when the source may be among posts the page does not hold.
 *
 * One statement answers the whole question. A second is only ever run when every candidate the
 * first offered turns out to be a post that merely repeats the quote, which the database cannot
 * tell and the matcher can.
 */
class textQuoteResolver {
	/** How many replies back from the quoting post a text quote is searched for. */
	public const REPLY_WINDOW = 1000;

	/** Rows read per statement; the database only narrows, the matcher decides. */
	private const BATCH_SIZE = 25;

	/** Statements run before giving up on the replies. */
	public const MAX_STATEMENTS = 4;

	public function __construct(
		private readonly textQuoteRepository $textQuoteRepository
	) {}

	/**
	 * @param string $threadUid Thread the quoting post is in.
	 * @param int    $beforeUid The quoting post; only posts before it are considered.
	 * @param string $needle    A needle from textQuoteMatcher::normalizeNeedle().
	 * @param bool   $quoted    True for a ">>text" quote of a quote.
	 * @return int|null The source post's uid.
	 */
	public function resolve(string $threadUid, int $beforeUid, string $needle, bool $quoted): ?int {
		if ($threadUid === '' || $beforeUid <= 0 || $needle === '') {
			return null;
		}

		$postNumber = textQuoteMatcher::postNumber($needle);

		if ($postNumber !== null) {
			return $postNumber > 0
				? $this->textQuoteRepository->findPostUidByNumber($threadUid, $beforeUid, $postNumber)
				: null;
		}

		$needles = $this->needles($needle);
		$fileName = textQuoteMatcher::splitFileName($needle);
		$cursor = $beforeUid;
		$openingPostMatch = null;
		$fileMatch = null;

		for ($batch = 0; $batch < self::MAX_STATEMENTS; $batch++) {
			// the opening post and the files are asked about once, with the first batch: they are
			// the whole thread's answer, not this batch's
			$rows = $this->textQuoteRepository->findCandidates(
				$threadUid,
				$cursor,
				$needles,
				self::REPLY_WINDOW,
				self::BATCH_SIZE,
				$batch === 0,
				$batch === 0 ? $fileName : null
			);

			$replies = [];

			foreach ($rows as $row) {
				$isFileRow = ($row['file_name'] ?? null) !== null;
				$isOpeningPost = (bool)$row['is_op'];

				if (!$isFileRow && !$isOpeningPost) {
					$replies[] = $row;
				}

				if (!$this->rowIsSource($row, $needle, $quoted, $isFileRow)) {
					continue;
				}

				// a reply is nearer than anything the opening post or another branch can offer
				if (!$isOpeningPost) {
					return (int)$row['post_uid'];
				}

				if ($isFileRow) {
					$fileMatch ??= (int)$row['post_uid'];
				} else {
					$openingPostMatch ??= (int)$row['post_uid'];
				}
			}

			if (count($replies) < self::BATCH_SIZE) {
				break;
			}

			$cursor = (int)end($replies)['post_uid'];
		}

		// the opening post's own words beat its attachment, and both are the last resort
		return $openingPostMatch ?? $fileMatch;
	}

	/**
	 * The forms a needle can be stored in: as the poster typed it, and escaped as a row written
	 * before the plain-text switch holds it.
	 *
	 * @return string[]
	 */
	private function needles(string $needle): array {
		$needles = [$needle];
		$legacyNeedle = textQuoteMatcher::legacyNeedle($needle);

		if ($legacyNeedle !== $needle) {
			$needles[] = $legacyNeedle;
		}

		return $needles;
	}

	/** Whether a candidate row really is the source of this quote. */
	private function rowIsSource(array $row, string $needle, bool $quoted, bool $isFileRow): bool {
		$format = textFormat::fromStored($row['text_format']);

		if (!$isFileRow) {
			return textQuoteMatcher::commentMatches((string)$row['com'], $format, $needle, $quoted);
		}

		// the name is compared in the column's collation, so it is checked again here; a quote of
		// a quote only ever points at a post that quotes something itself
		if (textQuoteMatcher::displayedFileName((string)$row['file_name'], (string)$row['file_ext']) !== $needle) {
			return false;
		}

		return !$quoted || textQuoteMatcher::hasQuoteLine((string)$row['com'], $format);
	}
}
