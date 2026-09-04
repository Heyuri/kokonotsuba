<?php

namespace Kokonotsuba\install;

use Kokonotsuba\account\accountRepository;
use Kokonotsuba\board\boardRepository;
use Kokonotsuba\cache\path_cache\boardPathRepository;
use Kokonotsuba\config\siteSettings;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\userRole;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

use function Puchiko\strings\sanitizeStr;

/**
 * Runs the install: check the credentials, write the config, migrate the schema, then create the
 * first board and the admin account.
 *
 * Everything that can be undone is undone when a later step fails — the database work runs in one
 * transaction, directories created along the way are removed, and config files that were replaced
 * are put back. The schema itself is the exception: MariaDB commits implicitly on DDL, so a
 * migration that fails halfway leaves the tables it already made. Re-running the installer against
 * that database is safe, since the ledger knows what was applied.
 */
final class installer {
	private const MARKER_FILE = 'global/.installed';

	/** Marker written by installs predating the move into global/. */
	private const LEGACY_MARKER_FILE = '.installed';

	private configFileWriter $configWriter;

	/** @var list<string> Directories created during this run, for rollback. */
	private array $createdPaths = [];

	/** @var list<string> Files created during this run, for rollback. */
	private array $createdFiles = [];

	public function __construct(
		private readonly string $appRoot,
		private readonly array $tableNames,
		private readonly string $kokoVersion,
		private readonly installDefaults $defaults
	) {
		$this->configWriter = new configFileWriter();
	}

	/** Whether this instance has already been installed. */
	public static function isInstalled(string $appRoot): bool {
		$appRoot = rtrim($appRoot, '/');

		return file_exists($appRoot.'/'.self::MARKER_FILE)
			|| file_exists($appRoot.'/'.self::LEGACY_MARKER_FILE);
	}

	public function run(installInput $input): installResult {
		$result = new installResult();

		try {
			$pdo = $this->connect($input, $result);
			if ($pdo === null) {
				return $result;
			}

			if (!$this->assertNothingInstalled($pdo, $input, $result)) {
				return $result;
			}

			$this->writeConfigFiles($input, $result);

			$connection = $this->openConnection($input);
			$this->migrate($connection, $input, $result);
			$this->createBoardAndAdmin($connection, $input, $result);
			$this->lockInstaller($result);

			$this->configWriter->discardBackups();
			$this->addFollowUp($result);
		} catch (Throwable $e) {
			$this->rollbackFilesystem();
			$this->configWriter->rollback();

			$result->add(installStep::fail(
				'Install rolled back',
				$e->getMessage()
			));
		}

		return $result;
	}

	// ─── Steps ─────────────────────────────────────────────────

	/** Connect with the credentials as typed, before anything is written anywhere. */
	private function connect(installInput $input, installResult $result): ?PDO {
		try {
			$pdo = new PDO(
				$input->databaseDsn(),
				$input->value('db_user'),
				$input->value('db_password'),
				[
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => false,
				]
			);
		} catch (PDOException $e) {
			$advice = databaseErrorAdvice::forError(
				$e->getMessage(),
				$input->value('db_host'),
				$input->value('db_user'),
				$input->value('db_name')
			);

			$result->add(installStep::fail('Database connection', $advice->message, $advice->fix));

			return null;
		}

		$version = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
		$result->add(installStep::ok(
			'Database connection',
			'Connected to '.$input->value('db_name').' on '.$input->value('db_host').' (server '.$version.').'
		));

		if (!$this->serverIsSupported($version)) {
			$result->add(installStep::warn(
				'Database version',
				'MariaDB 10.3 or MySQL 8 and newer are what the schema is built against; '.$version.' may reject parts of it.'
			));
		}

		return $pdo;
	}

