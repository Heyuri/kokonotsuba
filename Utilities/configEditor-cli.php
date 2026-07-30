<?php

declare(strict_types=1);

/**
 * Command-line editor for the per-board / global configuration overrides.
 *
 * The same settings the admin panel's [Global config] and per-board config screens edit, driven
 * from a terminal against the same board_configs table and the same configService - so a value
 * set here shows up in the panel and vice versa. Handy for headless setup, scripting, or fixing a
 * board whose config left the panel unreachable.
 *
 * A "scope" is either a board UID, or the word "global" for the site-wide config that every board
 * inherits (stored under the reserved GLOBAL board). Values cascade exactly as in the app:
 * schema default -> global override -> board override.
 *
 * Usage:
 *   php Utilities/configEditor-cli.php boards
 *       List the boards (and the global scope) you can edit.
 *
 *   php Utilities/configEditor-cli.php list <scope> [--all] [--filter=STR]
 *       Show settings for a scope. Only overridden ones by default; --all shows every setting.
 *       --filter limits to keys/labels containing STR.
 *
 *   php Utilities/configEditor-cli.php get <scope> <key>
 *       Print one setting's effective value, what it inherits, and whether it is overridden.
 *
 *   php Utilities/configEditor-cli.php set <scope> <key> <value> [--rebuild]
 *       Override one setting. A value equal to what the scope inherits clears the override
 *       instead. Array settings take a JSON value (e.g. '["webm","mp4"]'). Booleans take
 *       1/0/true/false/on/off/yes/no.
 *
 *   php Utilities/configEditor-cli.php unset <scope> <key> [--rebuild]
 *       Clear one setting's override, reverting just it to the inherited value.
 *
 *   php Utilities/configEditor-cli.php reset <scope> [--rebuild]
 *       Clear every override for the scope. Asks to confirm unless --yes is given.
 *
 * Options:
 *   --rebuild   After a change, queue a background rebuild of the affected board(s), like the
 *               panel does. Without it, regenerate the static pages yourself afterwards.
 *   --yes       Skip the confirmation prompt (for reset).
 *   --json      Machine-readable output for get/list.
 *
 * A change to the global scope affects every board that has not overridden that setting itself.
 */

use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\configService;
use Kokonotsuba\database\databaseConnection;
use Puchiko\background\BackgroundTaskDispatcher;

use const Kokonotsuba\GLOBAL_BOARD_UID;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

// Tests define this to load the command functions below without booting a database or running the
// argument-driven main flow. Nothing else should set it.
if (defined('CONFIG_EDITOR_CLI_NO_MAIN')) {
	return;
}

$rootDir = dirname(__DIR__);

require $rootDir . '/autoload.php';
require $rootDir . '/code/Kokonotsuba/constants.php';
require $rootDir . '/paths.php';
require $rootDir . '/bootstrap/libraryIncludes.php';

// ── argument parsing ────────────────────────────────────────────────────────

$scriptName = basename(__FILE__);
$positional = [];
$flags = [];

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--')) {
		$body = substr($arg, 2);
		[$name, $value] = array_pad(explode('=', $body, 2), 2, true);
		$flags[$name] = $value;
	} else {
		$positional[] = $arg;
	}
}

$command = $positional[0] ?? '';

if ($command === '' || $command === 'help' || isset($flags['help'])) {
	printUsage($scriptName);
	exit($command === '' ? 2 : 0);
}

// ── wiring (only what a config edit needs; no full request lifecycle) ────────

require $rootDir . '/bootstrap/database.php';

$databaseSettings = getDatabaseSettings();

$configRepository = new configRepository(
	databaseConnection::getInstance(),
	$databaseSettings['BOARD_CONFIG_TABLE']
);
$configService = new configService($configRepository);

// A background rebuild dispatches the same 'rebuild_boards' task the panel uses; register it and
// its context the way bootstrap/global.php would, so --rebuild works outside the web app.
require_once $rootDir . '/code/Kokonotsuba/background/rebuildBoardsTask.php';
BackgroundTaskDispatcher::setContext($rootDir . '/bootstrap/background.php');
BackgroundTaskDispatcher::setAppRoot($rootDir . '/');
\Puchiko\background\BackgroundTaskRegistry::register(
	'rebuild_boards',
	\Kokonotsuba\background\rebuildBoardsTask::class
);

