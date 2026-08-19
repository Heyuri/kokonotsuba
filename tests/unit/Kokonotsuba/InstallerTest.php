<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Koko\Tests\Framework\InstallerHarness;

/**
 * Tests for install.php.
 *
 * The installer runs once, unattended, and owns the schema - nothing re-checks its work
 * afterwards, so a mistake in it surfaces much later as a broken board. Its declarations are
 * loaded without running the front-controller part (see InstallerHarness) and exercised here.
 *
 * The DDL is executed against a real server in tests/integration/installSchema.php; this file
 * covers what can be checked without a database: identifier validation, the config-template
 * builder, and the shape/ordering of the statements the installer emits.
 */
final class InstallerTest extends TestCase {

	protected function setUp(): void {
		InstallerHarness::load();
	}

	/** Call an installer function by name. */
	private function call(string $function, ...$args) {
		return (InstallerHarness::fn($function))(...$args);
	}

	/**
	 * setNestedInstallConfig() takes its config array by reference, which a variadic forward
	 * would drop, so it gets its own caller.
	 */
	private function setNested(array &$config, string $dotpath, $value): void {
		$setter = InstallerHarness::fn('setNestedInstallConfig');
		$setter($config, $dotpath, $value);
	}

	// ---- sanitizeTableName --------------------------------------------------

	public function testValidTableNamesPassThroughUnchanged(): void {
		foreach (['posts', 'post_table', 'Posts2', '_private', '9lives'] as $name) {
			$this->assertSame($name, $this->call('sanitizeTableName', $name));
		}
	}

	public function testInjectionAttemptsAreRejected(): void {
		$hostile = [
			'posts; DROP TABLE accounts',
			'posts`',
			'posts users',
			"posts'",
			'posts-table',
			'posts.accounts',
			'',
			'投稿',
		];

		foreach ($hostile as $name) {
			$this->assertThrows(fn() => $this->call('sanitizeTableName', $name), \InvalidArgumentException::class);
		}
	}

	/**
	 * The validator anchors with `$`, which in PCRE also matches immediately before a trailing
	 * newline - so "posts\n" is accepted and interpolated into the DDL verbatim. Table names come
	 * from databaseSettings.php rather than a request, so this is not reachable input today, but
	 * the function exists to be the guard on that interpolation. `\z` (or the D modifier) closes it.
	 */
	public function testATrailingNewlineIsRejected(): void {
		$this->assertThrows(fn() => $this->call('sanitizeTableName', "posts\n"), \InvalidArgumentException::class);
	}

	// ---- setNestedInstallConfig ---------------------------------------------

	public function testSetNestedConfigWritesADotPath(): void {
		$config = [];
		$this->setNested($config, 'modules.soudane.enabled', true);
		$this->assertSame(true, $config['modules']['soudane']['enabled']);
	}

	public function testSetNestedConfigWritesATopLevelKey(): void {
		$config = ['IMG_DIR' => 'src/'];
		$this->setNested($config, 'IMG_DIR', 'files/');
		$this->assertSame('files/', $config['IMG_DIR']);
	}

	public function testSetNestedConfigKeepsSiblingsIntact(): void {
		$config = ['modules' => ['soudane' => ['enabled' => true], 'notes' => ['enabled' => false]]];
		$this->setNested($config, 'modules.soudane.limit', 5);

		$this->assertSame(true, $config['modules']['soudane']['enabled']);
		$this->assertSame(5, $config['modules']['soudane']['limit']);
		$this->assertSame(false, $config['modules']['notes']['enabled']);
	}

	/** A scalar standing where a branch is needed is replaced, not appended to. */
	public function testSetNestedConfigReplacesAScalarOnThePath(): void {
		$config = ['modules' => 'off'];
		$this->setNested($config, 'modules.soudane.enabled', true);
		$this->assertSame(true, $config['modules']['soudane']['enabled']);
	}

	public function testSetNestedConfigIsIdempotent(): void {
		$first = [];
		$second = [];
		$this->setNested($first, 'a.b.c', 1);
		$this->setNested($second, 'a.b.c', 1);
		$this->setNested($second, 'a.b.c', 1);
		$this->assertSame($first, $second);
	}

	// ---- getTemplateConfigArray ---------------------------------------------

	public function testTemplateConfigCarriesTheGlobalConfigBase(): void {
		$config = $this->call('getTemplateConfigArray');
		$this->assertIsArray($config);

		// createBoardAndFiles() reads these to build the board's upload directories; a missing
		// key would silently create the board's files in the wrong place.
		$this->assertTrue(array_key_exists('IMG_DIR', $config), 'IMG_DIR missing from the template config');
		$this->assertTrue(array_key_exists('THUMB_DIR', $config), 'THUMB_DIR missing from the template config');
		$this->assertTrue(array_key_exists('USE_CDN', $config), 'USE_CDN missing from the template config');
	}

	public function testTemplateConfigCarriesModuleDefaults(): void {
		$config = $this->call('getTemplateConfigArray');
		$this->assertTrue(isset($config['modules']) && is_array($config['modules']), 'no module defaults were collected');

		// Every module shipping a config.php should appear under its own key.
		foreach (glob(KOKO_TEST_ROOT . '/module/*/config.php') ?: [] as $moduleFile) {
			$moduleName = basename(dirname($moduleFile));
			$definition = require $moduleFile;
			if (!is_array($definition)) {
				continue;
			}
			unset($definition['_group'], $definition['_module']);
			if ($definition === []) {
				continue;
			}
			$this->assertTrue(
				array_key_exists($moduleName, $config['modules']),
				"module '$moduleName' declares defaults but none reached the template config"
			);
		}
	}

	// ---- the emitted DDL ----------------------------------------------------

