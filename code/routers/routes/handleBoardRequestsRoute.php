<?php

// handleBoardRequests route - handles board actions for admin

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class handleBoardRequestsRoute {
	private readonly array $config;
	private readonly softErrorHandler $softErrorHandler;
	private readonly boardIO $boardIO;
	private readonly boardPathCachingIO $boardPathCachingIO;
	private readonly globalHTML $globalHTML;

	private readonly DatabaseConnection $databaseConnection;

	public function __construct(
		array $config,
		softErrorHandler $softErrorHandler,
		boardIO $boardIO,
		globalHTML $globalHTML
	) {
		$this->config = $config;
		$this->softErrorHandler = $softErrorHandler;
		$this->boardIO = $boardIO;
		$this->boardPathCachingIO = boardPathCachingIO::getInstance();
		$this->databaseConnection = DatabaseConnection::getInstance();
		$this->globalHTML = $globalHTML;
	}


		// handle actions
		public function handleBoardRequests(): void {
		$this->softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_ADMIN);

		// edit a board
		if(!empty($_POST['edit-board'])) {
			$this->editBoardFromRequest();
		}

		// create a new board
		if(!empty($_POST['new-board'])) {
			$this->createNewBoardFromRequest();
		}

		// import a board
		if(!empty($_POST['import-board'])) {
			$this->importBoardFromRequest();
		}

		// redirect
		redirect($this->config['PHP_SELF'] . '?mode=boards');
	}

	// handle board editing
	private function editBoardFromRequest() {
		try {
			$modifiedBoardIdFromPOST = intval($_POST['edit-board-uid']) ?? '';
			
			if(!$modifiedBoardIdFromPOST) {
				throw new \InvalidArgumentException("Board UID in board editing cannot be NULL!");
			}
			
			if($modifiedBoardIdFromPOST === GLOBAL_BOARD_UID) {
				throw new \InvalidArgumentException("Cannot reserved board.");
			}

			$modifiedBoard = $this->boardIO->getBoardByUID($modifiedBoardIdFromPOST);


			if (isset($_POST['board-action-submit']) && $_POST['board-action-submit'] === 'delete-board') {
				$this->boardIO->deleteBoardByUID($modifiedBoard->getBoardUID());
				redirect($this->config['PHP_SELF'] . '?mode=boards');
			}

			$fields = [
				'board_identifier' => $_POST['edit-board-identifier'] ?? false,
				'board_title' => $_POST['edit-board-title'] ?? false,
				'board_sub_title' => $_POST['edit-board-sub-title'] ?? false,
				'config_name' => $_POST['edit-board-config-path'] ?? false,
				'storage_directory_name' => $_POST['edit-board-storage-dir'] ?? false,
				'listed' => $_POST['edit-board-listed'] ?? false
			];

			if (!file_exists(getBoardConfigDir() . $fields['config_name'])) {
				$this->globalHTML->error("Invalid config file, doesn't exist.");
			}
			if (!file_exists(getBoardStoragesDir() . $fields['storage_directory_name'])) {
				$this->globalHTML->error("Invalid storage directory, doesn't exist.");
			}

			$this->boardIO->editBoardValues($modifiedBoard, $fields);
		} catch (Exception $e) {
			http_response_code(500);
			echo "Error: " . $e->getMessage();
		}

		$boardRedirectUID = $_POST['edit-board-uid-for-redirect'] ?? '';
		redirect($this->config['PHP_SELF'] . '?mode=boards&view=' . $boardRedirectUID);
	}

	// handle board creation
	private function createNewBoardFromRequest() {
		// Get board information from the request
		$boardTitle = $_POST['new-board-title'] ?? $this->globalHTML->error("Board title wasn't set!");
		$boardSubTitle = $_POST['new-board-sub-title'] ?? '';
		$boardIdentifier = $_POST['new-board-identifier'] ?? '';
		$boardListed = !empty($_POST['new-board-listed']) ? 1 : 0;
		$boardPath = $_POST['new-board-path'] ?? $this->globalHTML->error("Board path wasn't set!");
	
		// Create an instance of the BoardCreator helper class
		$boardCreator = new boardCreator($this->globalHTML, $this->config, $this->boardIO, $this->boardPathCachingIO);
	
		// Call the createNewBoard method in the BoardCreator class
		$boardCreator->createNewBoard($boardTitle, $boardSubTitle, $boardIdentifier, $boardListed, $boardPath);
	}
	

	// Import board from request
	private function importBoardFromRequest() {
		try {
			// get database settings
			$dbSettings = getDatabaseSettings();
			$postTableName = $dbSettings['POST_TABLE'];
			$threadTableName = $dbSettings['THREAD_TABLE'];
	
			// board creation object
			$boardCreator = new boardCreator($this->globalHTML, 
			 $this->config,
			 $this->boardIO, 
			 $this->boardPathCachingIO);
	
			// Initialize board variables
			$boardTitle = $_POST['import-board-title'] ?? $this->globalHTML->error("Board title wasn't set!");
			$boardSubTitle = $_POST['import-board-sub-title'] ?? $this->globalHTML->error("Board subtitle wasn't set!");
			$boardIdentifier = $_POST['import-board-identifier'] ?? $this->globalHTML->error("Board identifier wasn't set!");
			$boardListed = !empty($_POST['import-board-listed']) ? 1 : 0;
			$boardPath = $_POST['import-board-path'] ?? $this->globalHTML->error("Board path wasn't set!");
			$boardTableName = $_POST['import-board-tablename'] ?? $this->globalHTML->error("Table name wasn't set!");
			$boardDatabaseFile = $_FILES['import-board-file'];
	
			// check if the file was uploaded correctly
			if(!isset($boardDatabaseFile) || $boardDatabaseFile['error'] !== UPLOAD_ERR_OK) {
				$this->globalHTML->error("Board SQL file wasn't uploaded properly!");
			}
	
			// basic check to ensure it's a mysql dump
			if(!isValidMySQLDumpFile($boardDatabaseFile['tmp_name'])) {
				throw new Exception("Invalid database file");
			}
	
			// first, create the board and get new board uid
			$importedBoard = $boardCreator->createNewBoard($boardTitle,
			 $boardSubTitle, 
			 $boardIdentifier, 
			 $boardListed, 
			 $boardPath);
	
			// check board uid is valid
			if(!$importedBoard) $this->globalHTML->error("Invalid board uid");
	
			// Create board importer instance (for now, hard-coded to pixmicat)
			$pixmicatBoardImporter = new pixmicatBoardImporter($this->databaseConnection,
			 $importedBoard, 
			 $threadTableName, 
			 $postTableName);
	
			// load mysql
			$pixmicatBoardImporter->loadSQLDumpToTempTable($boardDatabaseFile['tmp_name'], $boardTableName);
	
			// Import threads from original database to current database
			// and get an assoc of 'resto' keys that resolve to thread Uids
			$mapRestoToThreadUids = $pixmicatBoardImporter->importThreadsToBoard();
	
			// Import posts to threads
			$pixmicatBoardImporter->importRepliesToThreads($mapRestoToThreadUids);
	
			// Update the board post number
			$pixmicatBoardImporter->updateBoardPostNumber();

			// now rebuild
			$importedBoard->rebuildBoard();

		} catch (Exception $e) {
			// do clean-up if the board was created so theres no left-over files
			if($importedBoard) {
				$boardUid = $importedBoard->getBoardUID();
				$boardPath = $importedBoard->getBoardCachedPath();
				// delete new board from database
				$this->boardIO->deleteBoardByUID($boardUid);
			
				// delete files
				safeRmdir($boardPath);
			}

			// run error
			$this->globalHTML->error('Error during board import: ' . $e->getMessage());
		}
	}
	

}