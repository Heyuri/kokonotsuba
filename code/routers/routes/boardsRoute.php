<?php

// boards route - display information on boards for admin

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class boardsRoute {
	private readonly array $config;
	private readonly staffAccountFromSession $staffSession;
	private readonly softErrorHandler $softErrorHandler;
	private readonly globalHTML $globalHTML;
	private readonly templateEngine $adminTemplateEngine;
	private readonly pageRenderer $adminPageRenderer;
	private readonly boardIO $boardIO;
	private readonly board $board;

	public function __construct(
		array $config,
		staffAccountFromSession $staffSession,
		softErrorHandler $softErrorHandler,
		globalHTML $globalHTML,
		templateEngine $adminTemplateEngine,
		pageRenderer $adminPageRenderer,
		boardIO $boardIO,
		board $board
	) {
		$this->config = $config;
		$this->staffSession = $staffSession;
		$this->softErrorHandler = $softErrorHandler;
		$this->globalHTML = $globalHTML;
		$this->adminTemplateEngine = $adminTemplateEngine;
		$this->adminPageRenderer = $adminPageRenderer;
		$this->boardIO = $boardIO;
		$this->board = $board;
	}

	public function drawBoardPage(): void {
		$authRoleLevel = $this->staffSession->getRoleLevel();

		$this->softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_ADMIN);

		// draw board tablke
		$boardTableList = $this->globalHTML->drawBoardTable();
		
		// set template values
		$templateValues = [
			'{$BOARD_LIST}' => $boardTableList,
			'{$CREATE_BOARD}' =>  '',
			'{$IMPORT_BOARD}' =>  ''];

		// admin forms for importing/creating boards
		if($authRoleLevel === \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN) {
			$defaultPath = dirname(getcwd()) . '/';
			// create board form
			$templateValues['{$CREATE_BOARD}'] = $this->adminTemplateEngine->ParseBlock('CREATE_BOARD', [
				'{$DEFAULT_CDN_DIR}' => $this->config['CDN_DIR'],
				'{$DEFAULT_CDN_URL}' => $this->config['CDN_URL'],
				'{$DEFAULT_ROOT_URL}' => $this->board->getBoardRootURL(),
				'{$DEFAULT_PATH}' => $defaultPath
			]);

			// import board form
			$templateValues['{$IMPORT_BOARD}'] = $this->adminTemplateEngine->ParseBlock('IMPORT_BOARD', [
				'{$DEFAULT_ROOT_URL}' => $this->board->getBoardRootURL(),
				'{$DEFAULT_PATH}' => $defaultPath
			]);

		}

		if (isset($_GET['view'])) {
			$id = $_GET['view'] ?? null;
			if (!$id) {
				throw new Exception("Board UID from GET was not set or invalid. " . __CLASS__ . ' ' . __LINE__);
			}

			$board = $this->boardIO->getBoardByUID($id);

			$boardUID = $board->getBoardUID() ?? '';
			$boardIdentifier = $board->getBoardIdentifier() ?? '';
			$boardTitle = $board->getBoardTitle() ?? '';
			$boardSubtitle = $board->getBoardSubTitle() ?? '';
			$boardURL = $board->getBoardURL() ?? '';
			$boardListed = $board->getBoardListed() ?? '';
			$boardConfig = $board->getConfigFileName() ?? '';
			$boardStorageDirectoryName = $board->getBoardStorageDirName() ?? '';
			$boardDate = $board->getDateAdded() ?? '';

			$templateValues['{$BOARD_UID}'] = $boardUID;
			$templateValues['{$BOARD_IDENTIFIER}'] = $boardIdentifier;
			$templateValues['{$BOARD_TITLE}'] = $boardTitle;
			$templateValues['{$BOARD_SUB_TITLE}'] = $boardSubtitle;
			$templateValues['{$BOARD_URL}'] = $boardURL;
			$templateValues['{$BOARD_IS_LISTED}'] = $boardListed ? 'True' : 'False';
			$templateValues['{$BOARD_DATE_ADDED}'] = $boardDate;
			$templateValues['{$BOARD_CONFIG_FILE}'] = $boardConfig;
			$templateValues['{$CHECKED}'] = $boardListed ? 'checked' : '';
			$templateValues['{$BOARD_STORAGE_DIR}'] = $boardStorageDirectoryName;
			$templateValues['{$EDIT_BOARD_HTML}'] = $this->adminTemplateEngine->ParseBlock('EDIT_BOARD', $templateValues);
			
			// prevent showing editing a reserved board
			if(!$boardUID === GLOBAL_BOARD_UID) {
				$templateValues['{$EDIT_BOARD_HTML}'] = "<p>This board cannot be edited.</p>"; 
			}

			$viewBoardHtml = $this->adminPageRenderer->ParseBlock('VIEW_BOARD', $templateValues);
			echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $viewBoardHtml], true);
			return;
		}

		$boardPageHtml = $this->adminTemplateEngine->ParseBlock('BOARD_PAGE', $templateValues);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $boardPageHtml], true);
	}
}
