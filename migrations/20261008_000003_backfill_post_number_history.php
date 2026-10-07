<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

use function Kokonotsuba\Modules\postStats\backfillReadings;

/**
 * Give each board counter readings for the days before they were recorded.
 *
 * Readings only start on the first statistics view after the upgrade, so without these that day
 * is the only evidence for everything numbered before it. Existing readings are never touched.
 */
return new class extends migration {
	private const BATCH = 500;

	public function description(): string {
		return 'Backfill post number history from surviving posts';
	}

	public function up(migrationContext $ctx): void {
		require_once $ctx->appRoot . '/module/postStats/postStatsBackfill.php';

		$today = (string)$ctx->fetchValue('SELECT CURDATE()');

		$first = [];
		foreach ($ctx->fetchAll(
			'SELECT history.board_uid, history.day, history.post_number
			 FROM {POST_NUMBER_HISTORY_TABLE} AS history
			 JOIN (SELECT board_uid, MIN(day) AS day FROM {POST_NUMBER_HISTORY_TABLE} GROUP BY board_uid) AS earliest
				ON earliest.board_uid = history.board_uid AND earliest.day = history.day'
		) as $row) {
			$first[(int)$row['board_uid']] = ['day' => (string)$row['day'], 'number' => (int)$row['post_number']];
		}

		$days = [];
		foreach ($ctx->fetchAll(
			'SELECT boardUID, DATE(root) AS day, MIN(`no`) AS min_no, MAX(`no`) AS max_no
			 FROM {POST_TABLE}
			 GROUP BY boardUID, day
			 ORDER BY boardUID, day'
		) as $row) {
			$days[(int)$row['boardUID']][(string)$row['day']] = ['min' => (int)$row['min_no'], 'max' => (int)$row['max_no']];
		}

		$rows = [];
		foreach ($days as $uid => $boardDays) {
			$readings = backfillReadings($boardDays, $first[$uid]['day'] ?? $today, $first[$uid]['number'] ?? null);
			foreach ($readings as $day => $number) {
				$rows[] = [$uid, $day, $number];
			}
		}

		foreach (array_chunk($rows, self::BATCH) as $chunk) {
			$ctx->execute(
				'INSERT IGNORE INTO {POST_NUMBER_HISTORY_TABLE} (board_uid, day, post_number) VALUES '
					. implode(', ', array_fill(0, count($chunk), '(?, ?, ?)')),
				array_merge(...$chunk)
			);
		}

		$ctx->note(($ctx->isDryRun() ? 'would backfill ' : 'backfilled ') . number_format(count($rows)) . ' reading(s) over ' . count($days) . ' board(s)');
	}

	/** Backfilled readings are ordinary readings now, and later days were built on them. */
	public function down(migrationContext $ctx): void {}
};
