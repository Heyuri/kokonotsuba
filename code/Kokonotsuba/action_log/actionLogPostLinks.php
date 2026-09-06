<?php

namespace Kokonotsuba\action_log;

use Kokonotsuba\board\board;
use Kokonotsuba\post\postRepository;

/**
 * Makes the post numbers in a page of log entries clickable.
 *
 * The log has always written post numbers as prose - "Purged thread No.1234" - and those lines
 * are already in the table, so they are matched by their shape rather than rewritten into
 * references. The numbers on the page are resolved to their threads in one query per board;
 * anything that no longer exists keeps its plain text.
 */
class actionLogPostLinks {
	/** Post numbers as the log writes them: "No.1234", "No. 1234". */
	private const POST_NUMBER_PATTERN = '/\bNo\.\s?(\d{1,10})\b/';

	/** Enough for any page of the log; past it the rest of the numbers stay unlinked. */
	private const MAX_LOOKUPS = 500;

	/** @var array<int, board> Boards by UID, for building each link on its own board. */
	private array $boardsByUid = [];

	/** @param list<board> $boards */
	public function __construct(private readonly postRepository $postRepository, array $boards) {
		foreach ($boards as $board) {
			$this->boardsByUid[$board->getBoardUID()] = $board;
		}
	}

	/**
	 * Resolve the post numbers these entries name and register the link for them.
	 *
	 * @param list<loggedActionEntry> $entries
	 */
	public function register(actionLogReferences $references, array $entries): void {
		$threadNumbers = $this->postRepository->findThreadNumbersForPostNumbers($this->collect($entries));

		$references->registerPattern('post', self::POST_NUMBER_PATTERN);
		$references->register('post', function (string $id, int $boardUid) use ($threadNumbers): ?string {
			$board = $this->boardsByUid[$boardUid] ?? null;
			$threadNumber = $threadNumbers[$boardUid][(int)$id] ?? null;

			if ($board === null || $threadNumber === null) {
				return null;
			}

			return $board->getBoardThreadURL($threadNumber, (int)$id);
		});
	}

	/**
	 * The post numbers named on this page, per board.
	 *
	 * A global entry names no board to look on, so its numbers are left as text.
	 *
	 * @param list<loggedActionEntry> $entries
	 * @return array<int, list<int>>
	 */
	private function collect(array $entries): array {
		$numbersByBoard = [];
		$found = 0;

		foreach ($entries as $entry) {
			$boardUid = $entry->getBoardUID();

			if (!isset($this->boardsByUid[$boardUid])) {
				continue;
			}

			if (!preg_match_all(self::POST_NUMBER_PATTERN, $entry->getLogAction(), $matches)) {
				continue;
			}

			foreach ($matches[1] as $postNumber) {
				if ($found >= self::MAX_LOOKUPS) {
					return $numbersByBoard;
				}

				$numbersByBoard[$boardUid][] = (int)$postNumber;
				$found++;
			}
		}

		return $numbersByBoard;
	}
}
