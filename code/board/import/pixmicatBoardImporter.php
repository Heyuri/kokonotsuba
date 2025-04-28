<?php
/*
* Pixmicat board importing object for Kokonotsuba!
* Used to migrate a piximcat table to kokonotsuba's database
*/

class pixmicatBoardImporter extends abstractBoardImporter {
	private string $tempTableName;
	private readonly string $threadTableName;
	private readonly string $postTableName;

	public function __construct(mixed $databaseConnection, 
	 board $board, 
	 string $threadTableName, 
	 string $postTableName) {
		$this->databaseConnection = $databaseConnection;
		$this->board = $board;

		$this->threadTableName = $threadTableName;
		$this->postTableName = $postTableName;
	}

	// load an sql dump into a temporary table in koko's db
	public function loadSQLDumpToTempTable(string $filePath, string $originalTableName): void {
		try {
		// Begin transaction to ensure table creation and data inserts are atomic
			$this->databaseConnection->beginTransaction();

			// Read the entire SQL file
			$sqlContent = file_get_contents($filePath);
			if ($sqlContent === false || trim($sqlContent) === '') {
				throw new Exception("SQL dump file missing or empty: $filePath");
			}

			// Find the CREATE TABLE statement for the original table
			if (!preg_match('/CREATE TABLE `?' . preg_quote($originalTableName, '/') . '`?.*?;/is', $sqlContent, $createMatch)) {
				throw new Exception("CREATE TABLE for `$originalTableName` not found in the dump file.");
			}
			$tableCreationSQL = $createMatch[0];

			// Split the dump into individual SQL statements
			$allStatements = $this->splitSQLStatements($sqlContent);

			// Filter only INSERT INTO statements for the original table
			$insertMatches = array_filter($allStatements, function($stmt) use ($originalTableName) {
				return stripos($stmt, "INSERT INTO `$originalTableName`") !== false;
			});

			if (empty($insertMatches)) {
				throw new Exception("No data to insert for table `$originalTableName` in the dump file.");
			}

			// Sanitize and generate a dynamic temporary table name
			$originalTableName = preg_replace('/[^a-zA-Z0-9_]/', '_', $originalTableName);
			$tempTableName = $originalTableName . '_temp_' . time();

			// Replace the original table name with the temporary one in the CREATE TABLE statement
			$tableCreationSQL = preg_replace(
				'/CREATE TABLE/i',
				'CREATE TEMPORARY TABLE',
				$tableCreationSQL,
				1 // only replace the first occurrence
			);
			
			// Then still replace the table name
			$tableCreationSQL = str_ireplace(
				"`$originalTableName`",
				"`$tempTableName`",
				$tableCreationSQL
			);
			

			// Clean and standardize the CREATE TABLE SQL
			$tableCreationSQL = $this->cleanCreateTableSQL($tableCreationSQL);

			// Execute CREATE TABLE
			$this->databaseConnection->execute($tableCreationSQL);

			// Insert all rows into the temporary table
			foreach ($insertMatches as $insertStatement) {
				$insertStatement = str_ireplace(
					"INSERT INTO `$originalTableName`",
					"INSERT INTO `$tempTableName`",
					$insertStatement
				);

				$this->databaseConnection->execute($insertStatement);
			}

			// Save the temp table name into the class property
			$this->tempTableName = $tempTableName;

			// Commit transaction if everything succeeded
			$this->databaseConnection->commit();

		} catch (Exception $e) {
			// Roll back transaction on any error
			$this->databaseConnection->rollBack();
			throw $e;
		}
	}


	private function splitSQLStatements(string $sql): array {
		$statements = [];
		$currentStatement = '';
		$inString = false;
		$escaped = false;
		$stringChar = '';
	
		for ($i = 0, $len = strlen($sql); $i < $len; $i++) {
			$char = $sql[$i];
			$currentStatement .= $char;
	
			if ($inString) {
				if ($escaped) {
					$escaped = false;
				} elseif ($char === '\\') {
					$escaped = true;
				} elseif ($char === $stringChar) {
					$inString = false;
				}
			} else {
				if ($char === '\'' || $char === '"') {
					$inString = true;
					$stringChar = $char;
				} elseif ($char === ';') {
					$statements[] = trim($currentStatement);
					$currentStatement = '';
				}
			}
		}
	
		if (trim($currentStatement) !== '') {
			$statements[] = trim($currentStatement);
		}
	
		return $statements;
	}
	

