<?php

namespace Kokonotsuba\migrations;

use Kokonotsuba\database\databaseConnection;
use RuntimeException;
use Throwable;

/**
 * Discovers migrations, applies them in order, and keeps the ledger in step.
 *
 * Core migrations live in {appRoot}/migrations, module ones in {appRoot}/module/{name}/migrations
 * under namespace "module:{name}". Core always runs first, since module tables reference core
 * ones; modules then run in name order.
 *
 * Module migrations run for every module present on disk, enabled or not: ModuleList is per-board
 * config, so a module can be switched on at any moment and must already have its tables.
 */
class migrationRunner {
	private const LOCK_NAME = 'koko_migrate';
	private const LOCK_TIMEOUT_SECONDS = 10;

	/** @var array<string, list<discoveredMigration>>|null */
	private ?array $discovered = null;

	/** @param callable(string, string):void $logger Receives (message, level). */
	public function __construct(
		private readonly databaseConnection $databaseConnection,
		private readonly migrationLedger $ledger,
		private readonly schemaInspector $inspector,
		private readonly array $tableNames,
		private readonly string $appRoot,
		private readonly string $kokoVersion,
		private $logger
	) {}

	private function log(string $message, string $level = 'info'): void {
		($this->logger)($message, $level);
	}

	// ─── Discovery ─────────────────────────────────────────────────

	/** @return array<string, list<discoveredMigration>> namespace => migrations, version-ordered */
	public function discover(): array {
		if ($this->discovered !== null) {
			return $this->discovered;
		}

		$namespaces = ['core' => $this->appRoot.'/migrations'];

		$moduleDirs = glob($this->appRoot.'/module/*/migrations', GLOB_ONLYDIR) ?: [];
		sort($moduleDirs, SORT_STRING);
		foreach ($moduleDirs as $dir) {
			$namespaces['module:'.basename(dirname($dir))] = $dir;
		}

		$discovered = [];
		foreach ($namespaces as $namespace => $dir) {
			if (!is_dir($dir)) {
				continue;
			}

			$found = [];
			foreach (glob($dir.'/*.php') ?: [] as $path) {
				$migration = discoveredMigration::fromPath($namespace, $path);
				if ($migration === null) {
					continue;
				}
				if (isset($found[$migration->version])) {
					throw new RuntimeException(
						"Duplicate migration version {$migration->version} in {$namespace}."
					);
				}
				$found[$migration->version] = $migration;
			}

			ksort($found, SORT_STRING);
			$discovered[$namespace] = array_values($found);
		}

		return $this->discovered = $discovered;
	}

	/** @return list<string> Namespaces in run order. */
	public function namespaces(): array {
		$namespaces = array_keys($this->discover());
		usort($namespaces, static fn (string $a, string $b): int
			=> [$a !== 'core', $a] <=> [$b !== 'core', $b]);

		return $namespaces;
	}

	/** @return array<string, array<string, array>> */
	private function appliedRows(bool $assumeEmptyIfMissing = false): array {
		if ($assumeEmptyIfMissing && !$this->ledger->exists()) {
			return [];
		}

		return $this->ledger->all();
	}

	/** @return list<discoveredMigration> */
	public function pending(?string $namespace = null, bool $readOnly = false): array {
		$applied = $this->appliedRows($readOnly);
		$pending = [];

		foreach ($this->namespaces() as $candidate) {
			if ($namespace !== null && $candidate !== $namespace) {
				continue;
			}
			foreach ($this->discover()[$candidate] as $migration) {
				if (!isset($applied[$candidate][$migration->version])) {
					$pending[] = $migration;
				}
			}
		}

		return $pending;
	}

