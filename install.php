<?php

function getRootPath() {
	$kokoFile = __DIR__ . DIRECTORY_SEPARATOR . 'koko.php';
	if (!file_exists($kokoFile)) {
		die(
			"The file <i>" . __DIR__ . DIRECTORY_SEPARATOR . "koko.php</i> couldn't be found. Please create it with the following code:<br>" .
			"<code>&lt;?php require_once '/path/to/kokonotsuba/koko.php'; ?&gt;</code>"
		);
	}

	$fileHandle = fopen($kokoFile, 'r');
	if (!$fileHandle) {
		die("Error: Unable to open <i>koko.php</i>.");
	}

	while (($line = fgets($fileHandle)) !== false) {
		if (preg_match("/require(?:_once)? ['\"](.*?koko\.php)['\"];/", $line, $matches)) {
			fclose($fileHandle);
			// Use dirname to extract the directory path from the matched file
			return dirname($matches[1]);
		}
	}

	fclose($fileHandle);
	return __DIR__;
}


define('ROOTPATH', getRootPath());

require ROOTPATH . '/code/libraries/lib_common.php';
require ROOTPATH . '/constants.php';

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

$extensions = [
	'mbstring',
	'pdo',
	'gd',
	'bcmath',
];

$commands = [
	'ffmpeg',
	'exiftool'
];

function checkExtensions(array $extensions) {
	$results = [];
	foreach ($extensions as $extension) {
		$results[$extension] = extension_loaded($extension);
	}
	return $results;
}

function checkCommands(array $commands) {
	$results = [];
	foreach ($commands as $command) {
		$results[$command] = isCommandAvailable($command);
	}
	return $results;
}

function isCommandAvailable(string $command): bool {
	$output = null;
	$status = null;
	exec("which " . escapeshellarg($command), $output, $status);
	return $status === 0 && !empty($output);
}

function getGlobalConfig() {
	require ROOTPATH . '/global/globalconfig.php';
	return $config;
}

function getBoardStorageDir() {
	return ROOTPATH.'/global/board-storages/';
}

function generateNewBoardConfigFile() {
	$templateConfigPath = ROOTPATH . '/global/board-configs/board-template.php';
	$newConfigFileName = 'board-' . generateUid() . '.php';
	$boardConfigsDirectory = ROOTPATH . '/global/board-configs/';
	if (!copyFileWithNewName($templateConfigPath, $newConfigFileName, $boardConfigsDirectory)) {
		throw new Exception("Failed to copy new config file");
	}
	return $newConfigFileName;
}

// Function to sanitize table names using regular expression validation
function sanitizeTableName($tableName) {
	// Validat e table name: Only allow alphanumeric characters and underscores
	if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
		throw new InvalidArgumentException("Invalid table name: $tableName. Only alphanumeric characters and underscores are allowed.");
	}
	return $tableName;
}

function getTemplateConfigArray() {
	require ROOTPATH . '/global/board-configs/board-template.php';
	return $config;
}

function createBoardAndFiles($boardTable) {
	include ROOTPATH . '/code/libraries/lib_file.php';

	//create board
	$board_identifier = $_POST['board-identifier'] ?? '';
	$board_title = $_POST['board-title'] ?? '';
	$board_sub_title = $_POST['board-sub-title'] ?? '';
	$board_path = $_POST['board-path'] ?? '';


	$globalConfig = getGlobalConfig();
	$mockConfig = getTemplateConfigArray();

	$nextBoardUID = $boardTable->getLastBoardUID() + 1;

	$dataDirName = 'storage-'.$nextBoardUID;
	$dataDir = getBoardStorageDir().'/'.$dataDirName;
	//create physical board files
	$fileUploadedImgDirectory = $globalConfig['USE_CDN']
		? $globalConfig['CDN_DIR'].$board_identifier.'/'.$mockConfig['IMG_DIR'].'/'
		: $board_path . $mockConfig['IMG_DIR'].'/';
	$fileUploadedThumbDirectory = $globalConfig['USE_CDN']
		? $globalConfig['CDN_DIR'].$board_identifier.'/'.$mockConfig['THUMB_DIR'].'/'
		: $board_path.$mockConfig['THUMB_DIR'].'/';

	//create upload dirs
	createDirectory($fileUploadedImgDirectory);
	createDirectory($fileUploadedThumbDirectory);
	//create dat
	createDirectory($dataDir);

	//generate new config
	$boardConfigName = generateNewBoardConfigFile();
	$boardTable->addFirstBoard($board_identifier, $board_title, $board_sub_title, $boardConfigName, $dataDirName);
	$boardUIDforBootstrapFile = $boardTable->getLastBoardUID();
	createFileAndWriteText($board_path, 'boardUID.ini', "board_uid = $boardUIDforBootstrapFile");
}