	private function cleanCreateTableSQL(string $createTableSQL): string {
		// Force ENGINE=InnoDB
		$createTableSQL = preg_replace('/ENGINE=\w+/i', 'ENGINE=InnoDB', $createTableSQL);
	
		// Remove AUTO_INCREMENT=xxxxx (optional, because temp tables may not need starting points)
		$createTableSQL = preg_replace('/AUTO_INCREMENT=\d+/i', '', $createTableSQL);
	
		// Remove old MySQL stuff like ROW_FORMAT
		$createTableSQL = preg_replace('/ROW_FORMAT=\w+/i', '', $createTableSQL);
	
		// Force utf8mb4 charset
		$createTableSQL = preg_replace('/CHARSET=\w+/i', 'CHARSET=utf8mb4', $createTableSQL);
	

		return trim($createTableSQL);
	}

	// so that new board's post number matches the original 
	public function updateBoardPostNumber() {
		// get highest post number
		$maxPostNumber = $this->getMaxPostNumberFromTempTable();
	
		// increment the board post numbers
		$this->board->incrementBoardPostNumberMultiple($maxPostNumber);
	}

	// import threads from pixmicat to kokonotsuba board
	public function importThreadsToBoard(): array {
		$PIO = PIOPDO::getInstance();

		// board uid
		$boardUID = $this->board->getBoardUID();

		$pixmicatThreads = $this->getThreadsFromTempTable();
		$mappedRestoToThreadUids = [];

		try {
			// Begin transaction to ensure atomic import of threads and OP posts
			$this->databaseConnection->beginTransaction();

			// Loop over threads and insert into Kokonotsuba
			foreach($pixmicatThreads as $thread) {
				// thread data
				$no = $thread['no'];
				$resto = $no;
				$root = $thread['root'];

				// post op data
				$time = $thread['time'];
				$md5chksum = $thread['md5chksum'];
				$category = $thread['category'];
				$tim = $thread['tim'];
				$fname = $thread['fname'];
				$ext = $thread['ext'];
				$imgw = $thread['imgw'];
				$imgh = $thread['imgh'];
				$imgsize = $thread['imgsize'];
				$tw = $thread['tw'];
				$th = $thread['th'];
				$pwd = $thread['pwd'];
				$now = $thread['now'];
				$name = $thread['name'];
				$email = $thread['email'];
				$sub = $thread['sub'];
				$com = $thread['com'];
				$host = $thread['host'];
				$status = $thread['status'];

				// data for koko thread row
				$post_uid = $PIO->getNextPostUid();
				$post_op_number = $no;
				$thread_uid = generateUid();
				$lastBumpTime = $root;
				$lastReplyTime = $root;

				// Insert thread
				$query = "INSERT INTO $this->threadTableName (boardUID, thread_uid, post_op_number, post_op_post_uid, last_reply_time, last_bump_time) 
					VALUES(:boardUID, :thread_uid, :post_op_number, :post_op_post_uid, :last_reply_time, :last_bump_time)";

				$params = [
					':boardUID' => $boardUID,
					':thread_uid' => $thread_uid,
					':post_op_number' => $post_op_number,
					':post_op_post_uid' => $post_uid,
					':last_bump_time' => $lastBumpTime,
					':last_reply_time' => $lastReplyTime
				];

				$this->databaseConnection->execute($query, $params);

				// Insert the OP post
				$this->insertPost(
					$boardUID, $no, $thread_uid, $root, $time, $md5chksum,
					$category, $tim, $fname, $ext, $imgw, $imgh, $imgsize,
					$tw, $th, $pwd, $now, $name, $email, $sub, $com, $host, $status
				);

				// Map resto to thread_uid
				$mappedRestoToThreadUids[$resto] = $thread_uid;
			}

			// Commit transaction
			$this->databaseConnection->commit();

		} catch (Exception $e) {
			// Roll back transaction if anything fails
			$this->databaseConnection->rollBack();
			throw $e;
		}

		return $mappedRestoToThreadUids;
	}

