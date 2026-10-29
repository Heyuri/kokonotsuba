<?php

declare(strict_types=1);

/**
 * Put converted posts back to the HTML they were stored as, from a pre-conversion backup.
 *
 * An earlier `migrate-cli up` (or post-text-format-cli convert) unwound legacy HTML posts into
 * plain text and flagged them `text_format` 1. That lost markup. Given a dump of the posts
 * table taken before that run, this copies the original `com`, `name`, `email`, `sub` and
 * `category` back and flags the rows legacy HTML (0) again, so they render verbatim as before.
 *
 * Load the dump into a scratch database on the same server first, then point this at it:
 *
 *   mysql -u root -p -e 'CREATE DATABASE koko_restore'
 *   mysql -u root -p koko_restore < backup.sql
 *
 *   php Utilities/post-text-restore-cli.php status  --source=koko_restore
 *       How many converted rows the backup can restore, per board.
 *
 *   php Utilities/post-text-restore-cli.php preview --source=koko_restore [--limit=N]
 *       Show the first N restorable rows (default 10) without writing anything.
 *
 *   php Utilities/post-text-restore-cli.php restore --source=koko_restore [--dry-run] [--force]
 *       Restore them. --dry-run reports without writing.
 *
 * Options:
 *   --source=DB          Database holding the backup, reached with the app's own credentials.
 *   --source-dsn=DSN     Instead of --source: a PDO DSN for the backup, with --source-user
 *                        and --source-pass. Use this when the dump lives on another server.
 *   --source-table=NAME  Posts table name in the backup (default: the app's).
 *   --board=UID          Only this board. Without it, every board.
 *   --limit=N            Stop after N backup rows.
 *   --batch=N            Rows read per query (default 500).
 *   --force              Restore rows edited since conversion too (see below).
 *   --no-color           Disable ANSI colour.
 */

use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\post\legacyPostConverter;
use Kokonotsuba\post\legacyPostRestorer;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir . '/autoload.php';
require $rootDir . '/code/Kokonotsuba/constants.php';
require $rootDir . '/paths.php';
\Kokonotsuba\kernel\kernel::loadLibraries();

// ── argument parsing ────────────────────────────────────────────────────────

$command = '';
$flags = [];

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--')) {
		[$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
		$flags[$name] = $value;
	} elseif ($command === '') {
		$command = $arg;
	}
}

$useColor = !isset($flags['no-color']) && getenv('NO_COLOR') === false;
$isTty = function_exists('stream_isatty') && @stream_isatty(STDOUT);

$paint = static function (string $text, string $color) use ($useColor): string {
	if (!$useColor) {
		return $text;
	}

	$codes = ['red' => '0;31', 'green' => '0;32', 'yellow' => '0;33', 'cyan' => '0;36', 'dim' => '0;90'];

	return "\033[{$codes[$color]}m{$text}\033[0m";
};

$usage = static function (): void {
	$name = basename(__FILE__);
	fwrite(STDERR, "Usage:\n"
		. "  php Utilities/{$name} status  --source=DB [--board=UID]\n"
		. "  php Utilities/{$name} preview --source=DB [--board=UID] [--limit=N]\n"
		. "  php Utilities/{$name} restore --source=DB [--dry-run] [--force] [--board=UID] [--limit=N] [--batch=N]\n"
		. "  (--source-dsn=DSN --source-user=U --source-pass=P in place of --source=DB)\n");
};

if (!in_array($command, ['status', 'preview', 'restore'], true)) {
	if ($command !== '') {
		fwrite(STDERR, "Unknown command: {$command}\n\n");
	}
	$usage();
	exit($command === '' ? 1 : 2);
}

$boardFilter = isset($flags['board']) ? (int)$flags['board'] : null;
$limit = isset($flags['limit']) ? max(1, (int)$flags['limit']) : null;
$batchSize = isset($flags['batch']) ? max(1, (int)$flags['batch']) : 500;
$dryRun = isset($flags['dry-run']);
$force = isset($flags['force']);