class html {
	private $dbSettings;

	public function __construct($dbSettings) {
		$this->dbSettings = $dbSettings;
	}

	public function drawHeader() {
		echo '<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">

			<!-- Prevent caching -->
			<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, proxy-revalidate">
			<meta http-equiv="Pragma" content="no-cache">
			<meta http-equiv="Expires" content="0">

			<!-- Prevent archiving by search engines -->
			<meta name="robots" content="noarchive, noindex, nofollow">
			<meta http-equiv="X-Robots-Tag" content="noindex, nofollow">

			<title>Kokonotsuba Installer</title>
		</head>
		<h1 class="page-head-title">Kokonotsuba Installer</h1>';
	}

	public function drawStyle() {
		echo '<style>
			.postblock {
				border: 1px solid #800043;
				background: #eeaa88;
			}
			.notice-text {
				padding-bottom: 20px;
				text-align:center;
			}

			body {
				background-color: #ffffee;
				color: #880000;
				font-size: 16px;
			}
		</style>';
	}

	public function drawInstallNotice() {
		echo '<div class="notice-text">
			<h2>Notice!</h2>
			<p>Kokonotsuba is a BBS software</p>
			<p>Read the instructions, other documentation or open an Issue on the <a href="https://github.com/Heyuri/kokonotsuba">repo</a> if there are any problems</p>
			<p>For more info: <a href="https://kokonotsuba.github.io/">see here</a></p>
		</div><hr size=1>';
	}

	public function drawRequiredExtentions() {
		global $extensions, $commands;
		$extentionResults = checkExtensions($extensions);
		$commandResults = checkCommands($commands);

		echo '<h3>Required Extensions</h3>
		<p>These are the extensions required for Kokonotsuba to work fully:</p>
		<ul>';
		foreach ($extentionResults as $extension => $isEnabled) {
			echo "<li>$extension: " . ($isEnabled ? 'enabled' : 'not enabled') . '</li>';
		}

		foreach($commandResults as $command => $isInstalled) {
			echo '<li>' . $command . ': ' . ($isInstalled ? 'enabled' : 'not enabled') . '</li>';
		}
		echo '</ul>';
	}

	public function drawImportantConfigValuesPreview() {
		$globalConfig = getGlobalConfig();

		$websiteURL = $globalConfig['WEBSITE_URL'];
		$staticURL = $globalConfig['STATIC_URL']; // eg. 'https://static.example.com/'
		$staticPath = $globalConfig['STATIC_PATH']; // eg. '/home/example/web/static/'

		echo '<h3>Config</h3>
		<p>Ensure these values are correctly set in global/globalconfig.php:</p>
		<table>
			<tr>
				<td>Static Path:</td>
				<td>' . htmlspecialchars($staticPath) . '</td>
			</tr>
			<tr>
				<td>Static URL:</td>
				<td>' . htmlspecialchars($staticURL) . '</td>
			</tr>
			<tr>
				<td>Website URL:</td>
				<td>' . htmlspecialchars($websiteURL) . '</td>
			</tr>
		</table>';
	}

