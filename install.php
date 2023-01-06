<?php require './config.php';

if(preg_match('/^mysqli:\/\/(.*)\:(.*)\@(.*)(?:\:([0-9]+))?\/(.*)\/(.*)\/$/i', CONNECTION_STRING, $linkinfos)){
            $username = $linkinfos[1];
            $password = $linkinfos[2];
            $server = $linkinfos[3];
            $port = $linkinfos[4] ? intval($linkinfos[4]) : 3306;
            $database = $linkinfos[5];
            $table = $linkinfos[6];
        }
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Kokonotsuba Installer</title>
		<style>
			body {
				width: max-content;
				max-width: 90%;
				margin-left: auto;
				margin-right: auto;
				font-family: sans;
			}
			h1,h2 {
				text-align: center;
			}
			.middle {
				width: max-content;
				display: block;
				margin-left: auto;
				margin-right: auto;
			}
			table td:nth-child(1) {
				text-align: right;
				font-weight: bold;
				padding-right: 16px;
			}
		</style>
	</head>
	<body>
		<h1>Kokonotsuba Installer</h1>

		<h2>MySQL Details</h2>
		<div class="middle">
			Are these details correct? (if not change them in <i>config.php</i>)<br>
			<table class="middle">
				<tr>
					<td>Username</td>
					<td><?= $username ?></td>
				</tr>
				<tr>
					<td>Password</td>
					<td><?= $password ?></td>
				</tr>
				<tr>
					<td>Server</td>
					<td><?= $server ?></td>
				</tr>
				<tr>
					<td>Database</td>
					<td><?= $database ?></td>
				</tr>
				<tr>
					<td>Table</td>
					<td><?= $table ?></td>
				</tr>
			</table>
		</div>

		<h2>Installer</h2>
		<div class="middle">
			<form method="POST" action="">
				<input type="submit" name="submit" value="Install">
			</form>
		</div>

		<h2>Converter</h2>
		<div class="middle">
			You can ignore this step if you don't want to convert any databases.<br><br>
			<form method="POST" action="">
				<table class="middle">
					<tr>
						<td>Script Folder</td>
						<td><input type="text" name="scriptfolder" value="<?= ROOTPATH ?>"></td>
					</tr>
					<tr>
						<td>Log Folder</td>
						<td><input type="text" name="logfolder" value="<?= STORAGE_PATH ?>"></td>
					</tr>
					<tr>
						<td></td>
						<td><input type="submit" name="submit" value="Convert"></td>
					</tr>
				</table>
			</form>
		</div>
		
		<h2>VARCHAR to TEXT</h2>
		<div class="middle">
			<form method="POST" action="">
				<input type="submit" name="submit" value="Convert VARCHAR to TEXT">
			</form>
		</div>
<?php
if (isset($_POST["submit"])) {
?>
		<h2>Output log</h2>
		<pre>
<?php
	$imgfile = $_POST["logfolder"]."img.log";
	$treefile = $_POST["logfolder"]."tree.log";
	$conn = new mysqli($server, $username, $password, $database);
	$stmt = $conn->stmt_init();
	
	
	if ($_POST["submit"] == "Install") {
		echo "Creating database... ";
		if (!$conn->query("DROP TABLE IF EXISTS " . $table . "")) {echo "Error, could not drop table.\n"; die();}
		if (!$conn->query("CREATE TABLE " . $table . " (
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
	) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin")) {echo "Error, could not create table.\n"; die();}
		echo "Done!\n";
	}

	if ($_POST["submit"] == "Convert") {
		echo "Converting post...\n";
		$spam = str_replace("\r","",file_get_contents($imgfile));
		$spam = explode("\n",$spam);
		foreach ($spam as $row) {
			$data = explode(",",$row);
			if (!isset($data[0]) || $data[0] == NULL) {continue;}

			$stmt->prepare("INSERT INTO " . $table . " VALUES (?,?,FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
			$com = str_replace("&#44;",",",$data[17]);
			$ut = substr($data[4],0,10);
			$stmt->bind_param("iiiississiisiissssssss",
				$data[0], $data[1], $ut, $ut, $data[2], $data[3],
				$data[4], $data[5], $data[6], $data[7],
				$data[8], $data[9], $data[10], $data[11],
				$data[12], $data[13], $data[14], $data[15],
				$data[16], $com, $data[18], $data[19],
			);
			if (!($stmt->execute())) {
				echo "<br>FAILURE on post #".$data[0]."\n";
				echo $stmt->error."\n";
				print_r($data);
				echo "This is okay, just one post missing.\n";
			}
		}
		echo "Post data converted!\n";

		echo "Converting tree data...\n";
		$tree = str_replace("\r","",file_get_contents($treefile));
		$tree = explode("\n",$tree);
		foreach ($tree as $row) {
			$data = explode(",",$row);
			if (!isset($data[0]) || $data[0] == NULL) {continue;}

			$stmt->prepare("SELECT time FROM " . $table . " WHERE resto=? AND email NOT LIKE '%sage%' ORDER BY no DESC");
			$stmt->bind_param("i", $data[0]);
			$stmt->execute();
			if ($stmt->num_rows == 0) {continue;}
			$bump = $stmt->get_result()->fetch_array()["time"];
			if (!isset($bump) || $bump == NULL) {continue;}
			$stmt->prepare("UPDATE " . $table . " SET root=FROM_UNIXTIME(?) WHERE no=?");
			$stmt->bind_param("ii",$bump,$data[0]);
			$stmt->execute();
		}
		echo "Tree data converted!\n";

		echo "Done! Please rebuild the board now.\n";
	}
	
	if ($_POST["submit"] == "Convert VARCHAR to TEXT") {
		
		$conn = new mysqli($server, $username, $password, $database);
		$stmt = $conn->stmt_init();

		echo "Converting VARCHAR to TEXT on " . $table . "... ";
		if (!$conn->query("ALTER TABLE " . $table . "
				MODIFY COLUMN md5chksum text,
				MODIFY COLUMN category text,
				MODIFY COLUMN fname text,
				MODIFY COLUMN imgsize text,
				MODIFY COLUMN pwd text,
				MODIFY COLUMN now text,
				MODIFY COLUMN name text,
				MODIFY COLUMN email text,
				MODIFY COLUMN sub text,
				MODIFY COLUMN status text;"
				)) {
		echo "Error, could not modify table.\n"; die();
		}
		echo "Done!\n";
	}
	

	$conn->close();
?>
		</pre>
<?php
} else {
?>
	</body>
</html>
<?php
die();
}
?>
