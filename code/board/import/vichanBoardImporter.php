<?php
/*
* Vichan board importing object for Kokonotsuba!
* This currently has no implementation nor working code, but is planned
*/
set_time_limit(0);
use Kokonotsuba\Root\Constants\userRole;

class vichanBoardImporter {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private boardCreator $boardCreator,
		private boardService $boardService,
		private postRepository $postRepository,
		private threadRepository $threadRepository,
		private fileService $fileService,
		private transactionManager $transactionManager,
		private quoteLinkRepository $quoteLinkRepository
	) {}

	public function importVichanInstance(string $sqlDumpPath, string $baseBoardPath): void {
		// wrap in a transaction to avoid potential corruption
		$this->transactionManager->run(function() use ($sqlDumpPath, $baseBoardPath) {
			// load all vichan tables that we'll import from an sql dump
			// then store the table names here
			$tableNames = $this->loadTablesInChunks($sqlDumpPath);

			// get the temp board table name
			$boardTable = $tableNames['boards'];

			// wrap in try catch so we can pune failed boards
			try {
				// loop through boards and import to koko
				$boardUriMap = $this->importBoards($boardTable, $baseBoardPath);

				// loop through the table posts to create posts
				$this->importPosts($tableNames, $boardUriMap);
			} catch (Throwable) {
				// loop over board uids
				foreach($boardUriMap as $boardUid) {
					// then delete the associated board
					$this->boardService->deleteBoard($boardUid);
				}
			}
		});
	}

	private function loadTablesInChunks(string $sqlDumpPath): array {
		if (!file_exists($sqlDumpPath)) {
			throw new RuntimeException("SQL dump not found: {$sqlDumpPath}");
		}

		// 1. Create a temporary database
		$tmpDb = "tmp_import_" . bin2hex(random_bytes(4));
		$this->databaseConnection->execute("CREATE DATABASE `$tmpDb`");
		$this->databaseConnection->execute("USE `$tmpDb`");

		// 2. Stream the SQL dump into the temporary database
		$handle = fopen($sqlDumpPath, 'r');
		if (!$handle) {
			throw new RuntimeException("Failed to open SQL dump: $sqlDumpPath");
		}

		$buffer = "";
		while (($line = fgets($handle)) !== false) {
			$buffer .= $line;
			if (substr(trim($buffer), -1) === ';') {
				try {
					$this->databaseConnection->execute($buffer);
				} catch (\Throwable $e) {
					fclose($handle);
					throw new RuntimeException(
						"Failed executing SQL:\n" . htmlspecialchars($buffer) .
						"\n\nError: " . $e->getMessage(), 0, $e
					);
				}
				$buffer = "";
			}
		}
		fclose($handle);

		// 3. Detect tables we care about (boards, posts_*)
		$tables = $this->databaseConnection
			->fetchAllAsArray("SHOW TABLES FROM `$tmpDb`");

		$tableNames = [];
		foreach ($tables as $row) {
			$table = array_values($row)[0];
			if ($table === 'boards' || preg_match('/^posts_/i', $table)) {
				// fully qualified table name with database
				$tableNames[$table] = "`$tmpDb`.`$table`";
			}
		}

		// force it to still load the main database
		$this->databaseConnection->execute("USE " . getDatabaseSettings()['DATABASE_NAME']);

		return $tableNames;
	}

	private function importBoards(string $boardTable, string $boardPathFromRequest): array {
		// get the board data
		$vichanBoards = $this->getBoardsFromVichan($boardTable);

		// as each board gets created - their former URI and
		$uriBoardMap = [];

		// loop through data and create boards using koko board service
		foreach($vichanBoards as $vBoards) {
			// get board uri/identifier
			$boardIdentifier = $vBoards['uri'];

			// get board title
			$boardTitle = $vBoards['title'];

			// get board sub title
			$boardSubTitle = $vBoards['subtitle'];

			// create listed board using vichan board data + path from request
			$newKokoBoard = $this->boardCreator->createNewBoard(
				$boardTitle, 
				$boardSubTitle,
				$boardIdentifier, 
				true, 
				$boardPathFromRequest
			);

			// if the new koko board isn't null then create uri => board_uid map entry
			if($newKokoBoard) {
				// get board uid
				$boardUid = $newKokoBoard->getBoardUID();

				// create map
				// uri (e.g 'a', 'b', 'x', etc.) => board_uid
				$uriBoardMap[$boardIdentifier] = $boardUid;
			}

		}
		
		// all done - return the map
		return $uriBoardMap;
	}

	private function getBoardsFromVichan(string $boardTableName): array {
		// assemble query to fetch board data
		$boardQuery = "SELECT * FROM $boardTableName";

		// fetch all boards as an array
		$boards =$this->databaseConnection->fetchAllAsArray($boardQuery);

		// then return boards
		return $boards;
	}

	private function importPosts(array $tableNames, array $boardUriMap): void {
		// loop through chunked post entries and insert into koko post/thread tables
		foreach($boardUriMap as $uri => $boardUid) {
			// the supposed table name if the posts table for this board
			$tableKey = 'posts_' . $uri;

			// continue if the post table name doesn't exist in the table names
			if(!array_key_exists($tableKey, $tableNames)) {
				continue;
			}

			// then get the actual table name
			$postTableName = $tableNames[$tableKey];

			// then process posts from that board
			// i.e, take data from vichan table rows and insert them into applicable koko post rows
			$this->processPostsFromBoard($postTableName, $boardUid);
		}
	}

	private function processPostsFromBoard(string $postTableName, int $boardUid): void {
		// init thread map array
		$threadMap = [];
		
		// init the id=>post_uid map
		$postUidMap = [];

		// these methods use yield as to not use up all available memory
		// for databases as large as gurochan's (+300MB) it's not viable to load it all into memory

		// get vichan threads + OPs loaded in chunks
    	foreach ($this->getChunkedVichanThreads($postTableName) as $threads) {
    	    foreach ($this->processThreadChunk($threads, $boardUid, $postUidMap) as $opId => $threadUid) {
    	        if (isset($threadMap[$opId])) {
    	            throw new RuntimeException(
    	                "Duplicate OP id {$opId} detected in board {$boardUid}. Thread map corruption likely."
    	            );
    	        }
    	        $threadMap[$opId] = $threadUid;
    	    }
    	}

		// load/process replies
		foreach ($this->getChunkedVichanReplies($postTableName) as $replies) {
			$this->processReplyChunk($replies, $boardUid, $threadMap, $postUidMap);
		}

		// get the board object
		$board = $this->boardService->getBoard($boardUid);

		// build quote links for the board from post comments and IDs
		// - memory intensive
		$this->buildQuoteLinks($postTableName, $postUidMap, $boardUid);

		// update latest post number increment for board
		$this->updatePostCounter($postTableName, $board);		

		// then handle post position (thread reply numbers)
		$this->updatePostPositions();

		// loop through and correct bump order
		foreach($threadMap as $uid) {
			// update bump time to the last reply
			$this->threadRepository->bumpThread($uid);
		}

		// all done!
		// rebuild board html
		$board->rebuildBoard();
	}

	private function getChunkedVichanThreads(string $postTableName): \Generator {
		// get chunked threads
		// pass true to fetch thread OPs
		return $this->getChunkedPosts($postTableName, true);
	}

	private function getChunkedVichanReplies(string $postTableName): \Generator {
		// get chunked replies
		// pass true to fetch thread replies
		return $this->getChunkedPosts($postTableName, false);
	}
	
	private function getChunkedPosts(string $postTableName, bool $isThreadNull): \Generator {
		$batchSize = 100; // number of rows per batch
		$threadCondition = $isThreadNull ? 'IS NULL' : 'IS NOT NULL'; // condition for OPs vs replies
		$lastId = 0; // track the last seen post ID

		while (true) {
			// Fetch the next batch of posts with IDs greater than the last seen
			$query = "SELECT * FROM $postTableName
					WHERE thread $threadCondition
					AND id > $lastId
					ORDER BY id ASC
					LIMIT $batchSize";

			$rows = $this->databaseConnection->fetchAllAsArray($query);

			// If no rows were returned, we've reached the end of the table
			if (!$rows) {
				break;
			}

			// Update $lastId to the ID of the last post in this batch
			// This ensures the next batch starts where this one left off
			$lastId = end($rows)['id'];

			// Yield the current batch to the caller
			yield $rows;
		}
	}

	private function processThreadChunk(array $posts, int $boardUid, array &$postUidMap): array {
		// init thread id to thread_uid map
		$threadMap = [];

		// loop over thread OPs in the chunk and process/insert them into koko's database
		foreach($posts as $p) {
			// whether its an OP or not
			$isOp = is_null($p['thread']) || $p['thread'] == $p['id'];

			// continue if the post isn't actually a thread OP for some reason
			if (!$isOp) {
			    continue;
			}

			// get id of the thread OP
			// this is what appears in the `thread` column
			// as well as the post number of the OP
			$id = $p['id'];

			if (isset($threadMap[$id])) {
   				 // This is fatal — two OPs share the same post number.
   				 throw new RuntimeException("WARNING: Duplicate OP id $id detected. Thread map corruption likely.");
    			continue;
			}

			// generate thread uid
			$generatedThreadUid = generateUid();

			// insert thread
			$this->threadRepository->insertThread(
				$generatedThreadUid, 
				$id,
				$boardUid
			);

			// then insert OP post since we already have it loaded
			$postUid = $this->insertPost($p, $generatedThreadUid, $boardUid);

			// append to post map
			// id => post_uid
			$postUidMap[$id] = $postUid;

			// append to Map
			// op number => thread_uid
			$threadMap[$id] = $generatedThreadUid;
		}

		// then return the id => thread_uid map
		return $threadMap;
	}

	private function processReplyChunk(
		array $posts, 
		int $boardUid, 
		array $threadMap,
		array &$postUidMap
	): void {
		// now we can handle insertion/transformation at the post-level
		foreach($posts as $p) {
			// get thread id
			$threadId = $p['thread'];

			// continue if thread id is null - that means it's a thread OP which isn't meant to be getting processed here
			if(is_null($threadId)) {
				continue;
			}
			
			// check if thread id exists in map
			if (!isset($threadMap[$threadId])) { continue; }


			// get the thread uid
			$threadUid = $threadMap[$threadId];

			// continue if thread uid wasn't found (orphaned reply)
			if(!$threadUid) {
				continue;
			}

			// insert reply
			// also get post uid to put into map
			$postUid = $this->insertPost($p, $threadUid, $boardUid);

			// append to post map
			// id => post_uid
			$postUidMap[$p['id']] = $postUid;
		}
	}

	private function updatePostPositions(): void {
		// generate post position query
		$query = "
			UPDATE posts p
				JOIN (
					SELECT 
						post_uid,
						ROW_NUMBER() OVER (PARTITION BY thread_uid ORDER BY post_uid ASC) - 1 AS position
					FROM posts
				) numbered ON p.post_uid = numbered.post_uid
			SET p.post_position = numbered.position";

		// then execute query
		$this->databaseConnection->execute($query);
	}

	private function buildQuoteLinks(string $postTableName, array $postUidMap, int $boardUid): void {
		if (empty($postUidMap)) {
			return;
		}

		$query = "SELECT id, body FROM {$postTableName} ORDER BY id ASC";
		$posts = $this->databaseConnection->fetchAllAsArray($query);

		$quoteLinksToInsert = [];

		foreach ($posts as $post) {

			$vichanPostId = (int)$post['id'];
			$body = $post['body'] ?? '';

			$hostPostUid = $postUidMap[$vichanPostId] ?? null;
			if (!$hostPostUid) {
				continue;
			}

			preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $body, $matches, PREG_SET_ORDER);

			if (empty($matches)) {
				continue;
			}

			$quotedIds = [];
			foreach ($matches as $m) {
				$quotedIds[] = (int)$m[2];
			}
			$quotedIds = array_unique($quotedIds);

			foreach ($quotedIds as $quotedId) {
				$targetUid = $postUidMap[$quotedId] ?? null;
				if (!$targetUid) {
					continue;
				}

				$quoteLinksToInsert[] = [
					'host_post_uid'   => $hostPostUid,
					'target_post_uid' => $targetUid,
					'board_uid'       => $boardUid,
				];
			}
		}

		if (!empty($quoteLinksToInsert)) {
			$this->quoteLinkRepository->insertQuoteLinks($quoteLinksToInsert);
		}
	}

	private function updatePostCounter(string $postTableName, board $board): void {
		// get highest post number
		$maxPostNumber = $this->getMaxPostNumberFromTempTable($postTableName);

		// increment the board post numbers
		$board->incrementBoardPostNumberMultiple($maxPostNumber);
	}

	private function getMaxPostNumberFromTempTable(string $postTableName): int {
		$query = "SELECT MAX(id) FROM $postTableName";
		$maxPostNumber = $this->databaseConnection->fetchColumn($query);

		return $maxPostNumber;
	}

	private function insertPost(array $p, string $threadUid, int $boardUid): int {
		// get post number
		$no = $p['id'] ?? 0;

		// get ip address
		$host = new IPAddress($p['ip'] ?? '');

		// get email
		$email = $p['email'] ?? '';

		// generate hoster hash
		$poster_hash = generatePostHash(
			$host,
			$p['thread'] ?? $no, 
			$email, 
			userRole::LEV_NONE, 
			'', 
			false
		);

		// check for if its an OP
		// NULL thread id value means its an OP according to vichan's schema
		$is_op = is_null($p['thread']) || $p['thread'] == $p['id'];

		// get post date formatter
		$postDateFormatter = new postDateFormatter(0);

		// get the unix timestamp of when the post was made
		$time = $p['time'] ?? '';

		// generate now
		$now = $postDateFormatter->formatFromTimestamp($time) ?? '';

		// get name
		$name = $p['name'] ?? '';

		// generate tripcode/secure_tripcode from trip value
		[$tripcode, $secure_tripcode] = $this->generateTrip($p['trip']);

		// get capcode
		// should be cross-compatible with koko
		$capcode = $p['capcode'] ?? '';

		// get subject
		$sub = $p['subject'] ?? '';

		// get comment/body
		$com = $p['body'] ?? '';

		// get age/sage
		$age = !$p['sage'];

		// prepare post data-transfer-object
		$postRegistData = new postRegistData(
			$no,
			$poster_hash,
			$threadUid,
			$is_op,
			'',
			'',
			$now,
			$name,
			$tripcode,
			$secure_tripcode,
			$capcode,
			$email,
			$sub,
			$com,
			$host,
			$age,
			'',
			0
		);

		// get root timestamp
		$root = gmdate('Y-m-d H:i:s', $time);

		// generate params
		$postParams = $postRegistData->toParams($boardUid, $root);

		// get next post uid before insert - which will be the post uid of the newly inserted post
		$postUid = $this->postRepository->getNextPostUid();

		// then insert post using post repo
		$this->postRepository->insertPost($postParams);

		// get file data
		// vichan stores files in the json format
		$filesJson = $p['files'];

		// also extract and insert attachment if it has any
		if(!is_null($filesJson)) {
			// insert attachment data
			$this->insertAttachments($filesJson, $postUid);
		}

		// return the post_uid for the map
		return $postUid;
	}

	private function generateTrip(?string $vichanTrip): array {
		// FYI, [$tripcode, $secure_tripcode]
		
		// return early if vichan tripkey is null
		if(is_null($vichanTrip)) {
			return ['', ''];
		}

		// if the tripkey contains 2 '!!' then its a secure tripcode
		if(str_contains($vichanTrip, '!!')) {
			// process the vichan trip and clip off the first 2 characters (to get rid of the tripkey)
			$vichanTrip = substr($vichanTrip, 2);

			// return secure tripcode
			// [$tripcode, $secure_tripcode]
			return ['', $vichanTrip];
		}
		// if it contains only 1 '!' then its a regular tripcode
		else if(str_contains($vichanTrip, '!')) {
			// strip off first character, the '!' tripkey
			$vichanTrip = substr($vichanTrip, 1);

			// return regular tripcode
			// [$tripcode, $secure_tripcode]
			return [$vichanTrip, ''];
		}

		// if its neither of these then its invalid , return empty on both values
		// [$tripcode, $secure_tripcode]
		return ['', ''];
	}

	private function insertAttachments(string $filesJson, int $postUid): void {
		// decode json data into array
		$filesArray = json_decode($filesJson, true);

		// return early if empty for some reason
		if(empty($filesArray)) {
			return;
		}

		// loop over each file entry
		foreach($filesArray as $file) {
			// get file name
			$fileName = $file['name'] ?? '';

			// we have to take the last 4 characters off the name since it includes the extension
			$fileName = substr($fileName, 0, -4); 

			// get mime type
			$mimeType = $file['type'] ?? '';

			// get size
			$fileSize = $file['size'] ?? 0;

			// get stored file name
			$storedFileName = $file['file_id'] ?? '';

			// get file extension
			$fileExtension = $file['extension'] ?? '';

			// get md5 hash
			$fileMd5 = $file['hash'] ?? '';

			// get file width
			$fileWidth = $file['width'] ?? 0;

			// get file height
			$fileHeight = $file['height'] ?? 0;

			// get thumb width
			$thumbWidth = $file['thumbWidth'] ?? 0;

			// get thumb height
			$thumbHeight = $file['thumbHeight'] ?? 0;

			// finally, insert the extracted data
			$this->fileService->addFile(
				$postUid,
				$fileName,
				$storedFileName,
				$fileExtension,
				$fileMd5,
				$fileWidth,
				$fileHeight,
				$thumbWidth,
				$thumbHeight,
				$fileSize,
				$mimeType,
				false
			);
		}
	}
}