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
use Kokonotsuba\config\configService;
use Kokonotsuba\request\request;
use Puchiko\background\BackgroundTaskDispatcher;
use function Puchiko\request\redirect;
use function Puchiko\json\sendJsonResponse;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\logError;
use function Kokonotsuba\libraries\requirePostWithCsrf;
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
		private request $request,
		private configService $configService
	) {}


	// handle actions
	public function handleBoardRequests(): void {
		$this->softErrorHandler->handleAuthError(userRole::LEV_ADMIN);

		// All board actions are state-changing POST submissions - require a valid CSRF token.
		requirePostWithCsrf($this->request);

		// edit a board
		if(!empty($this->request->getParameter('edit-board', 'POST'))) {
			$this->editBoardFromRequest();
		}

		// reset a board's configuration back to the defaults. Checked before the save below: the
		// config form posts its saveBoardConfig field whichever of its two buttons was clicked.
		if(!empty($this->request->getParameter('resetBoardConfig', 'POST'))) {
			$this->resetBoardConfigFromRequest();
		}

		// save a board's configuration overrides
		if(!empty($this->request->getParameter('saveBoardConfig', 'POST'))) {
			$this->saveBoardConfigFromRequest();
		}

		// create a new board
		if(!empty($this->request->getParameter('new-board', 'POST'))) {
			$this->createNewBoardFromRequest();
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
				'storage_directory_name' => $this->request->getParameter('edit-board-storage-dir', 'POST', false),
				'listed' => $this->request->getParameter('edit-board-listed', 'POST', false),
				// Always sent by the form, and an empty value legitimately clears the subdomain.
				'subdomain' => $this->request->getParameter('edit-board-subdomain', 'POST', '')
			];

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

	// handle saving a board's configuration overrides
	private function saveBoardConfigFromRequest(): void {
		$boardUid = intval($this->request->getParameter('saveBoardConfig', 'POST'));

		if (!$boardUid) {
			throw new \InvalidArgumentException("Board UID for config save cannot be empty.");
		}

		if ($boardUid === GLOBAL_BOARD_UID) {
			throw new \InvalidArgumentException("Cannot edit the reserved board's configuration.");
		}

		// Ensure the board exists before writing config.
		$board = $this->boardService->getBoard($boardUid);
		if (!$board) {
			throw new BoardException("Board not found.");
		}

		$submitted = $this->request->getParameter('config', 'POST', []);
		if (!is_array($submitted)) {
			$submitted = [];
		}

		$isAjax = $this->request->isAjax();

		try {
			// Persist only values that differ from the schema defaults.
			$this->configService->saveOverrides($boardUid, $submitted);
		} catch (Exception $e) {
			if ($isAjax) {
				sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
			}

			http_response_code(400);
			echo "Error saving configuration: " . $e->getMessage();
			return;
		}

		// Regenerate the board's static pages using the freshly-resolved config.
		$this->queueBoardRebuild($boardUid);

		// The editor saves over AJAX so the admin keeps their scroll position; without JS the same
		// POST falls through to the redirect below.
		if ($isAjax) {
			sendJsonResponse([
				'success'    => true,
				'message'    => _T('config_saved'),
				'overridden' => array_keys($this->configService->getOverrides($boardUid)),
			]);
		}

		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards&view=' . $boardUid . '&rebuild=queued');
	}

	// handle resetting a board's configuration - deletes its stored overrides so every setting
	// falls back to the schema default
	private function resetBoardConfigFromRequest(): void {
		$boardUid = intval($this->request->getParameter('resetBoardConfig', 'POST'));

		if (!$boardUid) {
			throw new \InvalidArgumentException("Board UID for config reset cannot be empty.");
		}

		if ($boardUid === GLOBAL_BOARD_UID) {
			throw new \InvalidArgumentException("Cannot edit the reserved board's configuration.");
		}

		$board = $this->boardService->getBoard($boardUid);
		if (!$board) {
			throw new BoardException("Board not found.");
		}

		try {
			$this->configService->resetOverrides($boardUid);
		} catch (Exception $e) {
			http_response_code(400);
			echo "Error resetting configuration: " . $e->getMessage();
			return;
		}

		// Regenerate the board's static pages using the freshly-resolved config.
		$this->queueBoardRebuild($boardUid);

		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=boards&view=' . $boardUid . '&rebuild=queued');
	}

	/**
	 * Hand the board's rebuild to the detached 'rebuild_boards' task rather than doing it inside
	 * this request. The config is already committed by this point, so a rebuild that fails to
	 * dispatch is logged and left for the Rebuild page; it never fails the save.
	 */
	private function queueBoardRebuild(int $boardUid): void {
		try {
			BackgroundTaskDispatcher::dispatch('rebuild_boards', ['boardUIDs' => [$boardUid]]);
		} catch (\Throwable $e) {
			logError('[boardConfig] rebuild dispatch failed: ' . $e->getMessage());
		}
	}

	// handle board creation
	private function createNewBoardFromRequest() {
		// Get board information from the request
		$boardTitle = $this->request->getParameter('new-board-title', 'POST') ?? throw new BoardException("Board title wasn't set!");
		$boardSubTitle = $this->request->getParameter('new-board-sub-title', 'POST', '');
		$boardIdentifier = $this->request->getParameter('new-board-identifier', 'POST', '');
		$boardListed = !empty($this->request->getParameter('new-board-listed', 'POST')) ? 1 : 0;
		$boardPath = $this->request->getParameter('new-board-path', 'POST') ?? throw new BoardException("Board path wasn't set!");
		$boardSubdomain = $this->request->getParameter('new-board-subdomain', 'POST', '');

		// Create an instance of the BoardCreator helper class
		$boardCreator = new boardCreator($this->boardService);

		// Call the createNewBoard method in the BoardCreator class
		$boardCreator->createNewBoard($boardTitle, $boardSubTitle, $boardIdentifier, $boardListed, $boardPath, getRoleLevelFromSession(), $boardSubdomain);
	}

}