<?php

class boardService {
	public function __construct(
		private readonly boardRepository $boardRepository,
		private readonly boardDiContainer $boardDiContainer,
		private readonly boardPathService $boardPathService
	) {}

	public function editBoard(board $board, array $inputFields): void {
		if (empty($inputFields)) {
			throw new Exception("Fields left empty.");
		}

		$fields = [];

		if (!empty($inputFields['board_identifier'])) {
			$fields['board_identifier'] = $inputFields['board_identifier'];
		}
		if (!empty($inputFields['board_title'])) {
			$fields['board_title'] = $inputFields['board_title'];
		}
		if (!empty($inputFields['board_sub_title'])) {
			$fields['board_sub_title'] = $inputFields['board_sub_title'];
		}
		if (!empty($inputFields['config_name'])) {
			$fields['config_name'] = $inputFields['config_name'];
		}
		if (!empty($inputFields['storage_directory_name'])) {
			$fields['storage_directory_name'] = $inputFields['storage_directory_name'];
		}
		if (isset($inputFields['listed'])) {
			$fields['listed'] = $inputFields['listed'] ? 1 : 0;
		}

		if (empty($fields)) {
			throw new Exception("No valid fields provided to update.");
		}

		$this->boardRepository->updateBoardByUID($board->getBoardUID(), $fields);
	}

	public function deleteBoard(int $boardUid): void {
		// get the full board data
		$board = $this->getBoard($boardUid);
		
		// get board file properties
		$boardPath = $board->getBoardCachedPath();
		$boardStoragePath = $board->getBoardStoragePath();
		$boardConfigPath = $board->getFullConfigPath();

		// Delete board from the database
		$this->boardRepository->deleteBoardByUID($boardUid);

		// delete files
		safeRmdir($boardPath);
		safeRmDir($boardStoragePath);
		unlink($boardConfigPath);
	}

    public function createBoard(array $inputFields, array $templateBoardConfig, string $backendDirectory, \Kokonotsuba\Root\Constants\userRole $requiredRoleLevel, \Kokonotsuba\Root\Constants\userRole $userRoleLevel): ?board {
        // Check for missing required fields
        if (empty($inputFields['boardIdentifier']) || empty($inputFields['boardTitle'])) {
            throw new Exception("Required fields missing.");
        }

        // Authenticate user role
        if($userRoleLevel->isLessThan($requiredRoleLevel)) {
            throw new Exception("User not authorized for board creation.");
        }

        // Sanitize input fields
        $boardIdentifier = $this->sanitizeBoardIdentifier($inputFields['boardIdentifier']);
        $boardPath = $this->sanitizeBoardPath($inputFields['boardPath']);
        $boardTitle = $this->sanitizeTextField($inputFields['boardTitle']);
        $boardSubTitle = isset($inputFields['boardSubTitle']) ? $this->sanitizeTextField($inputFields['boardSubTitle']) : '';
        $boardListed = isset($inputFields['boardListed']) ? (int) $inputFields['boardListed'] : 0;

        // Ensure valid `boardListed` (0 or 1)
        if (!in_array($boardListed, [0, 1], true)) {
            throw new Exception("Invalid value for 'boardListed'. Expected 0 or 1.");
        }

        // Get next board UID
        $nextBoardUid = $this->boardRepository->getNextBoardUID();
        
		 // Define directory paths
        $boardCdnDir = $templateBoardConfig['CDN_DIR']  .  '/'  .  $nextBoardUid . '/';
        $fullBoardPath = $boardPath  .  '/'  .  $boardIdentifier  . '/';
        
        $createdPaths = [];

        try {
            // Create necessary directories
            $createdPaths[] = createDirectory($fullBoardPath);
            $imgDir = $templateBoardConfig['USE_CDN'] ? $boardCdnDir . $templateBoardConfig['IMG_DIR'] : $fullBoardPath . $templateBoardConfig['IMG_DIR'];
            $thumbDir = $templateBoardConfig['USE_CDN'] ? $boardCdnDir . $templateBoardConfig['THUMB_DIR'] : $fullBoardPath . $templateBoardConfig['THUMB_DIR'];
            $createdPaths[] = createDirectory($imgDir);
            $createdPaths[] = createDirectory($thumbDir);

            // Create the PHP file
            $requireString = "\"$backendDirectory{$templateBoardConfig['LIVE_INDEX_FILE']}\"";
            createFileAndWriteText($fullBoardPath, $templateBoardConfig['LIVE_INDEX_FILE'], "<?php require_once {$requireString}; ?>");

            // Create board storage directory
            $boardStorageDirectoryName = 'storage-' . $nextBoardUid;
            $dataDir = getBoardStoragesDir() . $boardStorageDirectoryName;
            $createdPaths[] = createDirectory($dataDir);

            // Generate config file for the board
            $boardConfigName = generateNewBoardConfigFile($nextBoardUid);

            // Add board to the database
            $this->boardRepository->addNewBoard($boardIdentifier, $boardTitle, $boardSubTitle, $boardListed, $boardConfigName, $boardStorageDirectoryName);

            // Initialize boardUID.ini
            $newBoardUID = $this->boardRepository->getLastBoardUID();
            createFileAndWriteText($fullBoardPath, 'boardUID.ini', "board_uid = $newBoardUID");

            // Add the board's physical path to the path cache table
            $this->boardPathService->addNew($newBoardUID, $fullBoardPath);

            // Rebuild the new board from the database
            $newBoardFromDatabase = $this->getBoard($newBoardUID);
            $newBoardFromDatabase->rebuildBoard();

            return $newBoardFromDatabase;
        } catch (Exception $e) {
            // Handle rollback and cleanup
            rollbackCreatedPaths($createdPaths);
            deleteCreatedBoardConfig($boardConfigName);
            throw $e;
        }
    }


