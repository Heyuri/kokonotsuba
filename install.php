<?php

require './lib/lib_common.php';

$extensions = [
	'mbstring',
	'pdo',
	'gd',
	'bcmath',
];

function checkExtensions(array $extensions) {
	$results = [];

	foreach ($extensions as $extension) {
		if (extension_loaded($extension)) {
			$results[$extension] = true;
		} else {
			$results[$extension] = false;
		}
	}

	return $results;
}

function isFFmpegInstalled() {
	$output = null;
	$returnVar = null;
	
	exec("ffmpeg -version", $output, $returnVar);

	return $returnVar === 0;
}

function getGlobalConfig() {
	require __DIR__.DIRECTORY_SEPARATOR.'global'.DIRECTORY_SEPARATOR.'globalconfig.php';
	return $config;
}

function generateNewBoardConfigFile() {
	$templateConfigPath = __DIR__.DIRECTORY_SEPARATOR.'global'.DIRECTORY_SEPARATOR.'board-configs'.DIRECTORY_SEPARATOR.'board-template.php';//config template
	$newConfigFileName = 'board-'.generateUid().'.php';
	$boardConfigsDirectory = __DIR__.DIRECTORY_SEPARATOR.'global'.DIRECTORY_SEPARATOR.'board-configs'.DIRECTORY_SEPARATOR;

	if(!copyFileWithNewName($templateConfigPath, $newConfigFileName, $boardConfigsDirectory)) throw new Exception("Failed to copy new config file");
	return $newConfigFileName;
}

// Function to sanitize table names using regular expression validation
function sanitizeTableName($tableName) {
	// Validat e table name: Only allow alphanumeric characters and underscores
	if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) throw new InvalidArgumentException("Invalid table name: $tableName. Only alphanumeric characters and underscores are allowed.");
	return $tableName;
}