try {
	exit(runCommand($command, $positional, $flags, $configService, $databaseSettings));
} catch (Throwable $e) {
	fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
	exit(1);
}

// ── commands ────────────────────────────────────────────────────────────────

function runCommand(string $command, array $positional, array $flags, configService $configService, array $databaseSettings): int {
	switch ($command) {
		case 'boards':
			return commandBoards($databaseSettings);

		case 'list':
			return commandList($positional, $flags, $configService, $databaseSettings);

		case 'get':
			return commandGet($positional, $flags, $configService, $databaseSettings);

		case 'set':
			return commandSet($positional, $flags, $configService, $databaseSettings);

		case 'unset':
			return commandUnset($positional, $flags, $configService, $databaseSettings);

		case 'reset':
			return commandReset($positional, $flags, $configService, $databaseSettings);

		default:
			fwrite(STDERR, "Unknown command: {$command}\n\n");
			printUsage(basename(__FILE__));
			return 2;
	}
}

function commandBoards(array $databaseSettings): int {
	echo "Scopes you can edit:\n\n";
	echo "  global    Site-wide config (inherited by every board)\n";

	foreach (listBoards($databaseSettings) as $uid => $board) {
		printf("  %-8d %s\n", $uid, describeBoard($board));
	}

	echo "\nEdit a scope by its UID, or 'global'.\n";
	return 0;
}

function commandList(array $positional, array $flags, configService $configService, array $databaseSettings): int {
	$boardUid = resolveScope($positional[1] ?? '', $databaseSettings);

	$effective = $configService->getEffectiveValues($boardUid);
	$inherited = $configService->getInheritedValues($boardUid);
	$overrides = $configService->getOverrides($boardUid);

	$showAll = isset($flags['all']);
	$filter = is_string($flags['filter'] ?? null) ? strtolower($flags['filter']) : '';

	$rows = [];
	foreach (configSchema::getAllFields() as $dotpath => $meta) {
		$dotpath = (string)$dotpath;
		$isOverridden = array_key_exists($dotpath, $overrides);

		if (!$showAll && !$isOverridden) {
			continue;
		}
		if ($filter !== '' && !str_contains(strtolower($dotpath), $filter) && !str_contains(strtolower((string)$meta['label']), $filter)) {
			continue;
		}

		$rows[$dotpath] = [
			'value'      => $effective[$dotpath] ?? $meta['default'],
			'inherited'  => $inherited[$dotpath] ?? $meta['default'],
			'overridden' => $isOverridden,
		];
	}

	if (isset($flags['json'])) {
		echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
		return 0;
	}

	echo scopeHeading($boardUid, $databaseSettings), "\n";
	if (empty($rows)) {
		echo $showAll ? "  (no settings match)\n" : "  (no overrides; pass --all to see every setting)\n";
		return 0;
	}

	$width = max(array_map('strlen', array_keys($rows)));
	foreach ($rows as $dotpath => $row) {
		$marker = $row['overridden'] ? '*' : ' ';
		printf("%s %-{$width}s  %s\n", $marker, $dotpath, formatValue($row['value']));
	}

	echo "\n* = overridden for this scope. " . count(array_filter($rows, fn($r) => $r['overridden'])) . " overridden.\n";
	return 0;
}