	public function drawInstallForm() {
		echo '<form id="installation-form" action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" method="POST">
			<input type="hidden" name="action" value="install">
			<h3>Database Options</h3>
			<p>If you make any changes to these - make sure to update databaseSettings.php afterwards to match what you set</p>

	<table id="installation-form-database-settings-table">
		<tr> 
			<td class="postblock"> <label for "database-post-table-input">Post table</label></td>
			<td> <input id="database-post-table-input" name="POST_TABLE" value="'.htmlspecialchars($this->dbSettings['POST_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-qotelink-table-input">Quotelink table</label></td>
			<td> <input id="database-quotelink-table-input" name="QUOTE_LINK_TABLE" value="'.htmlspecialchars($this->dbSettings['QUOTE_LINK_TABLE']).'"> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-report-table-input">Report table</label></td>
			<td> <input id="database-report-table-input" name="REPORT_TABLE" value="'.htmlspecialchars($this->dbSettings['REPORT_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-ban-table-input">Ban table</label></td>
			<td> <input id="database-ban-table-input" name="BAN_TABLE" value="'.htmlspecialchars($this->dbSettings['BAN_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-board-table-input">Board table</label></td>
			<td> <input id="database-board-table-input" name="BOARD_TABLE" value="'.htmlspecialchars($this->dbSettings['BOARD_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-board-cache-table-input">Board path cache table</label></td>
			<td> <input id="database-board-cache-table-input" name="BOARD_PATH_CACHE_TABLE" value="'.htmlspecialchars($this->dbSettings['BOARD_PATH_CACHE_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-post-number-table-input">Post number table</label></td>
			<td> <input id="database-post-number-table-input" name="POST_NUMBER_TABLE" value="'.htmlspecialchars($this->dbSettings['POST_NUMBER_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-account-table-input">Account table</label></td>
			<td> <input id="database-account-table-input" name="ACCOUNT_TABLE" value="'.htmlspecialchars($this->dbSettings['ACCOUNT_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-actionlog-table-input">Action log table</label></td>
			<td> <input id="database-actionlog-table-input" name="ACTIONLOG_TABLE" value="'.htmlspecialchars($this->dbSettings['ACTIONLOG_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-thread-table-input">Thread table</label></td>
			<td> <input id="database-thread-table-input" name="THREAD_TABLE" value="'.htmlspecialchars($this->dbSettings['THREAD_TABLE']).'" required> </td>
		</tr>
		<tr>
			<td class="postblock"> <label for "database-thread-redirect-table-input">Thread redirect table</label></td>
			<td> <input id="database-thread-redirect-table-input" name="THREAD_REDIRECT_TABLE" value="'.htmlspecialchars($this->dbSettings['THREAD_REDIRECT_TABLE']).'" required> </td>
		</tr>
	</table>

			<h3>Admin Account</h3>
		<p>The username and password of the admin account, it can be changed at any time</p>
			<table id="installation-form-admin-account-table">
				<tr>
					<td class="postblock"> <label for "admin-username-input" >Admin username</label></td>
					<td> <input id="admin-username-input" name="admin-username" required> </td>
				</tr>
				<tr>
					<td class="postblock"> <label for "admin-password-input">Admin password</label></td>
					<td> <input type="password" id="admin-password-input" name="admin-password" required> </td>
				</tr>
			</table>
			<h3>First Board</h3>
		<p>This will be the first board on your kokonotsuba instance</p>
			<table id="installation-form-admin-account-table">
				<tr> 
					<td class="postblock"> <label for "first-board-identifier-input" >Board identifier</label></td>
					<td> <input id="first-board-identifier-input" name="board-identifier" placeholder="b" value="'.basename(__DIR__).'"> </td>
					<td> (leave blank if the board is in web root) </td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "first-board-title-input" >Board title</label></td>
					<td> <input id="first-board-title-input" name="board-title" placeholder="board@example.net" required> </td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "first-board-sub-title-input" >Board sub-title</label></td>
					<td> <input id="first-board-sub-title-input" name="board-sub-title" placeholder="an example board" required> </td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "first-board-path-input" >Board path</label></td>
					<td> <input id="first-board-path-input" name="board-path" placeholder="an example board" value="'.dirname(__FILE__).'/'.'" required> </td>
				</tr>
			</table>
			<input type="submit" value="Install">
		</form>';
	}