	/** Refuse to run against a database that already has accounts or boards. */
	private function assertNothingInstalled(PDO $pdo, installInput $input, installResult $result): bool {
		$existing = [];

		foreach (['ACCOUNT_TABLE' => 'account', 'BOARD_TABLE' => 'board'] as $key => $noun) {
			$table = $this->tableNames[$key];

			if (!$this->tableExists($pdo, $input->value('db_name'), $table)) {
				continue;
			}

			// The GLOBAL board is seeded by a migration, so it does not count as a real board.
			$where = $key === 'BOARD_TABLE' ? ' WHERE board_uid > 0' : '';
			$count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`{$where}")->fetchColumn();

			if ($count > 0) {
				$existing[] = $count.' '.$noun.($count === 1 ? '' : 's');
			}
		}

		if ($existing !== []) {
			$result->add(installStep::fail(
				'Existing install',
				'The database "'.$input->value('db_name').'" already holds '.implode(' and ', $existing)
					.'. Installing again would add a second admin account to a live site. Use an empty database, '
					.'or delete install.php and log in with the account you already have.'
			));

			return false;
		}

		$result->add(installStep::ok('Existing install', 'No accounts or boards in this database yet.'));

		return true;
	}

	/** Write databaseSettings.php and global/siteSettings.php, keeping any secrets already set. */
	private function writeConfigFiles(installInput $input, installResult $result): void {
		$databaseSettingsPath = $this->appRoot.'/databaseSettings.php';
		$siteSettingsPath = $this->appRoot.'/global/siteSettings.php';

		$existingDatabase = $this->readArrayFile($databaseSettingsPath);
		$existingSite = $this->readArrayFile($siteSettingsPath);

		$this->configWriter->write(
			$databaseSettingsPath,
			configFileWriter::render(
				$input->databaseSettings((string)($existingDatabase['ANON_IP_SALT'] ?? '')),
				"Database credentials, written by install.php.\n"
					."Not tracked by git: it is yours, and an update never touches it.\n"
					.'Table names are not configurable and live in tables.php.',
				['ANON_IP_SALT' => "Secret salt for IP anonymisation (anonIp module). Keep it out of the database."]
			)
		);

		$this->configWriter->write(
			$siteSettingsPath,
			configFileWriter::render(
				$input->siteSettings(array_map('strval', array_filter($existingSite, 'is_scalar'))),
				"Site-specific globals, written by install.php.\n"
					."These override the defaults in globalconfig.php, which stays tracked by git.\n"
					.'TRIPSALT and IDSEED must never change once posts exist: doing so changes everyone\'s tripcodes and IDs.'
			),
			0644
		);

		// Anything reading the config later in this request must see the file we just wrote.
		siteSettings::forget($siteSettingsPath);

		$result->add(installStep::ok(
			'Configuration written',
			'databaseSettings.php and global/siteSettings.php, with a freshly generated tripcode salt.'
		));
	}

	private function migrate(databaseConnection $connection, installInput $input, installResult $result): void {
		$ledger = new migrationLedger($connection, $this->tableNames['SCHEMA_MIGRATION_TABLE']);

		$runner = new migrationRunner(
			$connection,
			$ledger,
			new schemaInspector($connection, $input->value('db_name')),
			$this->tableNames,
			$this->appRoot,
			$this->kokoVersion,
			// Logged rather than shown: if a migration fails, its own message is what the page
			// reports, and the log is where the run leading up to it can be read back.
			static function (string $message, string $level): void {
				if ($level === 'migration') {
					error_log('install: '.$message);
				}
			}
		);

		$applied = $runner->withLock(static fn (): array => $runner->up());

		$result->add(installStep::ok(
			'Database schema',
			count($applied).' migration'.(count($applied) === 1 ? '' : 's').' applied: '
				.implode(', ', array_map(static fn ($migration): string => $migration->name, $applied))
		));
	}

	/**
	 * Create the board's directories and rows, and the admin account, as one unit.
	 *
	 * The filesystem work happens inside the transaction so that a failure anywhere leaves neither
	 * a board directory without a row nor a row without a directory.
	 */
	private function createBoardAndAdmin(databaseConnection $connection, installInput $input, installResult $result): void {
		$config = getTemplateConfigArray();

		$boardRepository = new boardRepository($connection, $this->tableNames['BOARD_TABLE']);
		$boardPathRepository = new boardPathRepository($connection, $this->tableNames['BOARD_PATH_CACHE_TABLE']);
		$accountRepository = new accountRepository($connection, $this->tableNames['ACCOUNT_TABLE']);
		$transactionManager = new transactionManager($connection);

		$identifier = $input->value('board_identifier');
		$boardPath = $this->defaults->boardsPath().$identifier.'/';

		$boardUid = $transactionManager->run(function () use (
			$boardRepository, $boardPathRepository, $accountRepository, $input, $config, $identifier, $boardPath
		): int {
			$this->makeDirectory($this->defaults->boardsPath());
			$this->makeDirectory($boardPath);

			// Sanitised the same way boardService does it, so a board made here and one made from
			// the admin panel store their titles identically.
			$boardRepository->addNewBoard(
				$identifier,
				sanitizeStr($input->value('board_title')),
				sanitizeStr($input->value('board_sub_title')),
				1,
				'',
				''
			);

			$boardUid = (int)$boardRepository->getLastBoardUID();
			if ($boardUid <= 0) {
				throw new RuntimeException('The board row was inserted but came back without a UID.');
			}

			// The storage and CDN directories are named after the UID, which only exists once the
			// row does. The CDN layout matches board::getCdnDir(): {CDN_DIR}{uid}/.
			$storageDirectoryName = 'storage-'.$boardUid;
			$boardRepository->updateBoardByUID($boardUid, ['storage_directory_name' => $storageDirectoryName]);
			$this->makeDirectory($this->appRoot.'/global/board-storages/'.$storageDirectoryName);

			$uploadDirectory = $config['USE_CDN']
				? rtrim((string)$config['CDN_DIR'], '/').'/'.$boardUid.'/'
				: $boardPath;

			$this->makeDirectory($uploadDirectory.$config['IMG_DIR']);
			$this->makeDirectory($uploadDirectory.$config['THUMB_DIR']);

			$boardPathRepository->insertPath($boardUid, $boardPath);

			$this->writeBoardFiles($boardPath, $boardUid, $config);

			$accountRepository->addNewAccount(
				$input->value('admin_username'),
				userRole::LEV_ADMIN->value,
				password_hash($input->value('admin_password'), PASSWORD_DEFAULT)
			);

			return $boardUid;
		});

		// Linked at koko.php rather than the directory: the static index does not exist until the
		// first request renders it.
		$result->setBoard(
			rtrim($input->value('website_url'), '/').'/'.$identifier.'/'.$config['LIVE_INDEX_FILE'],
			$boardPath
		);
		$result->add(installStep::ok(
			'First board',
			'/'.$identifier.'/ created as board '.$boardUid.' in '.$boardPath
		));
		$result->add(installStep::ok(
			'Admin account',
			'"'.$input->value('admin_username').'" created with the Admin role.'
		));
	}

	/**
	 * The board's entry point and its UID file.
	 *
	 * No index.html is written: the board's static index is rendered by the first request to
	 * koko.php, which rebuilds it whenever it is missing. A placeholder here would only bounce
	 * that request straight back at itself.
	 */
	private function writeBoardFiles(string $boardPath, int $boardUid, array $config): void {
		$liveIndexFile = (string)$config['LIVE_INDEX_FILE'];

		$this->writeFile(
			$boardPath.$liveIndexFile,
			'<?php require_once '.var_export($this->appRoot.'/'.$liveIndexFile, true).";\n"
		);

		$this->writeFile($boardPath.'boardUID.ini', "board_uid = {$boardUid}\n");
	}

	private function lockInstaller(installResult $result): void {
		$marker = $this->appRoot.'/'.self::MARKER_FILE;
		$stamp = 'installed '.date('c').' by kokonotsuba '.$this->kokoVersion."\n";

		if (@file_put_contents($marker, $stamp) === false) {
			$result->add(installStep::warn(
				'Installer lock',
				'Could not write '.$marker.'. Delete install.php now — without the marker it will run again.'
			));

			return;
		}

		$result->add(installStep::ok('Installer lock', 'global/.installed written; the installer will not run again.'));
	}

	private function addFollowUp(installResult $result): void {
		$identity = processIdentity::current();

		$result->addFollowUpCommand('rm '.escapeshellarg($this->appRoot.'/install.php'));
		$result->addFollowUpCommand('sudo chmod 640 '.escapeshellarg($this->appRoot.'/databaseSettings.php'));
		$result->addFollowUpCommand(
			'sudo chown root:'.$identity->group.' '.escapeshellarg($this->appRoot)
				.' && sudo chmod 750 '.escapeshellarg($this->appRoot)
		);
	}

	// ─── Plumbing ─────────────────────────────────────────────────

	/** The application's own connection object, which the repositories and migrator both take. */
	private function openConnection(installInput $input): databaseConnection {
		databaseConnection::createInstance($input->databaseSettings());
		$connection = databaseConnection::getInstance();

		if (!$connection instanceof databaseConnection) {
			throw new RuntimeException('The database connection could not be created.');
		}

		return $connection;
	}

	private function tableExists(PDO $pdo, string $schema, string $table): bool {
		$statement = $pdo->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
		);
		$statement->execute([$schema, $table]);

		return (int)$statement->fetchColumn() > 0;
	}

	/** MariaDB reports "10.11.6-MariaDB"; MySQL reports "8.0.36". */
	private function serverIsSupported(string $version): bool {
		$number = preg_replace('/[^0-9.].*$/', '', $version) ?? '';
		if ($number === '') {
			return true;
		}

		if (stripos($version, 'mariadb') !== false) {
			return version_compare($number, '10.3', '>=');
		}

		return version_compare($number, '8.0', '>=');
	}

	/** @return array<string, mixed> */
	private function readArrayFile(string $path): array {
		if (!is_file($path) || !is_readable($path)) {
			return [];
		}

		try {
			$values = include $path;
		} catch (Throwable) {
			return [];
		}

		return is_array($values) ? $values : [];
	}

	private function makeDirectory(string $path): void {
		$path = rtrim($path, '/');

		if (is_dir($path)) {
			return;
		}

		if (!@mkdir($path, 0770, true) && !is_dir($path)) {
			throw new RuntimeException(
				'Could not create '.$path.'. Check that '.dirname($path).' is writable by '
					.processIdentity::current()->user.'.'
			);
		}

		$this->createdPaths[] = $path;
	}

	private function writeFile(string $path, string $contents): void {
		if (@file_put_contents($path, $contents) === false) {
			throw new RuntimeException('Could not write '.$path.'.');
		}

		$this->createdFiles[] = $path;
	}

	/** Remove what this run created, newest first, and only what it created. */
	private function rollbackFilesystem(): void {
		foreach (array_reverse($this->createdFiles) as $file) {
			@unlink($file);
		}

		foreach (array_reverse($this->createdPaths) as $path) {
			@rmdir($path);
		}

		$this->createdFiles = [];
		$this->createdPaths = [];
	}
}
