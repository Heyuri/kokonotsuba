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
use Kokonotsuba\request\request;
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
		private request $request
	) {}


	// handle actions
	public function handleBoardRequests(): void {
		$this->softErrorHandler->handleAuthError(userRole::LEV_ADMIN);

		// edit a board
		if(!empty($this->request->getParameter('edit-board', 'POST'))) {
			$this->editBoardFromRequest();
		}

		// create a new board
		if(!empty($this->request->getParameter('new-board', 'POST'))) {
			$this->createNewBoardFromRequest();
		}

		// import a board
		if(!empty($this->request->getParameter('import-board', 'POST'))) {
			$this->importBoardFromRequest();
		}

		// redirect
		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards');
	}

	// handle board editing
	private function editBoardFromRequest() {
		try {
			$modifiedBoardIdFromPOST = intval($this->request->getParameter('edit-board-uid', 'POST')) ?? '';
			
			if(!$modifiedBoardIdFromPOST) {
				throw new \InvalidArgumentException("Board UID in board editing cannot be NULL!");
			}
			
			if($modifiedBoardIdFromPOST === GLOBAL_BOARD_UID) {
				throw new \InvalidArgumentException("Cannot reserved board.");
			}

			$modifiedBoard = $this->boardService->getBoard($modifiedBoardIdFromPOST);


			if ($this->request->hasParameter('board-action-submit', 'POST') && $this->request->getParameter('board-action-submit', 'POST') === 'delete-board') {
				$this->boardService->deleteBoard($modifiedBoard->getBoardUID());
				redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards');
			}

			$fields = [
				'board_identifier' => $this->request->getParameter('edit-board-identifier', 'POST', false),
				'board_title' => $this->request->getParameter('edit-board-title', 'POST', false),
				'board_sub_title' => $this->request->getParameter('edit-board-sub-title', 'POST', false),
				'config_name' => $this->request->getParameter('edit-board-config-path', 'POST', false),
				'storage_directory_name' => $this->request->getParameter('edit-board-storage-dir', 'POST', false),
				'listed' => $this->request->getParameter('edit-board-listed', 'POST', false)
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

		$boardRedirectUID = $this->request->getParameter('edit-board-uid-for-redirect', 'POST', '');
		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards&view=' . $boardRedirectUID);
	}

	// handle board creation
	private function createNewBoardFromRequest() {
		// Get board information from the request
		$boardTitle = $this->request->getParameter('new-board-title', 'POST') ?? throw new BoardException("Board title wasn't set!");
		$boardSubTitle = $this->request->getParameter('new-board-sub-title', 'POST', '');
		$boardIdentifier = $this->request->getParameter('new-board-identifier', 'POST', '');
		$boardListed = !empty($this->request->getParameter('new-board-listed', 'POST')) ? 1 : 0;
		$boardPath = $this->request->getParameter('new-board-path', 'POST') ?? throw new BoardException("Board path wasn't set!");
	
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
			$boardPath = $this->request->getParameter('import-board-path', 'POST') ?? throw new BoardException("Board path wasn't set!");
			$dumpPath = $this->request->getParameter('import-dump-path', 'POST') ?? throw new BoardException("Dump path wasn't set!");
	
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