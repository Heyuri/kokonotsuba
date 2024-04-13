<!DOCTYPE html>
<html>
	<head>
		<title>KotatsuBBS Installer</title>
		<style>
			body {
				background-color: #d0f0c0;
				font-family: Arial, sans-serif;
			}
			.postblock {
				padding: 20px;
				background-color: #ffcccc;
				border: 2px solid #ff0000;
				margin: 10px 0;
			}
			.prompt {
				padding: 20px;
				background-color: #e6ffe6;
				border: 2px solid #4CAF50;
				margin: 10px 0;
			}
			form > div {
				display: flex;
				align-items: center;
				margin-bottom: 10px;
			}

			form > div > label {
				margin-right: 10px;
				width: 20%;
				min-width: 120px;
			}

			form > div > input {
				width: 80%;
				padding: 8px;
				border: 1px solid #ccc;
			}

			button[type="submit"] {
				padding: 10px 15px;
				cursor: pointer;
			}
		</style>
	</head>
	<body>

<?php 
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$conf = require __DIR__ .'/conf.php'; 
	
	function createDB($conn){
		// SQL to create tables
		$sqlCommands = [
			"CREATE TABLE IF NOT EXISTS boards (
				boardID INT AUTO_INCREMENT PRIMARY KEY,
				configPath VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				lastPostID INT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		
			"CREATE TABLE IF NOT EXISTS posts (
				UID INT AUTO_INCREMENT PRIMARY KEY,
				postID INT NOT NULL,
				boardID INT NOT NULL,
				threadID INT NULL, -- Allow NULL to create a post without an existing thread
				password VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
				subject VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
				comment TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				ip VARCHAR(45) NOT NULL,
				postTime INT NOT NULL,
				special TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
				FOREIGN KEY (boardID) REFERENCES boards(boardID) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		
			"CREATE TABLE IF NOT EXISTS threads (
				threadID INT AUTO_INCREMENT PRIMARY KEY,
				boardID INT NOT NULL,
				lastTimePosted INT NOT NULL,
				opPostID INT NOT NULL,
				FOREIGN KEY (boardID) REFERENCES boards(boardID) ON DELETE CASCADE ON UPDATE CASCADE
				FOREIGN KEY (opPostID) REFERENCES posts(UID) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		
			"ALTER TABLE posts ADD CONSTRAINT fk_threadID FOREIGN KEY (threadID) REFERENCES threads(threadID) ON DELETE CASCADE ON UPDATE CASCADE;",
		
			"CREATE TABLE IF NOT EXISTS files (
				fileID INT AUTO_INCREMENT PRIMARY KEY,
				postID INT NOT NULL,
				threadID INT NOT NULL,
				boardID INT NOT NULL,
				filePath VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				FOREIGN KEY (postID) REFERENCES posts(UID) ON DELETE CASCADE ON UPDATE CASCADE,  -- Note: Ensure this references the correct column
				FOREIGN KEY (threadID) REFERENCES threads(threadID) ON DELETE CASCADE ON UPDATE CASCADE,
				FOREIGN KEY (boardID) REFERENCES boards(boardID) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		];
		// Execute each SQL command
		foreach ($sqlCommands as $sql) {
			if ($conn->query($sql) === TRUE) {
				echo "Table created successfully<br>";
			} else {
				echo "Error creating table: " . $conn->error . "\n";
			}
		}
		
		echo "database Successfully set up!\n";
	}
	
	function updateConf(){	
		global $conf;

		//set configs from postData
		$conf['mysqlDB']['host']			= $_POST['host'];
		$conf['mysqlDB']['port']			= $_POST['port'];
		$conf['mysqlDB']['username'] 		= $_POST['username'];
		$conf['mysqlDB']['password'] 		= $_POST['password'];
		$conf['mysqlDB']['databaseName']	= $_POST['databaseName'];

		//formate and write new config to file
		//$newConfig = '<?php' . PHP_EOL . '// conf.php' . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL;
		//file_put_contents('conf.php', $newConfig);

		$newConf = '<?php return ' . var_export($conf, true) . ';';
		if (file_put_contents('conf.php', $newConf) === false) {
			echo "Failed to write configuration.";
		}
	}

	
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		echo "<div class=\"postblock\">";
		updateConf();

		$connection;
		try {
			$connection = new mysqli($_POST['host'], $_POST['username'], $_POST['password'], $_POST['databaseName']);
		} catch (mysqli_sql_exception $e) {
			echo "invalid credentals. is you database running?";
		}

		if ($connection) {
			createDB($connection);
			$connection->close();
		}
		echo "</div>";
	} 
?>

		<h1>KotatsuBBS Installer</h1>
		<div class="prompt">
			Once you have a MySQL server set up with a basic username, password, and privileges. enter in the credentals below.<br>
			this would also update your <i>conf.php</i> to use the newly added creds.<br>
			<hr>
			<form method="post">
				<div>
					<label for="username">Username*:</label>
					<input type="text" id="username" name="username" value="<?php echo htmlspecialchars($conf['mysqlDB']['username']); ?>">
				</div>
				<div>
					<label for="dbpassword">Password*:</label>
					<input type="text" id="password" name="password" value="<?php echo htmlspecialchars($conf['mysqlDB']['password']); ?>">
				</div>
				<div>
					<label for="host">Domain/ip*:</label>
					<input type="text" id="host" name="host" value="<?php echo htmlspecialchars($conf['mysqlDB']['host']); ?>">
				</div>
				<div>
					<label for="host">port:</label>
					<input type="text" id="port" name="port" value="<?php echo htmlspecialchars($conf['mysqlDB']['port']); ?>">
				</div>
				<div>
					<label for="databaseName">Database name*:</label>
					<input type="text" id="databaseName" name="databaseName" value="<?php echo htmlspecialchars($conf['mysqlDB']['databaseName']); ?>">
				</div>
				<div>
					<button type="submit" name="install">install</button>
				</div>
			</form>
		</div>
	</body>
</html>