	/** A stand-in PDO that records the statements the installer prepares. */
	private function recordingPdo(): object {
		return new class {
			/** @var string[] */
			public array $queries = [];

			public function prepare(string $query): object {
				$this->queries[] = $query;
				return new class {
					public function execute(): bool { return true; }
				};
			}
		};
	}

	/** @return string[] the CREATE statements the installer runs, in order */
	private function emittedStatements(): array {
		$pdo = $this->recordingPdo();
		$class = InstallerHarness::cls('tableCreator');

		$tables = [];
		foreach (array_keys(InstallerHarness::installerTableKeys()) as $logicalKey) {
			$tables[$logicalKey] = strtolower($logicalKey);
		}

		(new $class($pdo))->createTables($tables);
		return $pdo->queries;
	}

	/**
	 * Every table the installer names in its $tables map should be a table it creates. Two
	 * (BAN_TABLE, BANNER_TABLE) are passed to createTables() but have no CREATE statement, so a
	 * fresh install ends up without them.
	 */
	public function testEveryDeclaredTableIsCreated(): void {
		$declared = array_keys(InstallerHarness::installerTableKeys());
		$created = InstallerHarness::createdTableKeys();
		$missing = array_diff($declared, $created);

		$this->assertSame(
			[],
			array_values($missing),
			'declared in the installer\'s table map but never created: ' . implode(', ', $missing)
		);
	}

	public function testEveryCreatedTableIsDeclared(): void {
		$declared = array_keys(InstallerHarness::installerTableKeys());
		$stray = array_diff(InstallerHarness::createdTableKeys(), $declared);

		$this->assertSame(
			[],
			array_values($stray),
			'created but absent from the table map (so its name is not configurable): ' . implode(', ', $stray)
		);
	}

	public function testOneStatementIsEmittedPerCreatedTable(): void {
		$this->assertCount(count(InstallerHarness::createdTableKeys()), $this->emittedStatements());
	}

	/**
	 * MariaDB resolves a foreign key at CREATE time, so a table must never reference one that is
	 * created after it.
	 */
	public function testForeignKeysOnlyReferenceAlreadyCreatedTables(): void {
		$seen = [];

		foreach ($this->emittedStatements() as $statement) {
			preg_match('/CREATE TABLE IF NOT EXISTS `?([a-z_]+)`?/i', $statement, $self);
			$table = $self[1] ?? '';

			preg_match_all('/REFERENCES `?([a-z_]+)`?/i', $statement, $references);
			foreach ($references[1] as $referenced) {
				$this->assertTrue(
					$referenced === $table || isset($seen[$referenced]),
					"$table references $referenced, which is created later (or not at all)"
				);
			}

			$seen[$table] = true;
		}
	}

	/** Every statement goes through sanitizeTableName() rather than interpolating a raw name. */
	public function testTableNamesAreValidatedBeforeInterpolation(): void {
		$class = InstallerHarness::cls('tableCreator');
		$pdo = $this->recordingPdo();

		$this->assertThrows(
			fn() => (new $class($pdo))->createTables(['BOARD_TABLE' => 'boards`; DROP TABLE accounts; --']),
			\InvalidArgumentException::class
		);
		$this->assertCount(0, $pdo->queries, 'a statement was prepared before validation failed');
	}

	// ---- the table map against databaseSettings.php -------------------------

	/**
	 * Each entry of the installer's table map reads a key out of databaseSettings.php. A key the
	 * settings file does not define reads as null, which sanitizeTableName() then rejects - the
	 * install dies half-way. The file is untracked, so this only runs where it exists.
	 */
	public function testTheTableMapOnlyReadsKeysDatabaseSettingsDefines(): void {
		$settingsFile = KOKO_TEST_ROOT . '/databaseSettings.php';
		if (!file_exists($settingsFile)) {
			$this->pass();
			return;
		}

		$settings = require $settingsFile;
		$missing = [];
		foreach (InstallerHarness::installerTableKeys() as $logicalKey => $settingsKey) {
			if (!array_key_exists($settingsKey, $settings)) {
				$missing[] = $settingsKey;
			}
		}

		$this->assertSame([], $missing, 'read by install.php but absent from databaseSettings.php: ' . implode(', ', $missing));
	}

	// ---- getRootPath's koko.php parsing -------------------------------------

	/**
	 * The board's koko.php is a one-line stub pointing at the backend; getRootPath() recovers the
	 * backend directory from it with a regex. Getting this wrong makes ROOTPATH the *board*
	 * directory, and the very next require in install.php fails.
	 */
	public function testTheRootPathPatternMatchesTheDocumentedStub(): void {
		$pattern = InstallerHarness::rootPathPattern();

		$supported = [
			"<?php require '/var/www/kokonotsuba/koko.php';" => '/var/www/kokonotsuba',
			'<?php require "/var/www/kokonotsuba/koko.php";' => '/var/www/kokonotsuba',
			"<?php require_once '/srv/koko/koko.php'; ?>" => '/srv/koko',
		];

		foreach ($supported as $line => $expectedRoot) {
			$this->assertSame(1, preg_match($pattern, $line, $m), "did not match: $line");
			$this->assertSame($expectedRoot, dirname($m[1]));
		}
	}

	/** The stub must not be confused by the board's own filename appearing elsewhere. */
	public function testTheRootPathPatternIgnoresLinesWithoutARequire(): void {
		$pattern = InstallerHarness::rootPathPattern();

		foreach (["<?php // koko.php lives in /var/www\n", "\$path = '/var/www/koko.php';\n"] as $line) {
			$this->assertSame(0, preg_match($pattern, $line), "unexpectedly matched: $line");
		}
	}
}