	    // Sanitize board identifier (alphanumeric, dashes, underscores)
    private function sanitizeBoardIdentifier($input) {
        $input = trim($input);
        // Only allow alphanumeric, underscores, and dashes
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $input)) {
            throw new Exception("Invalid board identifier.");
        }
        return $input;
    }

    // Sanitize board path (ensure it's a valid directory path)
    private function sanitizeBoardPath($input) {
        $input = rtrim(trim($input), '/');  // Remove any trailing slashes
        if (!is_dir($input)) {
            throw new Exception("Invalid board path.");
        }
        return $input;
    }

    // Sanitize text fields (remove unwanted HTML tags)
    private function sanitizeTextField($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');  // Convert special chars to HTML entities
    }

	public function getBoardsFromUIDs(array $boardUIDs): ?array {
		// Ensure $uidList is an array
		if (!is_array($boardUIDs)) {
			$boardUIDs = [$boardUIDs];
		}
	
		// Sanitize each value to be an integer
		$boardUIDs = array_map('intval', $boardUIDs);

		// Create a string of UIDs for the IN clause, separated by commas
		$boardUIDs = implode(', ', $boardUIDs);

		$boardDataList = $this->boardRepository->getBoardsFromUIDs($boardUIDs);

		$boards = $this->assembleBoardsFromArray($boardDataList);

		return $boards;
	}

	
	public function getAllRegularBoards(): ?array {
		$allBoardsData = $this->boardRepository->getAllRegularBoards();

		$boards = $this->assembleBoardsFromArray($allBoardsData);

		return $boards;
	}

	public function getAllListedBoards(): ?array {
		$allBoardsData = $this->boardRepository->getAllListedBoards();

		$boards = $this->assembleBoardsFromArray($allBoardsData);

		return $boards;
	}

	public function getAllBoards(): ?array {
		$allBoardsData = $this->boardRepository->getAllBoards();

		$boards = $this->assembleBoardsFromArray($allBoardsData);

		return $boards;
	}

	private function assembleBoardsFromArray(array $boardDataList): ?array {
		$boards = [];

		foreach($boardDataList as $boardData) {
			$boards[] = $this->assembleBoard($boardData);
		}

		return $boards;
	}

	public function getBoard(int $boardUid): board {
		$boardData = $this->boardRepository->getBoardByUID($boardUid);

		$board = $this->assembleBoard($boardData);

		// If it's not a proper instance of a board - throw exception
		if (!$board instanceof board) {
			throw new Exception("Board could not be assembled.");
		}

		return $board;
	}

	private function assembleBoard(boardData $boardData): ?board {
		$board = new board($this->boardDiContainer->boardPostNumbers, $boardData, $this->boardDiContainer->boardPathService);

		// initialize dependencies for setter inject
		$templateEngine = $this->initializeTemplateEngine($board);
		
		$liveIndexFile = $board->getConfigValue('LIVE_INDEX_FILE');

		$moduleEngineContext = new moduleEngineContext($board->loadBoardConfig(), 
			$liveIndexFile, $board->getConfigValue('ModuleList'), 
			$this->boardDiContainer->postRepository, 
			$this->boardDiContainer->postService, 
			$this->boardDiContainer->threadRepository, 
			$this->boardDiContainer->threadService, 
			$this->boardDiContainer->postSearchService,
			$this->boardDiContainer->quoteLinkService,
			$this,
			$this->boardDiContainer->attachmentService,
			$this->boardDiContainer->actionLoggerService,
			$this->boardDiContainer->postRedirectService,
			$this->boardDiContainer->transactionManager,
			$templateEngine, 
			$board);
			
		$moduleEngine = new moduleEngine($moduleEngineContext);
		
		// setter inject certain dependencies
		$board->setTemplateEngine($templateEngine);
		$board->setModuleEngine($moduleEngine);

		$softErrorHandler	= new softErrorHandler($board->getBoardHead('Error!'), $board->getBoardFooter(), $board->getConfigValue('STATIC_INDEX_FILE'), $templateEngine);

		$boardRebuilder = new boardRebuilder($board,
			$moduleEngine,
			$templateEngine,
			$this->boardDiContainer->postService,
			$this->boardDiContainer->actionLoggerService,
			$this->boardDiContainer->threadRepository,
			$this->boardDiContainer->threadService,
			$softErrorHandler,
			$this->boardDiContainer->quoteLinkService);

		$board->setBoardRebuilder($boardRebuilder);

		// It's all set up now. So we can return it 
		return $board;
	}

	private function initializeTemplateEngine(board $board): ?templateEngine {
		$config = $board->loadBoardConfig();

		$templateFile = null;
		$isReply = !empty($_GET['res']);

		$templateKey = $isReply ? 'REPLY_TEMPLATE_FILE' : 'TEMPLATE_FILE';
		$templateFileName = $board->getConfigValue($templateKey, 'TEMPLATE_FILE');

		if ($templateFileName !== null) {
			$templateFile = getBackendDir() . 'templates/' . $templateFileName;
		}

		if ($templateFile === null) {
			return null;
		}

		$dependencies = [
			'config'	=> $config,
			'boardData'	=> [
				'title'		=> $board->getBoardTitle(),
				'subtitle'	=> $board->getBoardSubTitle()
			]
		];

		return new templateEngine($templateFile, $dependencies);
	}

	public function getBoardFromBootstrapFile(string $bootstrapFile): board {
		$boardUIDIni = parse_ini_file($bootstrapFile, true);

		if (!$boardUIDIni) {
			die("Error: Failed to parse '$bootstrapFile'. Please check that the file exists and has valid INI syntax.");
		}

		if (!isset($boardUIDIni['board_uid']) || empty($boardUIDIni['board_uid'])) {
			die("Error: 'board_uid' is not set or is empty in 'boardUID.ini'. Please provide a valid board UID.");
		}

		$boardUID = $boardUIDIni['board_uid'];
		$board = $this->getBoard($boardUID);

		if (!$board) {
			die("Error: Board with UID '$boardUID' not found in the database. Contact the administrator if you believe this is incorrect.");
		}

		return $board;
	}

}