	public function drawFooter() {
		echo '<hr>';
	}
}

class tableCreator {
	private $db;	
	public function __construct($pdoConnection) {
		$this->db = $pdoConnection;

	}
	public function createTables($tableNames) {
		$sanitizedTableNames = array_map('sanitizeTableName', $tableNames);
	
		// Define the SQL queries using sanitized table names
		$queries = [
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['BOARD_TABLE']} (
				`board_uid` INT NOT NULL AUTO_INCREMENT,
				`board_identifier` TEXT,
				`board_title` TEXT NOT NULL,
				`board_sub_title` TEXT,
				`config_name` TEXT NOT NULL,
				`storage_directory_name` TEXT NOT NULL,
				`listed` BOOL DEFAULT TRUE,
				`date_added` DATE DEFAULT CURRENT_DATE,
				PRIMARY KEY(`board_uid`),
				INDEX(date_added)
			) ENGINE=InnoDB;",

			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['THREAD_TABLE']} (
				`insert_id` INT NOT NULL AUTO_INCREMENT,
				`thread_uid` VARCHAR(255) NOT NULL,
				`post_op_number` INT NOT NULL,
				`post_op_post_uid` INT NOT NULL,
				`boardUID` INT NOT NULL,
				`last_reply_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`last_bump_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`thread_created_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`insert_id`),
				CONSTRAINT fk_thread_boardUID FOREIGN KEY (`boardUID`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE,
				INDEX (`thread_uid`),
				INDEX (`last_reply_time`),
				INDEX (`last_bump_time`),
				INDEX (`thread_created_time`)
			) ENGINE=InnoDB;",

			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['POST_TABLE']} (
				`post_uid` INT NOT NULL AUTO_INCREMENT,
				`no` INT NOT NULL,
				`boardUID` INT NOT NULL,
				`thread_uid` VARCHAR(255) NOT NULL,
				`post_position` INT DEFAULT 0,
				`is_op` BOOLEAN NOT NULL,
				`root` TIMESTAMP NOT NULL,
				`time` INT NOT NULL,
				`md5chksum` TEXT,
				`category` TEXT,
				`tim` BIGINT NOT NULL,
				`fname` TEXT NOT NULL,
				`ext` TEXT NOT NULL,
				`imgw` SMALLINT NOT NULL,
				`imgh` SMALLINT NOT NULL,
				`imgsize` TEXT NOT NULL,
				`tw` SMALLINT NOT NULL,
				`th` SMALLINT NOT NULL,
				`pwd` TEXT NOT NULL,
				`now` TEXT NOT NULL,
				`name` TEXT NOT NULL,
				`tripcode` TEXT,
				`secure_tripcode` TEXT,
				`capcode` TEXT,
				`email` TEXT NOT NULL,
				`sub` TEXT NOT NULL,
				`com` TEXT NOT NULL,
				`host` TEXT NOT NULL,
				`status` TEXT,
				PRIMARY KEY (`post_uid`),
				CONSTRAINT fk_boardUID FOREIGN KEY (`boardUID`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE,
				CONSTRAINT fk_thread_uid FOREIGN KEY (`thread_uid`) REFERENCES `{$sanitizedTableNames['THREAD_TABLE']}`(`thread_uid`) ON DELETE CASCADE,
				INDEX (`thread_uid`),
				INDEX (`no`),
				FULLTEXT INDEX ft_com (com),
				FULLTEXT INDEX ft_sub (sub),
				FULLTEXT INDEX ft_name (name)
			) ENGINE=InnoDB;",

			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['POST_NUMBER_TABLE']} (
				`board_uid` INT NOT NULL,
				`post_number` INT NOT NULL DEFAULT 0,
				PRIMARY KEY (`board_uid`),
				CONSTRAINT fk_post_count_board_uid FOREIGN KEY (`board_uid`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE
			) ENGINE=InnoDB;",
	
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['QUOTE_LINK_TABLE']} (
				`quotelink_id` INT NOT NULL AUTO_INCREMENT,
				`board_uid` INT NOT NULL,
				`host_post_uid` INT NOT NULL,
				`target_post_uid` INT NOT NULL,
				PRIMARY KEY (`quotelink_id`),
				INDEX (`host_post_uid`),
				INDEX (`target_post_uid`),
				CONSTRAINT `fk_quote_link_host_post_uid` FOREIGN KEY (`host_post_uid`)
				REFERENCES `{$sanitizedTableNames['POST_TABLE']}`(`post_uid`) ON DELETE CASCADE,
				CONSTRAINT `fk_quote_link_target_post_uid` FOREIGN KEY (`target_post_uid`)
				REFERENCES `{$sanitizedTableNames['POST_TABLE']}`(`post_uid`) ON DELETE CASCADE
			) ENGINE=InnoDB;",

			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['ACTIONLOG_TABLE']} (
				`id` INT NOT NULL AUTO_INCREMENT,
				`time_added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`date_added` DATE DEFAULT CURRENT_DATE,
				`name` TEXT NOT NULL,
				`role` INT NOT NULL,
				`log_action` TEXT NOT NULL,
				`ip_address` TEXT NOT NULL,
				`board_uid` INT,
				`board_title` TEXT NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (role),
				INDEX (time_added),
				INDEX (name),
				INDEX (board_uid)
			) ENGINE=InnoDB;",
	
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['ACCOUNT_TABLE']} (
				id INT AUTO_INCREMENT PRIMARY KEY,
				username TEXT NOT NULL UNIQUE,
				role INT DEFAULT 0,
				password_hash TEXT NOT NULL,
				number_of_actions INT DEFAULT 0,
				last_login TIMESTAMP DEFAULT NULL,
				date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				index(last_login),
				index(date_added)
			) ENGINE=InnoDB;",
	
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['THREAD_REDIRECT_TABLE']} (
				`redirect_id` INT NOT NULL AUTO_INCREMENT,
				`original_board_uid` INT NOT NULL,
				`new_board_uid` INT NOT NULL,
				`post_op_number` INT NOT NULL,
				`thread_uid` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`redirect_id`),
				CONSTRAINT new_board_uid FOREIGN KEY (`new_board_uid`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE,
				CONSTRAINT redirect_thread_uid FOREIGN KEY (`thread_uid`) REFERENCES `{$sanitizedTableNames['THREAD_TABLE']}`(`thread_uid`) ON DELETE CASCADE,
				INDEX (`original_board_uid`),
				INDEX (`thread_uid`)
			) ENGINE=InnoDB;",

			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['BOARD_PATH_CACHE_TABLE']} (
				`id` INT NOT NULL AUTO_INCREMENT,
				`boardUID` INT NOT NULL,
				`board_path` TEXT NOT NULL,
				PRIMARY KEY (`id`),
				CONSTRAINT path_cache_board_uid FOREIGN KEY (`boardUID`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE
			) ENGINE=InnoDB;"
		];
	
		// Use prepared statements for execution
		foreach ($queries as $query) {
			$stmt = $this->db->prepare($query);
			$stmt->execute();
		}
	}
	
}

