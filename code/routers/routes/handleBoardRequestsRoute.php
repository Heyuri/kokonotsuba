<?php

// handleBoardRequests route - handles board actions for admin

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class handleBoardRequestsRoute {
	private readonly array $config;
	private readonly softErrorHandler $softErrorHandler;
	private readonly boardIO $boardIO;
	private readonly globalHTML $globalHTML;

	public function __construct(
		array $config,
		softErrorHandler $softErrorHandler,
		boardIO $boardIO,
		globalHTML $globalHTML
	) {
		$this->config = $config;
		$this->softErrorHandler = $softErrorHandler;
		$this->boardIO = $boardIO;
		$this->globalHTML = $globalHTML;
	}

	public function handleBoardRequests(): void {
		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_ADMIN']);


		// handle actions

		if (!empty($_POST['edit-board'])) {
			$this->editBoardFromRequest();
		}

		if (!empty($_POST['new-board'])) {
			$this->createNewBoardFromRequest();
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
		$boardPathCachingIO = boardPathCachingIO::getInstance();

		$boardTitle = $_POST['new-board-title'] ?? $this->globalHTML->error("Board title wasn't set!");
		$boardSubTitle = $_POST['new-board-sub-title'] ?? '';
		$boardIdentifier = $_POST['new-board-identifier'] ?? '';
		$boardListed = isset($_POST['new-board-listed']) ? 1 : 0;
		$boardPath = $_POST['new-board-path'] ?? $this->globalHTML->error("Board path wasn't set!");

		$fullBoardPath = $boardPath . $boardIdentifier . '/';
		$mockConfig = getTemplateConfigArray();
		$backendDirectory = getBackendDir();
		$cdnDir = $this->config['CDN_DIR'] . $boardIdentifier . '/';

		$createdPaths = [];

		try {
			// create full board path
			$createdPaths[] = createDirectoryWithErrorHandle($fullBoardPath, $this->globalHTML);

			// create image storage paths
			$imgDir = $this->config['USE_CDN'] ? $cdnDir . $mockConfig['IMG_DIR'] : $fullBoardPath . $mockConfig['IMG_DIR'];
			$thumbDir = $this->config['USE_CDN'] ? $cdnDir . $mockConfig['THUMB_DIR'] : $fullBoardPath . $mockConfig['THUMB_DIR'];
			$createdPaths[] = createDirectoryWithErrorHandle($imgDir, $this->globalHTML);
			$createdPaths[] = createDirectoryWithErrorHandle($thumbDir, $this->globalHTML);

			// create koko.php in the directory
			$requireString = "\"$backendDirectory{$this->config['PHP_SELF']}\"";
			createFileAndWriteText($fullBoardPath, $mockConfig['PHP_SELF'], "<?php require_once {$requireString}; ?>");

			// create board-storage directory
			$boardStorageDirectoryName = 'storage-' . $this->boardIO->getNextBoardUID();
			$dataDir = getBoardStoragesDir() . $boardStorageDirectoryName;
			$createdPaths[] = createDirectoryWithErrorHandle($dataDir, $this->globalHTML);

			// generate config file
			$boardConfigName = generateNewBoardConfigFile();
			
			// add board to database
			$this->boardIO->addNewBoard($boardIdentifier, $boardTitle, $boardSubTitle, $boardListed, $boardConfigName, $boardStorageDirectoryName);

			// initialize and create boardUID.ini
			$newBoardUID = $this->boardIO->getLastBoardUID();
			createFileAndWriteText($fullBoardPath, 'boardUID.ini', "board_uid = $newBoardUID");

			// add the boards' physical path to path cache table
			$boardPathCachingIO->addNewCachedBoardPath($newBoardUID, $fullBoardPath);


			$newBoardFromDatabase = $this->boardIO->getBoardByUID($newBoardUID);
			$newBoardFromDatabase->rebuildBoard();

		} catch (Exception $e) {
			rollbackCreatedPaths($createdPaths);
			$this->globalHTML->error($e->getMessage());
		}
	}
}
