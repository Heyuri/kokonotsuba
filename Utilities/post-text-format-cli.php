<?php

declare(strict_types=1);

/**
 * Convert posts stored as HTML into the plain text the renderer now formats at render time.
 *
 * Posts used to be escaped and marked up on the way into the database: `com` held <br> tags,
 * autolink anchors, and whatever markup modules baked in. Posts are stored as typed now, and
 * `posts.text_format` says which a row is - 0 legacy HTML, 1 plain text, 2 raw HTML.
 *
 * `php Utilities/migrate-cli.php up` converts every legacy post as part of the upgrade. This is
 * the hand-driven version of the same conversion (Kokonotsuba\post\legacyPostConverter): for
 * previewing what it will do first, or converting one board or a big table in sittings.
 *
 *   php Utilities/post-text-format-cli.php status
 *       Count the posts in each format, per board.
 *
 *   php Utilities/post-text-format-cli.php preview [--board=UID] [--limit=N]
 *       Show what conversion would do to N posts (default 10) without writing anything.
 *
 *   php Utilities/post-text-format-cli.php convert [--dry-run] [--board=UID] [--limit=N]
 *       Convert legacy posts and mark them plain text. --dry-run reports without writing.
 *
 * Options:
 *   --board=UID   Only this board. Without it, every board.
 *   --limit=N     Stop after N posts. Handy for converting a big table in sittings.
 *   --batch=N     Rows read per query (default 500).
 *   --no-color    Disable ANSI colour.
 *
 * BACK UP THE POSTS TABLE FIRST. Conversion rewrites `com`, `name`, `email`, `sub` and
 * `category` in place and there is no reverse command.
 *
 * Two things do not survive exactly, both visible in --preview:
 *
 *   - A post made with the rawHtml tool is indistinguishable from any other legacy row, so its
 *     markup is reduced to text like everything else. Note those post numbers before converting
 *     and set their text_format to 2 by hand afterwards.
 *   - Very old rows kept a poster's tripcode and capcode markup inside the name column. There is
 *     no plain text that renders back to that, so the markup goes and its text stays.
 *
 * Rebuild the static pages afterwards; nothing here touches them.
 */

use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\post\legacyPostConverter;
use Kokonotsuba\post\textFormat;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir . '/autoload.php';
require $rootDir . '/code/Kokonotsuba/constants.php';
require $rootDir . '/paths.php';
require $rootDir . '/bootstrap/libraryIncludes.php';

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

// Progress redraws itself with a carriage return, which only makes sense on a terminal; piped
// or redirected output would just collect the padding.
$isTty = function_exists('stream_isatty') && @stream_isatty(STDOUT);

$paint = static function (string $text, string $color) use ($useColor): string {
	if (!$useColor) {
		return $text;
	}

	$codes = ['red' => '0;31', 'green' => '0;32', 'yellow' => '0;33', 'cyan' => '0;36', 'dim' => '0;90'];

	return "\033[{$codes[$color]}m{$text}\033[0m";
};

$boardFilter = isset($flags['board']) ? (int)$flags['board'] : null;
$limit = isset($flags['limit']) ? max(1, (int)$flags['limit']) : null;
$batchSize = isset($flags['batch']) ? max(1, (int)$flags['batch']) : 500;
$dryRun = isset($flags['dry-run']);

// ── wiring ──────────────────────────────────────────────────────────────────

require $rootDir . '/bootstrap/database.php';

$databaseConnection = databaseConnection::getInstance();

$converter = new legacyPostConverter(
	$databaseConnection,
	new configService(new configRepository($databaseConnection, getTableName('BOARD_CONFIG_TABLE'))),
	getTableName('POST_TABLE'),
	getTableName('BOARD_TABLE')
);

$formatNames = [
	textFormat::LEGACY_HTML->value => 'legacy HTML',
	textFormat::PLAIN_TEXT->value => 'plain text',
	textFormat::RAW_HTML->value => 'raw HTML',
];

// ── commands ────────────────────────────────────────────────────────────────