function createBoardAndFiles($boardTable) {
	//create board
	$board_identifier = $_POST['board-identifier'];
	$board_title = $_POST['board-title'];
	$board_sub_title = $_POST['board-sub-title'];
	$board_path = $_POST['board-path'];

	$globalConfig = getGlobalConfig();

	$fullBoardPath = $board_path.DIRECTORY_SEPARATOR.$board_identifier.DIRECTORY_SEPARATOR;

	$dataDir = $fullBoardPath.DIRECTORY_SEPARATOR.'dat'.DIRECTORY_SEPARATOR;
	//create physical board files
	$fileUploadedDirectory = $globalConfig['USE_CDN'] ? $globalConfig['CDN_DIR'].$board_identifier.DIRECTORY_SEPARATOR : $fullBoardPath.'src'.DIRECTORY_SEPARATOR;

	//create cdn dirs
	$boardImagesDir = $fileUploadedDirectory;
	$boardThumbDir = $fileUploadedDirectory;
	createDirectory($boardImagesDir);
	createDirectory($boardThumbDir);
	//create dat
	createDirectory($dataDir);
	//write files
	$backendDirectory = __DIR__.DIRECTORY_SEPARATOR;
	$requireString = "\"$backendDirectory{$globalConfig['PHP_SELF']}\"";
	createFileAndWriteText($fullBoardPath, $globalConfig['PHP_SELF'], "<?php require_once {$requireString}; ?>");

	//generate new config
	$boardConfigName = generateNewBoardConfigFile();

	$boardTable->addFirstBoard($board_identifier, $board_title, $board_sub_title, $boardConfigName);

	$boardUIDforBootstrapFile = $boardTable->getLastBoardUID();
	createFileAndWriteText($fullBoardPath, 'boardUID.ini', "board_uid = $boardUIDforBootstrapFile");
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
		echo '
			<div class="notice-text">
				<h2>Notice!</h2>
				<p>Kokonotsuba is a BBS software</p>
				<p>Read the instructions, other documentation or open a Pull Request on the <a href="https://github.com/Heyuri/kokonotsuba">repo</a> if there are any problems</p>
				<p>For more info: <a href="https://kokonotsuba.github.io/">see here</a></p>
			</div><hr size=1>';
	}
	
	public function drawRequiredExtentions() {
		global $extensions; 
		$results = checkExtensions($extensions);
		
		echo '<h3>Required extentions</h3>
			<p>These are extentions required for Kokonotsuba to work fully.</p>
		<ul>';
		foreach ($results as $extension => $isEnabled) {
			echo "<li> $extension: " . ($isEnabled ? 'enabled' : 'not enabled').'</li>';
		}
		echo '<li>ffmpeg: '.(isFFmpegInstalled() ? 'enabled' : 'not enabled').'</li></ul>';

	}

	public function drawImportantConfigValuesPreview() {
		$globalConfig = getGlobalConfig();

		$websiteURL = $globalConfig['WEBSITE_URL'];
		$staticURL = $globalConfig['STATIC_URL']; // Where static files are located on the web, can be a full URL (eg. 'https://static.example.com/'). Include trailing '/'
		$staticPath = $globalConfig['STATIC_PATH']; // Where static files are stored in the server, can be an absolute path (eg. '/home/example/web/static/'). Include trailing '/'

		echo '
		<h3>Config</h3>
		<p>Here are a few config values that are required for a successful installation, make sure they\'re set correctly in global/globalconfig.php</p>
		<table id="config-preview-table-table">
				<tr> 
					<td class="postblock"> <label for "static-dir-preview">Static Path</label></td>
					<td>'.htmlspecialchars($staticPath).'</td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "static-url-preview">Static Path</label></td>
					<td>'.htmlspecialchars($staticURL).'</td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "site-url-preview">Site URL</label></td>
					<td>'.htmlspecialchars($websiteURL).'</td>
				</tr>
			</table>';
	}

	public function drawInstallForm() {
		echo '
		<form id="installation-form" action="'.$_SERVER['PHP_SELF'].'" method="POST">
			<input type="hidden" name="action" value="install">
			<h3>Database Options</h3>
			<p>If you make any changes to these - make sure to update databaseSettings.php afterwards to match what you set</p>
			<table id="installation-form-database-settings-table">
				<tr> 
					<td class="postblock"> <label for "database-post-table-input">Post table</label></td>
					<td> <input id="database-post-table-input" name="POST_TABLE" value="'.htmlspecialchars($this->dbSettings['POST_TABLE']).'" required> </td>
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
			<h3>Admin account</h3>
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
			<h3>First board</h3>
			<p>This will be the first board on your kokonotsuba instance</p>
			<table id="installation-form-admin-account-table">
				<tr> 
					<td class="postblock"> <label for "first-board-identifier-input" >Board identifier</label></td>
					<td> <input id="first-board-identifier-input" name="board-identifier" placeholder="b" required> </td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "first-board-title-input" >Board Title</label></td>
					<td> <input id="first-board-title-input" name="board-title" placeholder="board@example.net" required> </td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "first-board-sub-title-input" >Board Sub-Title</label></td>
					<td> <input id="first-board-sub-title-input" name="board-sub-title" placeholder="an example board" required> </td>
				</tr>
				<tr> 
					<td class="postblock"> <label for "first-board-path-input" >Board Path</label></td>
					<td> <input id="first-board-path-input" name="board-path" placeholder="an example board" value="'.dirname(__FILE__).DIRECTORY_SEPARATOR.'" required> </td>
				</tr>
			</table>
			<input type="submit" Value="Install">
		</form>';

	}

	public function drawFooter() {
		echo '<hr size="1">';
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
				`board_identifier` TEXT NOT NULL,
				`board_title` TEXT NOT NULL,
				`board_sub_title` TEXT NOT NULL,
				`config_name` TEXT NOT NULL,
				`listed` BOOL DEFAULT TRUE,
				`date_added` DATE DEFAULT CURRENT_DATE,
				PRIMARY KEY(`board_uid`),
				INDEX(date_added)
			) ENGINE=InnoDB;",
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['THREAD_TABLE']} (
				`post_op_number` INT NOT NULL,
				`post_op_post_uid` VARCHAR(255) NOT NULL,
				`thread_uid` VARCHAR(255) NOT NULL,
				`bump_number` INT NOT NULL,
				`boardUID` INT NOT NULL,
				`last_reply_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`last_bump_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`thread_created_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`thread_uid`),
				CONSTRAINT fk_thread_boardUID FOREIGN KEY (`boardUID`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE,
				INDEX (`last_reply_time`),
				INDEX (`last_bump_time`),
				INDEX (`thread_created_time`),
				INDEX (`bump_number`)
			) ENGINE=InnoDB;",
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['POST_TABLE']} (
				`bump_increment` INT NOT NULL AUTO_INCREMENT,
				`no` INT NOT NULL,
				`boardUID` INT NOT NULL,
				`post_uid` VARCHAR(255) NOT NULL,
				`thread_uid` VARCHAR(255) NOT NULL,
				`root` TIMESTAMP NOT NULL,
				`time` INT NOT NULL,
				`md5chksum` TEXT,
				`category` TEXT NOT NULL,
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
				`email` TEXT NOT NULL,
				`sub` TEXT NOT NULL,
				`com` TEXT NOT NULL,
				`host` TEXT NOT NULL,
				`status` TEXT,
				PRIMARY KEY (`bump_increment`),
				CONSTRAINT fk_boardUID FOREIGN KEY (`boardUID`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE,
				CONSTRAINT fk_thread_uid FOREIGN KEY (`thread_uid`) REFERENCES `{$sanitizedTableNames['THREAD_TABLE']}`(`thread_uid`) ON DELETE CASCADE,
				INDEX (`thread_uid`),
				INDEX (`post_uid`),
				INDEX (`no`)
			) ENGINE=InnoDB;",
	
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['POST_NUMBER_TABLE']} (
				`post_number` INT NOT NULL AUTO_INCREMENT,
				`board_uid` INT NOT NULL,
				PRIMARY KEY(`post_number`),
				CONSTRAINT fk_post_count_board_uid FOREIGN KEY (`board_uid`) REFERENCES `{$sanitizedTableNames['BOARD_TABLE']}`(`board_uid`) ON DELETE CASCADE,
				INDEX(board_uid)
			) ENGINE=InnoDB;",
	
			"CREATE TABLE IF NOT EXISTS {$sanitizedTableNames['ACTIONLOG_TABLE']} (
				`id` int(1) NOT NULL AUTO_INCREMENT,
				`time_added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`date_added` DATE DEFAULT CURRENT_DATE,
				`name` TEXT NOT NULL,
				`role` INT NOT NULL,
				`log_action` TEXT NOT NULL,
				`ip_address` VARCHAR(500),
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
				username VARCHAR(50) NOT NULL UNIQUE,
				role INT DEFAULT 0,
				password_hash VARCHAR(255) NOT NULL,
				number_of_actions INT DEFAULT 0,
				last_login TIMESTAMP DEFAULT NULL,
				date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
	private $db, $boardTableName;

	public function __construct($pdoConnection, $boardTableName) {		
		$this->db = $pdoConnection;
		$this->boardTableName = $boardTableName;
	}

	public function addFirstBoard($board_identifier, $board_title, $board_sub_title, $config_name) {
		$query = "INSERT INTO {$this->boardTableName} (board_identifier, board_title, board_sub_title, config_name) VALUES(:board_identifier, :board_title, :board_sub_title, :config_name)";
		$stmt = $this->db->prepare($query);
		$stmt->bindParam(':board_identifier', $board_identifier);
		$stmt->bindParam(':board_title', $board_title);
		$stmt->bindParam(':board_sub_title', $board_sub_title);
		$stmt->bindParam(':config_name', $config_name);
		return $stmt->execute();
	}

	public function getLastBoardUID() {
		$query = "SELECT MAX(board_uid) AS max_uid FROM {$this->boardTableName}";
		$stmt = $this->db->query($query);
		$board_uid = $stmt->fetchColumn();
		return $board_uid;
	}
}