	/**
	 * Applied versions with no file on disk. In a module namespace that usually means the module
	 * was removed; in core it means the code is older than the database.
	 *
	 * @return list<array{namespace: string, version: string, name: string, moduleMissing: bool}>
	 */
	public function orphans(bool $readOnly = false): array {
		$discovered = $this->discover();
		$orphans = [];

		foreach ($this->appliedRows($readOnly) as $namespace => $versions) {
			$known = array_column(
				array_map(static fn (discoveredMigration $m): array => ['v' => $m->version], $discovered[$namespace] ?? []),
				'v'
			);

			foreach ($versions as $version => $row) {
				if (in_array($version, $known, true)) {
					continue;
				}

				$moduleMissing = str_starts_with($namespace, 'module:')
					&& !is_dir($this->appRoot.'/module/'.substr($namespace, strlen('module:')));

				$orphans[] = [
					'namespace' => $namespace,
					'version' => $version,
					'name' => $row['name'],
					'moduleMissing' => $moduleMissing,
				];
			}
		}

		return $orphans;
	}

	/**
	 * Applied migrations whose file no longer hashes to what was recorded. Reported, never fatal.
	 *
	 * @return list<array{namespace: string, version: string, name: string}>
	 */
	public function mismatched(bool $readOnly = false): array {
		$applied = $this->appliedRows($readOnly);
		$mismatched = [];

		foreach ($this->discover() as $namespace => $migrations) {
			foreach ($migrations as $migration) {
				$row = $applied[$namespace][$migration->version] ?? null;
				if ($row !== null && $row['checksum'] !== $migration->checksum()) {
					$mismatched[] = [
						'namespace' => $namespace,
						'version' => $migration->version,
						'name' => $migration->name,
					];
				}
			}
		}

		return $mismatched;
	}

	// ─── Execution ─────────────────────────────────────────────────

	private function makeContext(bool $dryRun, string $mode): migrationContext {
		$sql = new sqlRunner(
			$this->databaseConnection,
			$this->tableNames,
			$dryRun,
			function (string $statement): void {
				$this->log($statement, 'sql');
			}
		);

		return new migrationContext(
			$sql,
			new schemaBuilder($sql, $this->inspector, $mode),
			$this->inspector,
			$this->appRoot,
			function (string $message): void {
				$this->log('  '.$message);
			}
		);
	}

	/**
	 * Apply pending migrations.
	 *
	 * @return list<discoveredMigration> Migrations applied (or that would be, when dry-running).
	 */
	public function up(?string $to = null, ?string $namespace = null, bool $dryRun = false): array {
		if (!$dryRun) {
			$this->ledger->ensureExists();
		}

		$applied = [];

		foreach ($this->pending($namespace, $dryRun) as $migration) {
			if ($to !== null && strcmp($migration->version, $to) > 0) {
				continue;
			}

			$this->applyOne($migration, $dryRun);
			$applied[] = $migration;
		}

		return $applied;
	}

	private function applyOne(discoveredMigration $migration, bool $dryRun): void {
		$instance = $migration->load();
		$context = $this->makeContext($dryRun, schemaBuilder::MODE_APPLY);

		$description = $instance->description();
		$this->log(
			"{$migration->namespace} {$migration->version} {$migration->name}"
				.($description !== '' ? " — {$description}" : ''),
			'migration'
		);

		$transactional = $instance->isTransactional() && !$dryRun;
		if ($transactional) {
			$this->databaseConnection->beginTransaction();
		}

		$startedAt = microtime(true);

		try {
			$instance->up($context);
			if ($transactional) {
				$this->databaseConnection->commit();
			}
		} catch (Throwable $e) {
			if ($transactional && $this->databaseConnection->inTransaction()) {
				$this->databaseConnection->rollBack();
			}

			throw new RuntimeException(
				"Migration {$migration->id()} failed: {$e->getMessage()}\n"
					."The database may be partially changed — MariaDB commits implicitly on DDL.\n"
					."File: {$migration->path}",
				0,
				$e
			);
		}

		if (!$dryRun) {
			$this->ledger->record($migration, (int)round((microtime(true) - $startedAt) * 1000), $this->kokoVersion);
		}
	}