try {
	// ── wiring ──────────────────────────────────────────────────────────────────

	\Kokonotsuba\kernel\kernel::connectDatabase();

	$databaseConnection = databaseConnection::getInstance();
	$postTable = getTableName('POST_TABLE');

	$converter = new legacyPostConverter(
		$databaseConnection,
		new configService(new configRepository($databaseConnection, getTableName('BOARD_CONFIG_TABLE'))),
		$postTable,
		getTableName('BOARD_TABLE')
	);

	/** The backup, as a separate PDO: a scratch database on the same server or anywhere a DSN reaches. */
	$backup = (static function (array $flags, array $dbSettings): PDO {
		if (isset($flags['source-dsn'])) {
			return new PDO($flags['source-dsn'], $flags['source-user'] ?? '', $flags['source-pass'] ?? '', [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			]);
		}

		if (!isset($flags['source']) || $flags['source'] === '1') {
			throw new RuntimeException('Name the backup database with --source=DB (or --source-dsn=DSN).');
		}

		if ($flags['source'] === $dbSettings['DATABASE_NAME']) {
			throw new RuntimeException('--source is the live database; load the dump into a scratch one.');
		}

		$port = isset($dbSettings['DATABASE_PORT']) && (int)$dbSettings['DATABASE_PORT'] > 0 && $dbSettings['DATABASE_HOST'] !== 'localhost'
			? 'port=' . (int)$dbSettings['DATABASE_PORT'] . ';'
			: '';
		$dsn = "mysql:host={$dbSettings['DATABASE_HOST']};{$port}dbname={$flags['source']};charset={$dbSettings['DATABASE_CHARSET']}";

		return new PDO($dsn, $dbSettings['DATABASE_USERNAME'], $dbSettings['DATABASE_PASSWORD'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		]);
	})($flags, $dbSettings);

	$restorer = new legacyPostRestorer($databaseConnection, $backup, $flags['source-table'] ?? $postTable, $converter, $postTable);

	$label = static fn(array $row): string => "post {$row['post_uid']} (No.{$row['no']}, board {$row['boardUID']})";

	// ── commands ────────────────────────────────────────────────────────────────

	$commandStatus = static function () use ($restorer, $boardFilter, $limit, $batchSize, $paint): int {
		$perBoard = $restorer->summary($boardFilter, $limit, $batchSize);

		if (!$perBoard) {
			echo "The backup holds nothing the live table has converted.\n";
			return 0;
		}

		printf("%-6s %12s %12s\n", 'BOARD', 'RESTORABLE', 'EDITED');

		$totals = ['restorable' => 0, 'edited' => 0];

		foreach ($perBoard as $board => $counts) {
			printf("%-6s %12s %12s\n", $board, number_format($counts['restorable']), number_format($counts['edited']));
			$totals['restorable'] += $counts['restorable'];
			$totals['edited'] += $counts['edited'];
		}

		echo "\n" . $paint(number_format($totals['restorable']) . ' post(s) can be restored.', 'cyan') . "\n";

		if ($totals['edited'] > 0) {
			echo $paint(number_format($totals['edited']) . ' post(s) were edited since conversion; restore skips them without --force.', 'yellow') . "\n";
		}

		return 0;
	};

	$commandPreview = static function () use ($restorer, $boardFilter, $batchSize, $paint, $flags, $label): int {
		$count = isset($flags['limit']) ? max(1, (int)$flags['limit']) : 10;
		$columns = $restorer->textColumns();
		$shown = 0;

		$restorer->walk(static function (array $backupRow, array $liveRow, bool $unchanged) use (&$shown, $count, $paint, $columns, $label): void {
			if ($shown >= $count) {
				return;
			}
			$shown++;

			echo $paint($label($liveRow), 'cyan') . ($unchanged ? '' : ' ' . $paint('edited since conversion', 'yellow')) . "\n";

			foreach ($columns as $column) {
				if ((string)$liveRow[$column] === (string)$backupRow[$column]) {
					continue;
				}
				echo '  ' . $paint(str_pad($column, 9), 'dim') . $paint('- ', 'red') . str_replace("\n", '\n', (string)$liveRow[$column]) . "\n";
				echo '  ' . str_pad('', 9) . $paint('+ ', 'green') . str_replace("\n", '\n', (string)$backupRow[$column]) . "\n";
			}

			echo "\n";
		}, $boardFilter, null, $batchSize);

		if ($shown === 0) {
			echo "Nothing to restore.\n";
			return 0;
		}

		echo $paint('Preview only. Nothing was written.', 'yellow') . "\n";

		return 0;
	};

	$commandRestore = static function () use ($restorer, $databaseConnection, $boardFilter, $limit, $batchSize, $dryRun, $force, $isTty, $paint, $label): int {
		$progress = $isTty
			? static function (int $restored) use ($paint): void {
				if ($restored % 100 === 0) {
					echo "\r" . $paint("  {$restored} restored", 'dim');
				}
			}
			: null;

		$result = $restorer->restore($dryRun, $force, $boardFilter, $limit, $batchSize, $progress);

		if ($isTty) {
			echo "\r" . str_pad('', 40) . "\r";
		}

		foreach ($result['skipped'] as $row) {
			echo $paint('skipped ' . $label($row) . ': edited since conversion', 'yellow') . "\n";
		}

		$skipped = count($result['skipped']);

		if ($dryRun) {
			echo $paint("Dry run: {$result['restored']} post(s) would be restored, {$skipped} skipped.", 'yellow') . "\n";
			return 0;
		}

		// Restored rows render differently now, so their boards' cached fragments are stale.
		if ($result['boards']) {
			$boardRows = $databaseConnection->fetchAllAsArray(
				'SELECT storage_directory_name FROM ' . getTableName('BOARD_TABLE')
				. ' WHERE board_uid IN (' . implode(',', array_fill(0, count($result['boards']), '?')) . ')',
				$result['boards']
			);

			foreach ($boardRows as $row) {
				threadFragmentCache::forStorageDirName((string)$row['storage_directory_name'], getBoardStoragesDir())->clear();
			}
		}

		echo $paint("Restored {$result['restored']} post(s); {$skipped} skipped.", 'green') . "\n";
		echo $paint('Rebuild the boards to regenerate their static pages.', 'dim') . "\n";

		return 0;
	};

	// ── dispatch ────────────────────────────────────────────────────────────

	exit(match ($command) {
		'status' => $commandStatus(),
		'preview' => $commandPreview(),
		'restore' => $commandRestore(),
	});
} catch (Throwable $e) {
	fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
	exit(1);
}
