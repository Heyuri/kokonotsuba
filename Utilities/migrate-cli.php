<?php

declare(strict_types=1);

/**
 * Schema migration runner.
 *
 * Code updates arrive by git pull or by unpacking a release; this applies the matching database
 * changes afterwards. It needs no git, so a tarball install upgrades the same way a checkout does.
 *
 *   php Utilities/migrate-cli.php status
 *   php Utilities/migrate-cli.php up [--to=VERSION] [--namespace=NS] [--dry-run]
 *   php Utilities/migrate-cli.php down [--step=N] [--namespace=NS] [--dry-run] --force
 *   php Utilities/migrate-cli.php baseline [--dry-run]
 *   php Utilities/migrate-cli.php doctor
 *   php Utilities/migrate-cli.php make <name> [--module=NAME]
 *   php Utilities/migrate-cli.php version
 *
 * An install predating the ledger starts with `baseline`: it creates whatever the database is
 * missing, stamps what it already has, and leaves everything newer for `up`.
 */

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir.'/paths.php';
require $rootDir.'/autoload.php';
require $rootDir.'/code/Kokonotsuba/constants.php';

// ─── Arguments ─────────────────────────────────────────────────────

$argument = static function (string $name, ?string $default = null) use ($argv): ?string {
	foreach (array_slice($argv, 1) as $token) {
		if ($token === "--{$name}") {
			return '';
		}
		if (str_starts_with($token, "--{$name}=")) {
			return substr($token, strlen($name) + 3);
		}
	}

	return $default;
};

$hasFlag = static fn (string $name): bool => $argument($name) !== null;

$positional = [];
foreach (array_slice($argv, 1) as $token) {
	if (!str_starts_with($token, '--')) {
		$positional[] = $token;
	}
}

$command = $positional[0] ?? 'status';
$dryRun = $hasFlag('dry-run');
$namespace = $argument('namespace') ?: null;
$useColor = !$hasFlag('no-color') && (getenv('NO_COLOR') === false);

// ─── Output ────────────────────────────────────────────────────────

$paint = static function (string $text, string $color) use ($useColor): string {
	if (!$useColor) {
		return $text;
	}

	$codes = ['red' => '0;31', 'green' => '0;32', 'yellow' => '0;33', 'blue' => '0;34', 'dim' => '0;90'];

	return "\033[{$codes[$color]}m{$text}\033[0m";
};

$logger = static function (string $message, string $level) use ($paint, $dryRun): void {
	switch ($level) {
		case 'sql':
			echo $paint('  '.preg_replace('/\s+/', ' ', $message), 'dim')."\n";
			break;
		case 'migration':
			echo $paint(($dryRun ? '[dry-run] ' : '').$message, 'blue')."\n";
			break;
		default:
			echo $message."\n";
	}
};

$fail = static function (string $message) use ($paint): never {
	fwrite(STDERR, $paint('error: '.$message, 'red')."\n");
	exit(1);
};

function printUsage(): void {
	echo "Usage:\n";
	echo "  php Utilities/migrate-cli.php status\n";
	echo "  php Utilities/migrate-cli.php up [--to=VERSION] [--namespace=NS] [--dry-run]\n";
	echo "  php Utilities/migrate-cli.php down [--step=N] [--namespace=NS] [--dry-run] --force\n";
	echo "  php Utilities/migrate-cli.php baseline [--dry-run]\n";
	echo "  php Utilities/migrate-cli.php doctor\n";
	echo "  php Utilities/migrate-cli.php make <name> [--module=NAME]\n";
	echo "  php Utilities/migrate-cli.php version\n";
}

// ─── Wiring ────────────────────────────────────────────────────────

$databaseSettings = getDatabaseSettings();
$tableNames = getTableNames();

databaseConnection::createInstance($databaseSettings);
$databaseConnection = databaseConnection::getInstance();

$ledger = new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']);
$inspector = new schemaInspector($databaseConnection, $databaseSettings['DATABASE_NAME']);

$runner = new migrationRunner(
	$databaseConnection,
	$ledger,
	$inspector,
	$tableNames,
	$rootDir,
	Kokonotsuba\KOKO_VERSION,
	$logger
);

// ─── Commands ──────────────────────────────────────────────────────