/** Count the posts in each format, per board. */
$commandStatus = static function () use ($converter, $boardFilter, $formatNames, $paint): int {
	$rows = $converter->countsByBoard($boardFilter);

	if (!$rows) {
		echo "No posts found.\n";
		return 0;
	}

	$totals = [];

	printf("%-6s %-24s %-14s %8s\n", 'BOARD', 'TITLE', 'FORMAT', 'POSTS');

	foreach ($rows as $row) {
		$format = (int)$row['text_format'];
		$total = (int)$row['total'];
		$totals[$format] = ($totals[$format] ?? 0) + $total;

		printf(
			"%-6s %-24s %-14s %8s\n",
			$row['boardUID'],
			mb_strimwidth((string)($row['board_title'] ?? '?'), 0, 24, '…'),
			$formatNames[$format] ?? "unknown ({$format})",
			number_format($total)
		);
	}

	echo "\n";

	foreach ($totals as $format => $total) {
		echo $paint(str_pad($formatNames[$format] ?? "unknown ({$format})", 14), 'dim')
			. ' ' . number_format($total) . "\n";
	}

	$legacy = $totals[textFormat::LEGACY_HTML->value] ?? 0;

	if ($legacy > 0) {
		echo "\n" . $paint("{$legacy} post(s) still stored as HTML. Run 'preview', then 'convert'.", 'yellow') . "\n";
	}

	return 0;
};

/** Show what conversion would do, without writing. */
$commandPreview = static function () use ($converter, $boardFilter, $paint, $flags): int {
	$count = isset($flags['limit']) ? max(1, (int)$flags['limit']) : 10;
	$rows = $converter->sample($count, $boardFilter);

	if (!$rows) {
		echo "Nothing to convert.\n";
		return 0;
	}

	foreach ($rows as $row) {
		$changes = $converter->convertRow($row);

		echo $paint("post {$row['post_uid']} (No.{$row['no']}, board {$row['boardUID']})", 'cyan') . "\n";

		if (!$changes) {
			echo '  ' . $paint('no change', 'dim') . "\n\n";
			continue;
		}

		foreach ($changes as $column => $value) {
			echo '  ' . $paint(str_pad($column, 9), 'dim') . $paint('- ', 'red')
				. str_replace("\n", '\n', (string)$row[$column]) . "\n";
			echo '  ' . str_pad('', 9) . $paint('+ ', 'green')
				. str_replace("\n", '\n', $value) . "\n";
		}

		echo "\n";
	}

	echo $paint('Preview only. Nothing was written.', 'yellow') . "\n";

	return 0;
};

/** Convert legacy posts and mark them plain text. */
$commandConvert = static function () use ($converter, $boardFilter, $paint, $dryRun, $limit, $batchSize, $isTty): int {
	$remaining = $converter->countLegacy($boardFilter);

	if ($remaining === 0) {
		echo "Nothing to convert.\n";
		return 0;
	}

	$target = $limit !== null ? min($limit, $remaining) : $remaining;

	echo $paint(($dryRun ? 'Would convert ' : 'Converting ') . number_format($target) . ' post(s).', 'cyan') . "\n";

	$progress = $isTty
		? static function (int $done, int $target) use ($paint): void {
			echo "\r" . $paint("  {$done} / {$target}", 'dim');
		}
		: null;

	$result = $converter->convert($dryRun, $boardFilter, $limit, $batchSize, $progress);

	if ($isTty) {
		echo "\r" . str_pad('', 40) . "\r";
	}

	if ($dryRun) {
		echo $paint("Dry run: {$result['converted']} post(s) would be converted, {$result['rewritten']} rewritten.", 'yellow') . "\n";
		return 0;
	}

	echo $paint("Converted {$result['converted']} post(s); {$result['rewritten']} had markup to unwind.", 'green') . "\n";
	echo $paint('Rebuild the boards to regenerate their static pages.', 'dim') . "\n";

	return 0;
};

// ── dispatch ────────────────────────────────────────────────────────────────

try {
	exit(match ($command) {
		'status' => $commandStatus(),
		'preview' => $commandPreview(),
		'convert' => $commandConvert(),
		default => (function () use ($command): int {
			$name = basename(__FILE__);

			if ($command !== '') {
				fwrite(STDERR, "Unknown command: {$command}\n\n");
			}

			fwrite(STDERR, "Usage:\n"
				. "  php Utilities/{$name} status\n"
				. "  php Utilities/{$name} preview [--board=UID] [--limit=N]\n"
				. "  php Utilities/{$name} convert [--dry-run] [--board=UID] [--limit=N] [--batch=N]\n");

			return $command === '' ? 1 : 2;
		})(),
	});
} catch (Throwable $e) {
	fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
	exit(1);
}