class accountTable {
	private $db, $accountTableName;

	public function __construct($pdoConnection, $accountTableName) {	
		$this->db = $pdoConnection;
		$this->accountTableName = $accountTableName;
	}

	public function addAdminAccount($username, $unhashedPassword, $role) {
		$hashedPassword = password_hash($unhashedPassword, PASSWORD_DEFAULT);
		$query = "INSERT INTO {$this->accountTableName} (username, password_hash, role) VALUES(:username, :password_hash, :role)";
		$stmt = $this->db->prepare($query);
		$stmt->bindParam(':username', $username);
		$stmt->bindParam(':password_hash', $hashedPassword);
		$stmt->bindParam(':role', $role);
		return $stmt->execute();
	}
}


class boardTable {
	private $db, $boardTableName, $databaseName;

	// Constructor to initialize the PDO connection, table name, and database name
	public function __construct($pdoConnection, $boardTableName, $databaseName) {
		$this->db = $pdoConnection;
		$this->boardTableName = $boardTableName;
		$this->databaseName = $databaseName;
	}

	// Method to create a global board if it doesn't exist
	public function createGlobalBoard() {
		// Check if the global board already exists
		$query = "SELECT COUNT(*) FROM {$this->boardTableName} WHERE board_uid = :global_board_uid";
		$stmt = $this->db->prepare($query);
		$stmt->execute([
			':global_board_uid' => GLOBAL_BOARD_UID
		]);
		$count = $stmt->fetchColumn();

		// If global board doesn't exist, insert it
		if ($count == 0) {
			// Insert the global board with a reserved UID
			$query = "INSERT INTO {$this->boardTableName} 
						(board_uid, board_identifier, board_title, board_sub_title, config_name, storage_directory_name, listed, date_added) 
					  VALUES 
						(:board_uid, :board_identifier, :board_title, :board_sub_title, :config_name, :storage_directory_name, :listed, :date_added)";
			
			$stmt = $this->db->prepare($query);
			$stmt->bindValue(':board_uid', GLOBAL_BOARD_UID);
			$stmt->bindValue(':board_identifier', 'GLOBAL');
			$stmt->bindValue(':board_title', 'GLOBAL');
			$stmt->bindValue(':board_sub_title', 'Global board scope');
			$stmt->bindValue(':config_name', '');
			$stmt->bindValue(':storage_directory_name', '');
			$stmt->bindValue(':listed', 0, PDO::PARAM_INT);
			$stmt->bindValue(':date_added', date('Y-m-d'));
			
			return $stmt->execute(); // Return true if successful
		}

		// If the global board exists, return false or a message (optional)
		return false; // Board already exists
	}

