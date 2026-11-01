<?php

declare(strict_types=1);

/**
 * Import the old Heyuri front page (../front-page/) into the front page's news, pages and subtitles.
 *
 *   php Utilities/frontPageImport-cli.php <dir> [--dry-run] [--no-news] [--no-pages] [--no-subtitles]
 *
 * <dir> is the old front page or its html/ directory. Pages are the ones its index.php served,
 * imported verbatim in its nav order; news comes from news.inc.html with its original dates;
 * subtitles are its index.php FRONTTEXTS, added to SITE.SUBTITLES. A page whose slug exists, an
 * entry with the same subject and date, or a subtitle already in the list is skipped, so the
 * import can be run again.
 *
 * Options:
 *   --dry-run    Parse and list what would be imported, without touching the database.
 *   --no-news    Skip news.inc.html.
 *   --no-pages   Skip the pages.
 *   --no-subtitles  Skip the FRONTTEXTS subtitles.
 *   --no-color   Disable ANSI colour.
 */

use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\front_pages\frontPageActionType;
use Kokonotsuba\front_pages\legacyFrontParser;
use Kokonotsuba\kernel\kernel;
use Kokonotsuba\news\newsActionType;
use Kokonotsuba\request\request;

use const Kokonotsuba\GLOBAL_BOARD_UID;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir . '/autoload.php';
require $rootDir . '/code/Kokonotsuba/constants.php';
require $rootDir . '/paths.php';
kernel::loadLibraries();

/** Slug => old nav label, in the old nav's order; null = not in the old nav. */
const LEGACY_PAGES = [
	'faq' => 'FAQ',
	'rules' => 'Rules',
	'links' => 'Links',
	'japanese' => '日本語',
	'advertise' => 'Advertise',
	'contact' => 'Contact',
	'positions' => 'Positions',
	'privacy' => 'Privacy',
	'oc' => 'OC',
	'tips' => null,
	'japanese2' => null,
];

// ── argument parsing ────────────────────────────────────────────────────────

$sourceDir = '';
$flags = [];

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--')) {
		$flags[substr($arg, 2)] = true;
	} elseif ($sourceDir === '') {
		$sourceDir = rtrim($arg, '/');
	}
}

$useColor = !isset($flags['no-color']) && getenv('NO_COLOR') === false;

$paint = static function (string $text, string $color) use ($useColor): string {
	if (!$useColor) {
		return $text;
	}

	$codes = ['red' => '0;31', 'green' => '0;32', 'yellow' => '0;33', 'dim' => '0;90'];

	return "\033[{$codes[$color]}m{$text}\033[0m";
};

if ($sourceDir === '') {
	fwrite(STDERR, "Usage: php Utilities/frontPageImport-cli.php <dir> [--dry-run] [--no-news] [--no-pages] [--no-subtitles]\n");
	exit(2);
}
// index.php sits beside html/
$oldIndexFile = (is_dir($sourceDir . '/html') ? $sourceDir : dirname($sourceDir)) . '/index.php';
if (is_dir($sourceDir . '/html')) {
	$sourceDir .= '/html';
}
if (!is_dir($sourceDir)) {
	fwrite(STDERR, "Not a directory: {$sourceDir}\n");
	exit(2);
}

$dryRun = isset($flags['dry-run']);

// ── parsing ─────────────────────────────────────────────────────────────────

$pages = [];
if (!isset($flags['no-pages'])) {
	$navOrder = 0;
	foreach (LEGACY_PAGES as $slug => $navLabel) {
		$navOrder++;
		$file = "{$sourceDir}/{$slug}.inc.html";
		if (!is_file($file)) {
			echo $paint("missing  {$slug}.inc.html", 'yellow'), "\n";
			continue;
		}

		$body = trim((string)file_get_contents($file));
		$pages[] = [
			'slug' => $slug,
			'title' => $navLabel ?? legacyFrontParser::pageTitle($slug, $body),
			'body' => $body,
			'showInNav' => $navLabel !== null,
			'navOrder' => $navOrder,
		];
	}
}

$news = [];
if (!isset($flags['no-news'])) {
	$newsFile = "{$sourceDir}/news.inc.html";
	if (is_file($newsFile)) {
		$unreadable = [];
		$news = legacyFrontParser::parseNews((string)file_get_contents($newsFile), $unreadable);
		foreach ($unreadable as $elementId) {
			echo $paint("unreadable news table #{$elementId}, left out", 'yellow'), "\n";
		}
	} else {
		echo $paint('missing  news.inc.html', 'yellow'), "\n";
	}
}

