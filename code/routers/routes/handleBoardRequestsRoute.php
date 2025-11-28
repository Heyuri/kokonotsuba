<?php

// handleBoardRequests route - handles board actions for admin

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class handleBoardRequestsRoute {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private array $config,
		private softErrorHandler $softErrorHandler,
		private boardService $boardService,
		private boardPathService $boardPathService,
		private transactionManager $transactionManager,
		private postRepository $postRepository,
		private threadRepository $threadRepository,
		private fileService $fileService,
		private quoteLinkRepository $quoteLinkRepository,
	) {}


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
		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards');
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

			$modifiedBoard = $this->boardService->getBoard($modifiedBoardIdFromPOST);


			if (isset($_POST['board-action-submit']) && $_POST['board-action-submit'] === 'delete-board') {
				$this->boardService->deleteBoard($modifiedBoard->getBoardUID());
				redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards');
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
				throw new BoardException("Invalid config file, doesn't exist.");
			}
			if (!file_exists(getBoardStoragesDir() . $fields['storage_directory_name'])) {
				throw new BoardException("Invalid storage directory, doesn't exist.");
			}

			$this->boardService->editBoard($modifiedBoard, $fields);
		} catch (Exception $e) {
			http_response_code(500);
			echo "Error: " . $e->getMessage();
		}

		$boardRedirectUID = $_POST['edit-board-uid-for-redirect'] ?? '';
		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards&view=' . $boardRedirectUID);
	}

	// handle board creation
	private function createNewBoardFromRequest() {
		// Get board information from the request
		$boardTitle = $_POST['new-board-title'] ?? throw new BoardException("Board title wasn't set!");
		$boardSubTitle = $_POST['new-board-sub-title'] ?? '';
		$boardIdentifier = $_POST['new-board-identifier'] ?? '';
		$boardListed = !empty($_POST['new-board-listed']) ? 1 : 0;
		$boardPath = $_POST['new-board-path'] ?? throw new BoardException("Board path wasn't set!");
	
		// Create an instance of the BoardCreator helper class
		$boardCreator = new boardCreator($this->boardService);
	
		// Call the createNewBoard method in the BoardCreator class
		$boardCreator->createNewBoard($boardTitle, $boardSubTitle, $boardIdentifier, $boardListed, $boardPath);
	}
	

	// Import board from request
	private function importBoardFromRequest() {
		try {
			// board creation object
			$boardCreator = new boardCreator(
			 $this->boardService, 
			 $this->boardPathService);
	
			// Initialize board variables
			$boardPath = $_POST['import-board-path'] ?? throw new BoardException("Board path wasn't set!");
			$dumpPath = $_POST['import-dump-path'] ?? throw new BoardException("Dump path wasn't set!");
	
			// basic check to ensure it's a mysql dump
			if(!isValidMySQLDumpFile($dumpPath)) {
				throw new Exception("Invalid database file");
			}
	
			// Create board importer instance
			$vichanBoardImporter = new vichanBoardImporter(
				$this->databaseConnection,
				$boardCreator,
				$this->boardService,
				$this->postRepository,
				$this->threadRepository,
				$this->fileService,
				$this->transactionManager,
				$this->quoteLinkRepository
			);

			// import the instance!
			// the contents are wrapped in a transaction to it shouldn't cause any database problems
			$vichanBoardImporter->importVichanInstance($dumpPath, $boardPath);
		} catch (Exception $e) {
			// run error
			throw new BoardException('Error during board import: ' . $e->getMessage());
		}
	}
	

}