	// Method to add the first board to the system (example for initial setup)
	public function addFirstBoard($board_identifier, $board_title, $board_sub_title, $config_name, $storage_directory_name) {
		$query = "INSERT INTO {$this->boardTableName} 
					(board_identifier, board_title, board_sub_title, config_name, storage_directory_name) 
				  VALUES 
					(:board_identifier, :board_title, :board_sub_title, :config_name, :storage_directory_name)";
		
		$stmt = $this->db->prepare($query);
		$stmt->bindParam(':board_identifier', $board_identifier);
		$stmt->bindParam(':board_title', $board_title);
		$stmt->bindParam(':board_sub_title', $board_sub_title);
		$stmt->bindParam(':config_name', $config_name);
		$stmt->bindParam(':storage_directory_name', $storage_directory_name);
		
		return $stmt->execute(); // Return true if successful
	}

	// Method to fetch the last board UID (useful for inserting new boards)
	public function getLastBoardUID() {
		$query = "SELECT MAX(board_uid) AS max_uid FROM {$this->boardTableName}";
		$stmt = $this->db->query($query);
		$board_uid = $stmt->fetchColumn();
		return $board_uid ?? 0;
	}

	// Method to get the next AUTO_INCREMENT value for a table
	public function getNextAutoIncrement($tableName) {
		try {
			// Query to get the AUTO_INCREMENT value from information_schema
			$query = "SELECT AUTO_INCREMENT 
					  FROM information_schema.TABLES 
					  WHERE TABLE_SCHEMA = :databaseName 
					  AND TABLE_NAME = :tableName";
	
			$stmt = $this->db->prepare($query);
			$stmt->execute([
				':databaseName' => $this->databaseName,
				':tableName' => $tableName,
			]);
	
			// Fetch the result
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
	
			if ($result && isset($result['AUTO_INCREMENT'])) {
				return (int)$result['AUTO_INCREMENT'];
			}
	
			// Return null if AUTO_INCREMENT value is not found
			return null;
		} catch (PDOException $e) {
			// Handle exceptions by logging or re-throwing
			error_log("Error fetching AUTO_INCREMENT value: " . $e->getMessage());
			return null;
		}
	}
}