$subtitles = [];
if (!isset($flags['no-subtitles'])) {
	if (is_file($oldIndexFile)) {
		$subtitles = legacyFrontParser::parseSubtitles((string)file_get_contents($oldIndexFile));
	} else {
		echo $paint('missing  index.php (subtitles)', 'yellow'), "\n";
	}
}

// Stored like CURRENT_TIMESTAMP rows: wall-clock time in PHP's zone.
$localZone = new DateTimeZone(date_default_timezone_get());
foreach ($news as &$entry) {
	$entry['createdAt'] = $entry['createdAt']->setTimezone($localZone)->format('Y-m-d H:i:s');
}
unset($entry);

if ($dryRun) {
	foreach ($pages as $page) {
		echo "page  {$page['slug']}  \"{$page['title']}\"", $page['showInNav'] ? '' : $paint('  (not in nav)', 'dim'), "\n";
	}
	foreach ($news as $entry) {
		echo "news  {$entry['createdAt']}  {$entry['subject']}  ", $paint("by {$entry['byline']}" . ($entry['email'] !== '' ? " <{$entry['email']}>" : ''), 'dim'), "\n";
	}
	foreach ($subtitles as $subtitle) {
		echo 'subtitle  ', mb_strimwidth($subtitle, 0, 70, '...'), "\n";
	}
	echo count($pages), ' pages, ', count($news), ' news entries, ', count($subtitles), " subtitles parsed. Dry run: nothing written.\n";
	exit(0);
}

// ── import ──────────────────────────────────────────────────────────────────

$container = kernel::bootServices(new request(), new cookieService([]));
$frontPageService = $container->get('frontPageService');
$newsService = $container->get('newsService');

$counts = ['pages' => 0, 'news' => 0, 'subtitles' => 0, 'skipped' => 0, 'failed' => 0];

$importOne = static function (string $label, callable $import) use (&$counts, $paint): void {
	try {
		if ($import() === null) {
			$counts['skipped']++;
			echo $paint("skipped  {$label} (already there)", 'dim'), "\n";
			return;
		}
		echo $paint("imported {$label}", 'green'), "\n";
	} catch (Throwable $e) {
		$counts['failed']++;
		echo $paint("failed   {$label}: {$e->getMessage()}", 'red'), "\n";
		return;
	}
};

foreach ($pages as $page) {
	$importOne("page {$page['slug']}", static function () use ($frontPageService, $page, &$counts): ?int {
		$id = $frontPageService->importPage($page['slug'], $page['title'], $page['body'], $page['showInNav'], $page['navOrder']);
		$counts['pages'] += (int)($id !== null);
		return $id;
	});
}
foreach ($news as $entry) {
	$importOne("news {$entry['createdAt']} {$entry['subject']}", static function () use ($newsService, $entry, &$counts): ?int {
		$id = $newsService->importEntry($entry['subject'], $entry['body'], $entry['byline'], $entry['email'], $entry['createdAt']);
		$counts['news'] += (int)($id !== null);
		return $id;
	});
}

// appended to whatever SITE.SUBTITLES already holds, in the old order
if ($subtitles !== []) {
	$configService = $container->get('configService');
	$current = array_values((array)($configService->getEffectiveValues(GLOBAL_BOARD_UID)['SITE.SUBTITLES'] ?? []));
	$merged = $current;
	foreach ($subtitles as $subtitle) {
		if (in_array($subtitle, $merged, true)) {
			$counts['skipped']++;
			continue;
		}
		$merged[] = $subtitle;
		$counts['subtitles']++;
	}

	if ($counts['subtitles'] > 0) {
		try {
			$configService->setOverride(GLOBAL_BOARD_UID, 'SITE.SUBTITLES', json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			echo $paint("imported {$counts['subtitles']} subtitles", 'green'), "\n";
		} catch (Throwable $e) {
			$counts['failed']++;
			$counts['subtitles'] = 0;
			echo $paint("failed   subtitles: {$e->getMessage()}", 'red'), "\n";
		}
	}
}

if ($counts['pages'] + $counts['news'] + $counts['subtitles'] > 0) {
	$container->get('actionLoggerService')->logAction(
		"Imported {$counts['news']} news entries, {$counts['pages']} pages and {$counts['subtitles']} subtitles from the old front page",
		GLOBAL_BOARD_UID,
		match (true) {
			$counts['news'] > 0 => newsActionType::CREATE,
			$counts['pages'] > 0 => frontPageActionType::CREATE,
			default => frontPageActionType::EDIT,
		}
	);
	$container->get('frontPageCache')->forgetAll();
}

echo "{$counts['pages']} pages, {$counts['news']} news entries and {$counts['subtitles']} subtitles imported, {$counts['skipped']} skipped, {$counts['failed']} failed.\n";
exit($counts['failed'] > 0 ? 1 : 0);
