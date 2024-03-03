<?php
	require './config.php';

	// Parse the mysql connection string
	$parsedUrl = parse_url(CONNECTION_STRING);

	// Extract the components
	$username = $parsedUrl['user'] ?? '';
	$password = $parsedUrl['pass'] ?? '';
	$host = $parsedUrl['host'] ?? '';
	
	$path = trim($parsedUrl['path'], '/'); // Trim leading and trailing slashes
	$pathComponents = explode('/', $path); // Split the path to get database and table name
	$database = $pathComponents[0] ?? '';
	$table = $pathComponents[1] ?? '';

	function createDB(&$conn){
		global $table;
		try {
			$conn->query("DROP TABLE IF EXISTS " . $table );
		} catch (mysqli_sql_exception $e) {
			echo "Error Failed to drop table : " . $e . "\n";
			return False;
		}
		try {
			$conn->query("CREATE TABLE " . $table . " (
				`no` int(1) NOT NULL AUTO_INCREMENT,
				`resto` int(1) NOT NULL,
				`root` timestamp NOT NULL,
				`time` int(1) NOT NULL,
				`md5chksum` text,
				`category` text NOT NULL,
				`tim` bigint(1) NOT NULL,
				`fname` text NOT NULL,
				`ext` text NOT NULL,
				`imgw` smallint(1) NOT NULL,
				`imgh` smallint(1) NOT NULL,
				`imgsize` text NOT NULL,
				`tw` smallint(1) NOT NULL,
				`th` smallint(1) NOT NULL,
				`pwd` text NOT NULL,
				`now` text NOT NULL,
				`name` text NOT NULL,
				`email` text NOT NULL,
				`sub` text NOT NULL,
				`com` text NOT NULL,
				`host` text NOT NULL,
				`status` text NOT NULL,
				PRIMARY KEY (`no`),
				index (resto),
				index (root),
				index (time)
			) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;");
		} catch (mysqli_sql_exception $e) {
			echo "Error Failed to create table : " . $e . "\n";
			return False;
		}
		
		echo "database Successfully set up!\n";
		return True;
	}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Kokonotsuba Installer</title>
		<link rel="stylesheet" type="text/css" href="<?php echo STATIC_URL ?>/css/heyuriclassic.css">
	</head>
	<body>
		<h1>Kokonotsuba Installer</h1><hr>
		<h2>DO NOT RUN THIS SCRIPT WHITH A TABLE ALREADY IN USE!</h2>
		Doing so would drop that table. If you want to use that table, delete this <i>install.php</i>. script and add your own MySQL creds to <i>config.php</i><br><br>
		<div class="reply">
			Once you have a MySQL server set up with a basic username, password, and database, put the MySQL credentials in <i>config.php</i> and run this form.<br>
			You have an option to set your own database names here.<hr>
			<form method="post">
				<label>Username: </label><?php echo htmlspecialchars($username);?><br>
				<label>Password: </label><?php echo htmlspecialchars($password);?><br>
				<label>Domain/ip: </label><?php echo htmlspecialchars($host);?><br>
				<label>Database Name: </label><?php echo htmlspecialchars($database);?><br>
				<label>Table name: </label><?php echo htmlspecialchars($table);?><br>
				<button type="submit">install</button>
			</form>
		</div>

<?php
	// on post request recived. connect to the DB and create the table.
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		echo "<div class=\"postblock\">";

		$connection = NULL;
		try {
			$connection = new mysqli($host, $username, $password, $database);
		} catch (mysqli_sql_exception $e) {
			echo "invalid credentals. is you database running? " . $e ."\n";
		}
		if ($connection) {
			createDB($connection);
			$connection->close();
		}

		echo "</div>";
	} 
?>

	</body>
</html>