// Main execution
$dbSettings = require ROOTPATH . '/databaseSettings.php';
$html = new html($dbSettings);

if (file_exists('.installed')) {
	$html->drawHeader();
	$html->drawStyle();
	$html->drawInstallNotice();
	echo "Kokonotsuba has been installed!";
	$html->drawFooter();
	exit;
}

$action = $_REQUEST['action'] ?? '';
switch ($action) {
	case 'install':
		try {
			$dsn = "{$dbSettings['DATABASE_DRIVER']}:host={$dbSettings['DATABASE_HOST']};port={$dbSettings['DATABASE_PORT']};dbname={$dbSettings['DATABASE_NAME']};charset={$dbSettings['DATABASE_CHARSET']}";
			$pdoConnection = new PDO($dsn, $dbSettings['DATABASE_USERNAME'], $dbSettings['DATABASE_PASSWORD'], [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);

			$globalConfig = getGlobalConfig();

			$tableCreator = new tableCreator($pdoConnection);
			$tables = [
				'POST_TABLE' => $_POST['POST_TABLE'],
				'QUOTE_LINK_TABLE' => $_POST['QUOTE_LINK_TABLE'],
				'REPORT_TABLE' => $_POST['REPORT_TABLE'],
				'BAN_TABLE' => $_POST['BAN_TABLE'],
				'BOARD_TABLE' => $_POST['BOARD_TABLE'],
				'POST_NUMBER_TABLE' => $_POST['POST_NUMBER_TABLE'],
				'ACCOUNT_TABLE' => $_POST['ACCOUNT_TABLE'],
				'ACTIONLOG_TABLE' => $_POST['ACTIONLOG_TABLE'],
				'THREAD_TABLE' => $_POST['THREAD_TABLE'],
				'THREAD_REDIRECT_TABLE' => $_POST['THREAD_REDIRECT_TABLE'],
				'BOARD_PATH_CACHE_TABLE' => $_POST['BOARD_PATH_CACHE_TABLE'],
			];

			$tableCreator->createTables($tables);
			$sanitizedTableNames = array_map('sanitizeTableName', $tables);
			$boardTable = new boardTable($pdoConnection, $sanitizedTableNames['BOARD_TABLE'], $dbSettings['DATABASE_NAME']);
			$accountTable = new accountTable($pdoConnection, $sanitizedTableNames['ACCOUNT_TABLE']);

			$boardTable->createGlobalBoard(); // create global dummy board

			createBoardAndFiles($boardTable);

			$username = $_POST['admin-username'] ?? '';
			$password = $_POST['admin-password'] ?? '';
			$accountTable->addAdminAccount($username, $password, 4);

			
			touch('.installed');
			
			if(file_exists(dirname(__FILE__) . '/' .$globalConfig['STATIC_INDEX_FILE'])) {

				unlink('./'.$globalConfig['STATIC_INDEX_FILE']);
				createFileAndWriteText(dirname(__FILE__) . '/', $globalConfig['STATIC_INDEX_FILE'], '
					<!DOCTYPE html>
					<html lang="en">
						<head>
							<meta charset="UTF-8">
							<meta http-equiv="refresh" content="url='.$globalConfig['LIVE_INDEX_FILE'].'">
							<title>Redirecting...</title>
						</head>
						<body>
							<p>If you are not redirected automatically, follow this <a href="'.$globalConfig['LIVE_INDEX_FILE'].'">link</a>.</p>
						</body>
					</html>
				');
			}
			
			redirect($globalConfig['LIVE_INDEX_FILE']);
		} catch (Exception $e) {
			throw $e;
		}
		break;

	//default main
	default:
		$html->drawHeader();
		$html->drawStyle();
		$html->drawInstallNotice();
		$html->drawRequiredExtentions();
		$html->drawImportantConfigValuesPreview();
		$html->drawInstallForm();
		$html->drawFooter();
	break;
}