	/**
	 * Reverse applied migrations, newest first.
	 *
	 * @return list<discoveredMigration>
	 */
	public function down(int $steps = 1, ?string $namespace = null, bool $dryRun = false): array {
		$applied = $this->appliedRows($dryRun);
		$candidates = [];

		foreach (array_reverse($this->namespaces()) as $candidate) {
			if ($namespace !== null && $candidate !== $namespace) {
				continue;
			}
			foreach (array_reverse($this->discover()[$candidate] ?? []) as $migration) {
				if (isset($applied[$candidate][$migration->version])) {
					$candidates[] = $migration;
				}
			}
		}

		$reversed = [];

		foreach (array_slice($candidates, 0, $steps) as $migration) {
			$instance = $migration->load();
			$context = $this->makeContext($dryRun, schemaBuilder::MODE_APPLY);

			$this->log("reverting {$migration->namespace} {$migration->version} {$migration->name}", 'migration');
			$instance->down($context);

			if (!$dryRun) {
				$this->ledger->forget($migration->namespace, $migration->version);
			}

			$reversed[] = $migration;
		}

		return $reversed;
	}

	/**
	 * Bring a database that predates the ledger up to date and stamp what it already has.
	 *
	 * Reconcilable migrations (the baseline) are re-run in reconcile mode, creating only what is
	 * missing. Everything else is stamped when its detect() says the change is already present,
	 * and left pending otherwise.
	 *
	 * @return array{stamped: list<discoveredMigration>, reconciled: list<string>, pending: list<discoveredMigration>}
	 */
	public function baseline(bool $dryRun = false): array {
		if (!$dryRun) {
			$this->ledger->ensureExists();
		}

		$stamped = [];
		$findings = [];
		$stillPending = [];

		foreach ($this->pending(null, $dryRun) as $migration) {
			$instance = $migration->load();

			if ($instance->isReconcilable()) {
				$context = $this->makeContext($dryRun, schemaBuilder::MODE_RECONCILE);
				$this->log("reconciling {$migration->namespace} {$migration->version} {$migration->name}", 'migration');

				$instance->up($context);
				$findings = [...$findings, ...$context->schema->getFindings()];

				if (!$dryRun) {
					$this->ledger->record($migration, 0, $this->kokoVersion);
				}
				$stamped[] = $migration;

				continue;
			}

			$detected = $instance->detect($this->makeContext(true, schemaBuilder::MODE_APPLY));

			if ($detected === true) {
				$this->log("already applied, stamping {$migration->namespace} {$migration->version} {$migration->name}", 'migration');
				if (!$dryRun) {
					$this->ledger->record($migration, 0, $this->kokoVersion);
				}
				$stamped[] = $migration;

				continue;
			}

			$stillPending[] = $migration;
		}

		return ['stamped' => $stamped, 'reconciled' => $findings, 'pending' => $stillPending];
	}

	/**
	 * Report what reconciliation would change, without writing or stamping anything.
	 *
	 * @return list<string>
	 */
	public function doctor(): array {
		$findings = [];

		foreach ($this->discover()['core'] ?? [] as $migration) {
			$instance = $migration->load();
			if (!$instance->isReconcilable()) {
				continue;
			}

			$context = $this->makeContext(true, schemaBuilder::MODE_RECONCILE);
			$instance->up($context);
			$findings = [...$findings, ...$context->schema->getFindings()];
		}

		return $findings;
	}

	// ─── Locking ───────────────────────────────────────────────────

	/** Serialise migration runs. The lock is released if the process dies. */
	public function withLock(callable $work): mixed {
		$acquired = (int)$this->databaseConnection->fetchValue(
			'SELECT GET_LOCK(:name, :timeout)',
			[':name' => self::LOCK_NAME, ':timeout' => self::LOCK_TIMEOUT_SECONDS]
		);

		if ($acquired !== 1) {
			throw new RuntimeException('Another migration run holds the lock. Try again shortly.');
		}

		try {
			return $work();
		} finally {
			$this->databaseConnection->fetchValue('SELECT RELEASE_LOCK(:name)', [':name' => self::LOCK_NAME]);
		}
	}
}