function commandGet(array $positional, array $flags, configService $configService, array $databaseSettings): int {
	$boardUid = resolveScope($positional[1] ?? '', $databaseSettings);
	$dotpath = requireField($positional[2] ?? '');

	$meta = configSchema::getFieldMeta($dotpath);
	$effective = $configService->getEffectiveValues($boardUid)[$dotpath] ?? $meta['default'];
	$inherited = $configService->getInheritedValues($boardUid)[$dotpath] ?? $meta['default'];
	$isOverridden = array_key_exists($dotpath, $configService->getOverrides($boardUid));

	if (isset($flags['json'])) {
		echo json_encode([
			'key'        => $dotpath,
			'type'       => $meta['type'],
			'value'      => $effective,
			'inherited'  => $inherited,
			'default'    => $meta['default'],
			'overridden' => $isOverridden,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
		return 0;
	}

	echo scopeHeading($boardUid, $databaseSettings), "\n";
	echo "  setting     {$dotpath}  ({$meta['type']})\n";
	echo "  value       " . formatValue($effective) . ($isOverridden ? "   (overridden)" : "   (inherited)") . "\n";
	echo "  inherits    " . formatValue($inherited) . "\n";
	echo "  default     " . formatValue($meta['default']) . "\n";
	return 0;
}

function commandSet(array $positional, array $flags, configService $configService, array $databaseSettings): int {
	$boardUid = resolveScope($positional[1] ?? '', $databaseSettings);
	$dotpath = requireField($positional[2] ?? '');

	if (!array_key_exists(3, $positional)) {
		throw new InvalidArgumentException("set needs a value: set <scope> <key> <value>");
	}
	$rawValue = $positional[3];

	$before = $configService->getEffectiveValues($boardUid)[$dotpath] ?? configSchema::getFieldMeta($dotpath)['default'];

	$configService->setOverride($boardUid, $dotpath, $rawValue);

	$after = $configService->getEffectiveValues($boardUid)[$dotpath] ?? configSchema::getFieldMeta($dotpath)['default'];
	$isOverridden = array_key_exists($dotpath, $configService->getOverrides($boardUid));

	echo scopeHeading($boardUid, $databaseSettings), "\n";
	echo "  {$dotpath}: " . formatValue($before) . "  ->  " . formatValue($after);
	echo $isOverridden ? "  (overridden)\n" : "  (equals inherited value; override cleared)\n";

	maybeRebuild($boardUid, $flags, $databaseSettings);
	return 0;
}

function commandUnset(array $positional, array $flags, configService $configService, array $databaseSettings): int {
	$boardUid = resolveScope($positional[1] ?? '', $databaseSettings);
	$dotpath = requireField($positional[2] ?? '');

	if (!array_key_exists($dotpath, $configService->getOverrides($boardUid))) {
		echo "Not overridden for this scope; nothing to do.\n";
		return 0;
	}

	$configService->unsetOverride($boardUid, $dotpath);
	$now = $configService->getEffectiveValues($boardUid)[$dotpath] ?? configSchema::getFieldMeta($dotpath)['default'];

	echo scopeHeading($boardUid, $databaseSettings), "\n";
	echo "  {$dotpath} reverted to its inherited value: " . formatValue($now) . "\n";

	maybeRebuild($boardUid, $flags, $databaseSettings);
	return 0;
}

function commandReset(array $positional, array $flags, configService $configService, array $databaseSettings): int {
	$boardUid = resolveScope($positional[1] ?? '', $databaseSettings);

	$count = count($configService->getOverrides($boardUid));
	if ($count === 0) {
		echo "No overrides to clear for this scope.\n";
		return 0;
	}

	if (!isset($flags['yes']) && !confirm("Clear all {$count} override(s) for " . scopeHeading($boardUid, $databaseSettings) . "?")) {
		echo "Aborted.\n";
		return 0;
	}

	$configService->resetOverrides($boardUid);
	echo "Cleared {$count} override(s).\n";

	maybeRebuild($boardUid, $flags, $databaseSettings);
	return 0;
}

// ── helpers ─────────────────────────────────────────────────────────────────

/** Resolve a scope argument ("global" or a board UID) to a UID, validating it exists. */
function resolveScope(string $scope, array $databaseSettings): int {
	if ($scope === '') {
		throw new InvalidArgumentException("Missing scope. Use 'global' or a board UID (see: {$GLOBALS['argv'][0]} boards).");
	}

	if (strtolower($scope) === 'global') {
		return GLOBAL_BOARD_UID;
	}

	if (!preg_match('/^\d+$/', $scope)) {
		throw new InvalidArgumentException("Invalid scope '{$scope}'. Use 'global' or a numeric board UID.");
	}

	$boardUid = (int)$scope;
	if (!isset(listBoards($databaseSettings)[$boardUid])) {
		throw new InvalidArgumentException("No board with UID {$boardUid}. Run '{$GLOBALS['argv'][0]} boards' to list them.");
	}

	return $boardUid;
}

/** Validate a setting key is in the schema and return it. */
function requireField(string $dotpath): string {
	if ($dotpath === '') {
		throw new InvalidArgumentException("Missing setting key.");
	}
	if (!configSchema::hasField($dotpath)) {
		$hint = suggestField($dotpath);
		throw new InvalidArgumentException("Unknown setting '{$dotpath}'." . ($hint !== '' ? " Did you mean '{$hint}'?" : " List settings with: list <scope> --all"));
	}
	return $dotpath;
}

/** Closest known setting key to a mistyped one, or '' if nothing is close. */
function suggestField(string $dotpath): string {
	$best = '';
	$bestDistance = PHP_INT_MAX;

	foreach (array_keys(configSchema::getAllFields()) as $candidate) {
		$distance = levenshtein(strtolower($dotpath), strtolower((string)$candidate));
		if ($distance < $bestDistance) {
			$bestDistance = $distance;
			$best = (string)$candidate;
		}
	}

	// Only suggest when it is actually close, not the least-bad of a hundred keys.
	return $bestDistance <= 3 ? $best : '';
}

/**
 * The regular boards, keyed by UID. Cached across calls in a single run.
 *
 * @return array<int, \Kokonotsuba\board\boardData>
 */
function listBoards(array $databaseSettings): array {
	static $boards = null;
	if ($boards !== null) {
		return $boards;
	}

	$repository = new \Kokonotsuba\board\boardRepository(
		databaseConnection::getInstance(),
		$databaseSettings['BOARD_TABLE']
	);

	$boards = [];
	foreach ($repository->getAllRegularBoards() ?? [] as $board) {
		$boards[$board->getBoardUID()] = $board;
	}

	return $boards;
}

function describeBoard(\Kokonotsuba\board\boardData $board): string {
	$identifier = $board->getBoardIdentifier();
	$title = $board->getBoardTitle();
	return trim(($identifier !== '' ? "/{$identifier}/ " : '') . $title);
}

function scopeHeading(int $boardUid, array $databaseSettings): string {
	if ($boardUid === GLOBAL_BOARD_UID) {
		return "global config";
	}
	$board = listBoards($databaseSettings)[$boardUid] ?? null;
	return "board {$boardUid}" . ($board ? " (" . describeBoard($board) . ")" : "");
}

/** Render a value for display: arrays as compact JSON, bools as true/false, '' as (empty). */
function formatValue(mixed $value): string {
	if (is_bool($value)) {
		return $value ? 'true' : 'false';
	}
	if (is_array($value)) {
		return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
	if ($value === '') {
		return '(empty)';
	}
	return (string)$value;
}

/** After a change, optionally queue a background rebuild of the affected board(s). */
function maybeRebuild(int $boardUid, array $flags, array $databaseSettings): void {
	if (!isset($flags['rebuild'])) {
		echo "\nNote: static pages are unchanged. Re-run with --rebuild, or rebuild from the panel.\n";
		return;
	}

	// A global change can alter any board; a board change only that board.
	$boardUids = $boardUid === GLOBAL_BOARD_UID
		? array_keys(listBoards($databaseSettings))
		: [$boardUid];

	if (empty($boardUids)) {
		echo "\nNo boards to rebuild.\n";
		return;
	}

	BackgroundTaskDispatcher::dispatch('rebuild_boards', ['boardUIDs' => array_values($boardUids)]);
	echo "\nQueued a background rebuild of " . count($boardUids) . " board(s).\n";
}

function confirm(string $question): bool {
	fwrite(STDOUT, $question . " [y/N] ");
	$line = fgets(STDIN);
	return $line !== false && in_array(strtolower(trim($line)), ['y', 'yes'], true);
}

function printUsage(string $scriptName): void {
	echo <<<TXT
Command-line editor for board / global configuration overrides.

Usage:
  php {$scriptName} boards
  php {$scriptName} list  <scope> [--all] [--filter=STR] [--json]
  php {$scriptName} get   <scope> <key> [--json]
  php {$scriptName} set   <scope> <key> <value> [--rebuild]
  php {$scriptName} unset <scope> <key> [--rebuild]
  php {$scriptName} reset <scope> [--yes] [--rebuild]

  <scope>  'global' (site-wide, inherited by every board) or a board UID.
  <key>    A setting's dot-path, e.g. PAGE_DEF or modules.antiFlood.RENZOKU3.
  <value>  Bools: 1/0/true/false. Arrays: a JSON value, e.g. '["webm","mp4"]'.

  --all       list: show every setting, not just overridden ones.
  --filter=S  list: only keys/labels containing S.
  --rebuild   Queue a background rebuild of affected board(s) after a change.
  --yes       reset: skip the confirmation prompt.
  --json      get/list: machine-readable output.

Editing 'global' changes every board that has not overridden that setting itself.

TXT;
}