	// import replies from pixmicat table to newly created koko threads
	public function importRepliesToThreads(array $mappedRestoToThreadUids): void {
		$boardUID = $this->board->getBoardUID();
		$pixmicatPosts = $this->getRepliesFromTempTable();
		
		try {
			// Begin transaction for bulk insert of replies
			$this->databaseConnection->beginTransaction();
	
			foreach ($pixmicatPosts as $post) {
				$no = $post['no'];
				$resto = $post['resto'];
			
				// Check if mapped resto exists, otherwise skip
				if (!isset($mappedRestoToThreadUids[$resto])) {
					continue;
				}
				
				// get reply data
				$thread_uid = $mappedRestoToThreadUids[$resto];
				$root = $post['root'];
				$time = $post['time'];
				$md5chksum = $post['md5chksum'];
				$category = $post['category'];
				$tim = $post['tim'];
				$fname = $post['fname'];
				$ext = $post['ext'];
				$imgw = $post['imgw'];
				$imgh = $post['imgh'];
				$imgsize = $post['imgsize'];
				$tw = $post['tw'];
				$th = $post['th'];
				$pwd = $post['pwd'];
				$now = $post['now'];
				$name = $post['name'];
				$email = $post['email'];
				$sub = $post['sub'];
				$com = $post['com'];
				$host = $post['host'];
				$status = $post['status'];
			
				// insert post reply
				$this->insertPost(
					$boardUID, $no, $thread_uid, $root, $time, $md5chksum,
					$category, $tim, $fname, $ext, $imgw, $imgh, $imgsize,
					$tw, $th, $pwd, $now, $name, $email, $sub, $com, $host, 
					$status
				);

			}			
	
			// Commit after inserting all posts
			$this->databaseConnection->commit();
	
		} catch (Exception $e) {
			$this->databaseConnection->rollBack();
			throw $e;
		}
		
	}

	/*Helper methods*/

	// insert post to post table
	private function insertPost(int $boardUID, 
		int $no, 
		string $thread_uid, 
		string $root,
		int $time,
		string $md5chksum, 
		string $category, 
		int $tim, 
		string $fname, 
		string $ext, 
		int $imgw, int $imgh,
		string $imgsize,
		int $tw, int $th,
		string $pwd,
		string $now,
		string $name,
		string $email,
		string $sub,
		string $com,
		string $host,
		string $status): void {
	
		$query = "INSERT INTO $this->postTableName (boardUID, no, thread_uid, root, time, md5chksum, category, tim, fname, ext,
			imgw, imgh, imgsize, tw, th, pwd, now, name, email, sub, com, host, status)
			VALUES (
			:boardUID, :no, :thread_uid, :root, :time, :md5chksum, :category, :tim, :fname, :ext,
			:imgw, :imgh, :imgsize, :tw, :th, :pwd, :now, :name, :email, :sub, :com, :host, :status)";

		// Bind parameters
		$params = [
			':boardUID' => $boardUID,
			':no' => $no,
			':thread_uid' => $thread_uid,
			':root' => $root,
			':time' => $time,
			':md5chksum' => $md5chksum,
			':category' => $category,
			':tim' => $tim,
			':fname' => $fname,
			':ext' => $ext,
			':imgw' => $imgw,
			':imgh' => $imgh,
			':imgsize' => $imgsize,
			':tw' => $tw,
			':th' => $th,
			':pwd' => $pwd,
			':now' => $now,
			':name' => $name,
			':email' => $email,
			':sub' => $sub,
			':com' => truncateForText($com), // somehow it may be larger
			':host' => $host,
			':status' => $status
		];

		// insert post to database
		$this->databaseConnection->execute($query, $params);
	}

	// get posts replies from pixmicat posts (a reply is where resto > 0)
	private function getRepliesFromTempTable(): array {
		$query = "SELECT * FROM $this->tempTableName WHERE resto > 0";
		$posts = $this->databaseConnection->fetchAllAsArray($query);
	
		return $posts ?? [];
	}

	// get threads from pixmicat threads (where a post has a resto of 0)
	private function getThreadsFromTempTable(): array {
		$query = "SELECT * FROM $this->tempTableName WHERE resto = 0";
		$threads = $this->databaseConnection->fetchAllAsArray($query);

		return $threads ?? [];
	}

	// get max post number from `no` column
	private function getMaxPostNumberFromTempTable(): int {
		$query = "SELECT MAX(no) FROM $this->tempTableName";
		$maxPostNumber = $this->databaseConnection->fetchColumn($query);

		return $maxPostNumber;
	}
}
