<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\post\postRootRepair;

/**
 * Put back post times that an auto-updating `root` overwrote.
 *
 * On older servers `root` was created with ON UPDATE CURRENT_TIMESTAMP, so every write to a post
 * (an edit, the legacy text conversion) moved its time to that moment. Each row's time is
 * recovered from its `now` text. Only runs while the column still auto-updates, and only moves
 * a root earlier, so it is safe to resume.
 */
return new class extends migration {
	private const BATCH = 2000;

	public function description(): string {
		return 'Recover post times overwritten by an auto-updating root';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		if (!$this->autoUpdates($ctx)) {
			return;
		}

		$votes = [];
		foreach ($this->posts($ctx) as [$row, $root, $parsedNow]) {
			$uid = (int)$row['boardUID'];
			$votes[$uid] ??= [];
			postRootRepair::vote($votes[$uid], $root, $parsedNow);
		}

		$allVotes = [];
		foreach ($votes as $boardVotes) {
			foreach ($boardVotes as $offset => $count) {
				$allVotes[$offset] = ($allVotes[$offset] ?? 0) + $count;
			}
		}
		$fallback = postRootRepair::winningOffset($allVotes);

		$offsets = [];
		foreach ($votes as $uid => $boardVotes) {
			$offsets[$uid] = postRootRepair::winningOffset($boardVotes);
		}

		$repaired = 0;
		$pending = [];
		foreach ($this->posts($ctx) as [$row, $root, $parsedNow]) {
			$offset = $offsets[(int)$row['boardUID']] ?? $fallback;
			$original = $offset === null ? null : postRootRepair::repairedRoot($root, $parsedNow, $offset);
			if ($original === null) {
				continue;
			}

			$pending[(int)$row['post_uid']] = gmdate('Y-m-d H:i:s', $original);
			if (count($pending) >= self::BATCH) {
				$repaired += $this->write($ctx, $pending);
				$pending = [];
			}
		}
		$repaired += $this->write($ctx, $pending);

		$ctx->note(($ctx->isDryRun() ? 'would recover ' : 'recovered ') . number_format($repaired) . ' post time(s)');
	}

	/** The overwritten times are gone, so there is nothing to put back. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		return !$this->autoUpdates($ctx);
	}

	private function autoUpdates(migrationContext $ctx): bool {
		$posts = $ctx->table('POST_TABLE');
		if (!$ctx->inspector->tableExists($posts)) {
			return false;
		}

		$column = $ctx->inspector->getColumns($posts)['root'] ?? null;

		return $column !== null && stripos($column['extra'], 'on update') !== false;
	}

	/**
	 * Every post with a readable `now`, as [row, root, parsed now].
	 */
	private function posts(migrationContext $ctx): Generator {
		$after = 0;

		do {
			$rows = $ctx->fetchAll(
				'SELECT post_uid, boardUID, root, `now` FROM {POST_TABLE} WHERE post_uid > ? ORDER BY post_uid LIMIT ' . self::BATCH,
				[$after]
			);

			foreach ($rows as $row) {
				$after = (int)$row['post_uid'];
				$parsedNow = postRootRepair::parseNow((string)$row['now']);
				$root = strtotime($row['root'] . ' UTC');
				if ($parsedNow !== null && $root !== false) {
					yield [$row, $root, $parsedNow];
				}
			}
		} while (count($rows) === self::BATCH);
	}

	/** @param array<int, string> $roots post_uid => root */
	private function write(migrationContext $ctx, array $roots): int {
		if (!$roots) {
			return 0;
		}

		$cases = '';
		$params = [];
		foreach ($roots as $uid => $root) {
			$cases .= ' WHEN ? THEN ?';
			$params[] = $uid;
			$params[] = $root;
		}

		$params = array_merge($params, array_keys($roots));
		$in = implode(', ', array_fill(0, count($roots), '?'));

		$ctx->execute("UPDATE {POST_TABLE} SET root = CASE post_uid{$cases} END WHERE post_uid IN ({$in})", $params);

		return count($roots);
	}
};