$dbSettings = require './databaseSettings.php';
$html = new html($dbSettings);


if(file_exists('.installed')) {
	$html->drawHeader();
	$html->drawStyle();
	$html->drawInstallNotice();
	echo "Kokonotsuba has been installed!";
	$html->drawFooter();
	exit;
}

$action = $_REQUEST['action'] ?? '';
switch($action) {
	case 'install':
		try {

			$dsn = "{$dbSettings['DATABASE_DRIVER']}:host={$dbSettings['DATABASE_HOST']};port={$dbSettings['DATABASE_PORT']};dbname={$dbSettings['DATABASE_NAME']};charset={$dbSettings['DATABASE_CHARSET']}";
			$pdoConnection = new PDO($dsn, $dbSettings['DATABASE_USERNAME'], $dbSettings['DATABASE_PASSWORD'], [
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES   => false,
			]);

			$tableCreator = new tableCreator($pdoConnection);
			$tables = [
				'POST_TABLE' => $_POST['POST_TABLE'],
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

			$tableCreator = new tableCreator($pdoConnection);
			$tableCreator->createTables($tables);

			$sanitizedTableNames = array_map('sanitizeTableName', $tables);
			$boardTable = new boardTable($pdoConnection, $sanitizedTableNames['BOARD_TABLE']);
			$accountTable = new accountTable($pdoConnection, $sanitizedTableNames['ACCOUNT_TABLE']);

			createBoardAndFiles($boardTable);
			
			$username = $_POST['admin-username'];
			$password = $_POST['admin-password'];

			$accountTable->addAdminAccount($username, $password, 4);

			touch('.installed');
			redirect($_SERVER['PHP_SELF']);
		} catch(Exception $e) {
			throw $e;
		}
	break;
}


//default main
$html->drawHeader();
$html->drawStyle();
$html->drawInstallNotice();
$html->drawRequiredExtentions();
$html->drawImportantConfigValuesPreview();
$html->drawInstallForm();
$html->drawFooter();
