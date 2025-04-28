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
			// Read the entire SQL file
			$sqlContent = file_get_contents($filePath);
			if ($sqlContent === false) {
				throw new Exception("Unable to read file: $filePath");
			}

			// Find the CREATE TABLE statement for the original table
			if (!preg_match('/CREATE TABLE `?' . preg_quote($originalTableName, '/') . '`?.*?;/is', $sqlContent, $createMatch)) {
				throw new Exception("CREATE TABLE for `$originalTableName` not found in the dump file.");
			}
			$tableCreationSQL = $createMatch[0];

			// Find all INSERT INTO statements for the original table
			preg_match_all('/INSERT INTO `?' . preg_quote($originalTableName, '/') . '`?.*?;/is', $sqlContent, $insertMatches);
			if (empty($insertMatches[0])) {
				throw new Exception("No data to insert for table `$originalTableName` in the dump file.");
			}

			// Sanitize and generate a dynamic temporary table name
			$originalTableName = preg_replace('/[^a-zA-Z0-9_]/', '_', $originalTableName);
			$tempTableName = $originalTableName . '_temp_' . time();

			// Replace the original table name with the temporary one in the CREATE TABLE statement
			$tableCreationSQL = str_ireplace(
				"CREATE TABLE `$originalTableName`",
				"CREATE TABLE `$tempTableName`",
				$tableCreationSQL
			);

			// Execute CREATE TABLE
			$this->databaseConnection->execute($tableCreationSQL);

			// Insert all rows into the temporary table
			foreach ($insertMatches[0] as $insertStatement) {
				// Replace the original table name inside each INSERT INTO
				$insertStatement = str_ireplace(
					"INSERT INTO `$originalTableName`",
					"INSERT INTO `$tempTableName`",
					$insertStatement
				);

				try {
					$this->databaseConnection->execute($insertStatement);
				} catch (Exception $e) {
					throw $e;
				}
			}


			// Save the temp table name into the class property
			$this->tempTableName = $tempTableName;

		} catch (Exception $e) {
			throw $e;
		}
	}


	// import threads to board
	public function importThreadsToBoard(): array {
		$PIO = PIOPDO::getInstance();
		
		// board uid
		$boardUID = $this->board->getBoardUID();

		$pixmicatThreads = $this->getThreadsFromTempTable();
		$mappedRestoToThreadUids = [];

		// loop over threads and insert into kokonotsuba 
		foreach($pixmicatThreads as $thread) {
			// thread data
			$no = $thread['no'];
			$resto = $no; // thread number of the thread
			$root = $thread['root'];
		
			// post op data
			$md5chksum = $thread['md5chksum'];
			$category = $thread['category'];
			$tim = $thread['tim'];
			$fname = $thread['fname'];
			$ext = $thread['ext'];
			$imgw = $thread['imgw'];
			$imgh = $thread['img'];
			$imgsize = $thread['imgsize'];
			$tw = $thread['tw'];
			$th = $thread['th'];
			$pwd = $thread['pwd'];
			$now = $thread['now'];
			$name = $thread['name'];
			$email = $thread['email'];
			$sub = $thread['sub'];
			$com = $thread['com'];
			$status = $thread['status'];

			// data for koko thread row
			$post_uid = $PIO->getNextPostUid();
			$post_op_number = $no;
			$thread_uid = generateUid();
			$lastBumpTime = $root; 
			$lastReplyTime = $root;

			// thread insert query

			$query = "INSERT INTO $this->threadTableName (boardUID, thread_uid, post_op_number, post_op_post_uid, last_reply_time, last_bump_time) 
			 VALUES(:boardUID, :thread_uid, :post_op_number, :post_op_post_uid, :last_reply_time, :last_bump_time)";		
			
			// parameters
			$params = [
				':boardUID' => $boardUID,
				':thread_uid' => $thread_uid,
				':post_op_number' => $post_op_number,
				':post_op_post_uid' => $post_uid,
				':last_bump_time' => $lastBumpTime,
				':last_reply_time' => $lastReplyTime
			];

			// insert thread row
			$this->databaseConnection->execute($query, $params);
		
			// insert the op post
			$this->insertPost($boardUID,
			 $no, 
			 $thread_uid, 
			 $md5chksum, 
			 $category, 
			 $tim, 
			 $fname, 
			 $ext,
			 $imgw,
			 $imgh,
			 $imgsize,
			 $tw,
			 $th,
			 $pwd,
			 $now,
			 $name,
			 $email,
			 $sub,
			 $com,
			 false,
			 $status);

			 // increment post number
			 $this->board->incrementBoardPostNumber();

			 // successful? now add the resto-to-thread_uid map
			 $mappedRestoToThreadUids[] = [$resto => $thread_uid];
		
		}
		return $mappedRestoToThreadUids;
	}
	
	public function importPostsToThreads(array $mappedRestoToThreadUids): void {
		$boardUID = $this->board->getBoardUID();

		// get pixmicat posts from temp table
		$pixmicatPosts = $this->getPostsFromTempTable();

		// loop and insert
		foreach($pixmicatPosts as $post) {
			$no = $post['no'];
			$resto = $post['resto'];
			$thread_uid = $mappedRestoToThreadUids[$resto];
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
			$status = $post['status'];

			$this->insertPost($boardUID,
			$no, 
			$thread_uid, 
			$md5chksum, 
			$category, 
			$tim, 
			$fname, 
			$ext,
			$imgw, $imgh,
			$imgsize,
			$tw, $th,
			$pwd,
			$now,
			$name,
			$email,
			$sub,
			$com,
			false,
			$status);
		}

	}

	/*Helper methods*/

	// insert post to post table
	private function insertPost(int $boardUID, 
	    int $no, 
	    string $thread_uid, 
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
	    bool $age,
	    string $status): void {
    
	    $query = "INSERT INTO $this->postTableName (boardUID, no, thread_uid, md5chksum, category, tim, fname, ext,
	        imgw, imgh, imgsize, tw, th, pwd, now, name, email, sub, com, age, status)
	        VALUES (
	        :boardUID, :no, :thread_uid, :md5chksum, :category, :tim, :fname, :ext,
	        :imgw, :imgh, :imgsize, :tw, :th, :pwd, :now, :name, :email, :sub, :com, :age, :status)";

	    // Bind parameters
	    $params = [
	        ':boardUID' => $boardUID,
	        ':no' => $no,
	        ':thread_uid' => $thread_uid,
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
	        ':com' => $com,
	        ':age' => $age,
	        ':status' => $status
	    ];

		// insert post to database
		$this->databaseConnection->execute($query, $params);
	}

	// get posts from pixmicat posts
	private function getPostsFromTempTable(): array {
		$query = "SELECT * FROM $this->tempTableName WHERE resto > 0";
		$posts = $this->databaseConnection->fetchAllAsArray($query);
	
		return $posts ?? [];
	}

	// get threads from pixmicat threads
	private function getThreadsFromTempTable(): array {
		$query = "SELECT * FROM $this->tempTableName WHERE resto = 0";
		$threads = $this->databaseConnection->fetchAllAsArray($query);

		return $threads ?? [];
	}

}
