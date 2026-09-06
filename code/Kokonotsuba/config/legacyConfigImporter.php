<?php

namespace Kokonotsuba\config;

use Kokonotsuba\database\databaseConnection;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Reads a pre-database install's config files and writes them as board_configs rows.
 *
 * The old layout was global/globalBoardConfig.php (the defaults every board started from) and
 * global/board-configs/board-{uid}.php (a board's own file, requiring the above and reassigning
 * what it wanted differently). legacyConfigConverter turns each into overrides; this finds the
 * files and stores the result:
 *
 *   - globalBoardConfig.php -> the global row, holding what the old site-wide file changed from
 *     today's schema defaults.
 *   - board-{uid}.php -> that board's row, holding only what it changed from the old site-wide
 *     file, so anything it merely inherited keeps following the global config.
 */
final class legacyConfigImporter {
	/**
	 * @param array<int, string> $boardConfigFiles Board uid => config file name, from the old
	 *                                             boards.config_name column. The first board of an
	 *                                             install was named board-{random}.php by install.php,
	 *                                             so the file name alone cannot always say whose it is.
	 */
	public function __construct(
		private readonly configRepository $configRepository,
		private readonly string $legacyDir,
		private readonly array $boardConfigFiles = []
	) {}

	/**
	 * Read the board => config file map an old install kept in boards.config_name.
	 *
	 * @return array<int, string> Empty when the column is gone or was never there.
	 */
	public static function boardConfigFilesFromDatabase(databaseConnection $db, string $boardTable): array {
		try {
			$rows = $db->fetchAllAsArray("SELECT board_uid, config_name FROM `{$boardTable}` WHERE config_name <> ''");
		} catch (\PDOException) {
			return [];
		}

		$files = [];
		foreach ($rows as $row) {
			$files[(int)$row['board_uid']] = basename((string)$row['config_name']);
		}

		return $files;
	}

	public function globalConfigFile(): string {
		return rtrim($this->legacyDir, '/') . '/globalBoardConfig.php';
	}

	public function hasSources(): bool {
		return is_file($this->globalConfigFile());
	}

	/**
	 * Convert the files without writing anything.
	 *
	 * @return array{global: array<string, mixed>, boards: array<int, array<string, mixed>>}
	 */
	public function load(): array {
		$schemaDefaults = configSchema::getDefaults();
		$legacyGlobal = self::loadLegacyConfig($this->globalConfigFile());
		$globalOverrides = legacyConfigConverter::extractOverrides($legacyGlobal, $schemaDefaults);

		// A board is diffed against the legacy global, not the schema defaults, so a value it simply
		// inherited stays inherited instead of freezing a copy of it.
		$legacyGlobalValues = $schemaDefaults;
		foreach ($globalOverrides as $dotpath => $value) {
			$legacyGlobalValues[$dotpath] = $value;
		}

		$boards = [];
		foreach ($this->boardFiles() as $boardUid => $boardFile) {
			$boards[$boardUid] = legacyConfigConverter::extractOverrides(
				self::loadLegacyConfig($boardFile),
				$legacyGlobalValues
			);
		}

		return ['global' => $globalOverrides, 'boards' => $boards];
	}

	/**
	 * Every board config file on disk, keyed by board uid.
	 *
	 * The boards table's own record wins; a board-{uid}.php not recorded there is taken by name.
	 *
	 * @return array<int, string> Board uid => path.
	 */
	private function boardFiles(): array {
		$dir = rtrim($this->legacyDir, '/') . '/board-configs/';
		$files = [];

		foreach ($this->boardConfigFiles as $boardUid => $name) {
			if (is_file($dir . $name)) {
				$files[(int)$boardUid] = $dir . $name;
			}
		}

		foreach (glob($dir . 'board-*.php') ?: [] as $boardFile) {
			if (!preg_match('/board-(\d+)\.php$/', basename($boardFile), $m)) {
				continue; // board-template.php and friends
			}

			$files[(int)$m[1]] ??= $boardFile;
		}

		return $files;
	}

	/**
	 * Store what load() produced.
	 *
	 * @param bool $keepExisting Leave a scope alone when it already has overrides, rather than
	 *                           replacing them - what the upgrade migration wants, since the panel
	 *                           may have been used since.
	 * @param callable(string):void|null $report Receives one line per scope.
	 * @return array{written: int, kept: int}
	 */
	public function write(array $plan, bool $keepExisting, ?callable $report = null): array {
		$report ??= static function (string $message): void {};
		$written = 0;
		$kept = 0;

		$scopes = [GLOBAL_BOARD_UID => $plan['global']] + $plan['boards'];

		foreach ($scopes as $boardUid => $overrides) {
			$label = $boardUid === GLOBAL_BOARD_UID ? 'global config' : "board {$boardUid}";

			if ($keepExisting && $this->configRepository->getOverridesByBoardUid($boardUid) !== []) {
				$report("  {$label} already has overrides, left as is");
				$kept++;
				continue;
			}

			if ($overrides === []) {
				$this->configRepository->deleteOverridesForBoardUid($boardUid);
			} else {
				$this->configRepository->saveOverridesForBoardUid($boardUid, $overrides);
			}

			$report("  {$label} written (" . count($overrides) . ' override(s))');
			$written++;
		}

		return ['written' => $written, 'kept' => $kept];
	}

	/**
	 * Load a legacy config file and return the $config array it builds.
	 *
	 * The old files are a cascade of `require`s ending in assignments to $config, so including one
	 * yields the fully-resolved array. It is included in a function scope to keep whatever else it
	 * defines out of the caller.
	 */
	public static function loadLegacyConfig(string $file): array {
		$config = [];
		require $file;

		return is_array($config) ? $config : [];
	}
}
