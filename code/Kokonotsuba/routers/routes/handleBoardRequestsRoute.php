<?php

// handleBoardRequests route - handles board actions for admin

namespace Kokonotsuba\routers\routes;

use Exception;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\board\boardService;
use Kokonotsuba\cache\path_cache\boardPathService;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\post\attachment\fileService;
use Kokonotsuba\quote_link\quoteLinkRepository;
use Kokonotsuba\userRole;
use Kokonotsuba\board\boardCreator;
use function Puchiko\request\redirect;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Puchiko\isValidMySQLDumpFile;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class handleBoardRequestsRoute {
	public function __construct(
		private databaseConnection $databaseConnection,
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
		$this->softErrorHandler->handleAuthError(userRole::LEV_ADMIN);

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
		$boardCreator->createNewBoard($boardTitle, $boardSubTitle, $boardIdentifier, $boardListed, $boardPath, getRoleLevelFromSession());
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
			/*$vichanBoardImporter = new vichanBoard(
				$this->databaseConnection,
				$boardCreator,
				$this->boardService,
				$this->postRepository,
				$this->threadRepository,
				$this->fileService,
				$this->transactionManager,
				$this->quoteLinkRepository
			);*/

			// import the instance!
			// the contents are wrapped in a transaction to it shouldn't cause any database problems
			//$vichanBoardImporter->importVichanInstance($dumpPath, $boardPath);
		} catch (Exception $e) {
			// run error
			throw new BoardException('Error during board import: ' . $e->getMessage());
		}
	}
	

}