try {
	switch ($command) {
		case 'status':
			$pending = $runner->pending(null, true);
			$orphans = $runner->orphans(true);
			$mismatched = $runner->mismatched(true);

			if (!$ledger->exists()) {
				echo $paint("No migration ledger yet — run `baseline` (existing install) or `install.php` (new one).\n", 'yellow');
			}

			foreach ($runner->namespaces() as $candidate) {
				$total = count($runner->discover()[$candidate]);
				$waiting = count(array_filter($pending, static fn ($m): bool => $m->namespace === $candidate));
				$head = $ledger->exists() ? $ledger->head($candidate) : null;

				printf(
					"%-28s %2d applied, %2d pending%s\n",
					$candidate,
					$total - $waiting,
					$waiting,
					$head !== null ? '   head '.$head : ''
				);
			}

			if ($pending !== []) {
				echo "\nPending:\n";
				foreach ($pending as $migration) {
					echo '  '.$migration->id()."\n";
				}
			}

			foreach ($orphans as $orphan) {
				$note = $orphan['moduleMissing']
					? 'module no longer installed'
					: 'applied but missing from disk — this code may be older than the database';
				echo $paint("\norphan: {$orphan['namespace']}/{$orphan['version']}_{$orphan['name']} ({$note})", 'yellow')."\n";
			}

			foreach ($mismatched as $entry) {
				echo $paint("checksum changed since it was applied: {$entry['namespace']}/{$entry['version']}_{$entry['name']}", 'yellow')."\n";
			}
			break;

		case 'up':
			$applied = $runner->withLock(static fn (): array => $runner->up($argument('to') ?: null, $namespace, $dryRun));

			if ($applied === []) {
				echo "Already up to date.\n";
				break;
			}

			echo $paint(($dryRun ? 'Would apply ' : 'Applied ').count($applied).' migration(s).', 'green')."\n";
			break;

		case 'down':
			if (!$hasFlag('force')) {
				$fail('down is destructive and requires --force. Take a backup first: nothing here does it for you.');
			}

			$steps = max(1, (int)($argument('step') ?: '1'));
			$reversed = $runner->withLock(static fn (): array => $runner->down($steps, $namespace, $dryRun));

			echo $paint(($dryRun ? 'Would revert ' : 'Reverted ').count($reversed).' migration(s).', 'green')."\n";
			break;

		case 'baseline':
			$result = $runner->withLock(static fn (): array => $runner->baseline($dryRun));

			if ($result['reconciled'] === []) {
				echo "Schema already matches the baseline.\n";
			} else {
				echo $paint(($dryRun ? 'Would reconcile:' : 'Reconciled:'), 'yellow')."\n";
				foreach ($result['reconciled'] as $finding) {
					echo '  '.$finding."\n";
				}
			}

			echo $paint(($dryRun ? 'Would stamp ' : 'Stamped ').count($result['stamped']).' migration(s).', 'green')."\n";

			if ($result['pending'] !== []) {
				echo "\n".count($result['pending'])." migration(s) still to apply — run `up`:\n";
				foreach ($result['pending'] as $migration) {
					echo '  '.$migration->id()."\n";
				}
			}
			break;

		case 'doctor':
			$findings = $runner->doctor();

			if ($findings === []) {
				echo $paint('No drift against the baseline.', 'green')."\n";
				break;
			}

			echo $paint(count($findings).' difference(s) from the baseline:', 'yellow')."\n";
			foreach ($findings as $finding) {
				echo '  '.$finding."\n";
			}
			echo "\nRun `baseline` to create what is missing.\n";
			break;

		case 'make':
			$name = $positional[1] ?? '';
			if (!preg_match('/^[a-z0-9_]+$/', $name)) {
				$fail('make needs a lower_snake_case name, e.g. `make add_post_flags`.');
			}

			$module = $argument('module');
			$directory = $module !== null && $module !== ''
				? $rootDir.'/module/'.$module.'/migrations'
				: $rootDir.'/migrations';

			if ($module !== null && $module !== '' && !is_dir($rootDir.'/module/'.$module)) {
				$fail("No such module: {$module}");
			}

			if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
				$fail("Could not create {$directory}");
			}

			$path = $directory.'/'.date('Ymd_His').'_'.$name.'.php';
			file_put_contents($path, <<<'TEMPLATE'
<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

return new class extends migration {
	public function description(): string {
		return '';
	}

	/** MariaDB commits implicitly on DDL — false for anything structural. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		// $ctx->schema->table('POST_TABLE')->addColumn('flags', 'INT UNSIGNED NOT NULL DEFAULT 0');
	}

	public function down(migrationContext $ctx): void {
		// $ctx->schema->table('POST_TABLE')->dropColumn('flags');
	}

	/** Return true when the database already shows this change, so `baseline` can stamp it. */
	public function detect(migrationContext $ctx): ?bool {
		return null;
	}
};

TEMPLATE);

			echo $paint('Created '.substr($path, strlen($rootDir) + 1), 'green')."\n";
			break;

		case 'version':
			echo 'Kokonotsuba '.Kokonotsuba\KOKO_VERSION."\n";

			if (!$ledger->exists()) {
				echo "Database: no migration ledger.\n";
				break;
			}

			foreach ($runner->namespaces() as $candidate) {
				echo '  '.str_pad($candidate, 28).($ledger->head($candidate) ?? '(none)')."\n";
			}

			$pending = count($runner->pending(null, true));
			echo $pending === 0
				? $paint("Database up to date.\n", 'green')
				: $paint("{$pending} migration(s) pending — run `up`.\n", 'yellow');
			break;

		case 'help':
		case '--help':
			printUsage();
			break;

		default:
			$fail("Unknown command: {$command}");
	}
} catch (Throwable $e) {
	$fail($e->getMessage());
}
