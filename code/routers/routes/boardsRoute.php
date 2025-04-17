<?php

// boards route - display information on boards for admin

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

		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_ADMIN']);

		$boardTableList = $this->globalHTML->drawBoardTable();
		$template_values = [
			'{$BOARD_LIST}' => $boardTableList,
			'{$CREATE_BOARD}' => ($authRoleLevel == $this->config['roles']['LEV_ADMIN']) ? $this->adminTemplateEngine->ParseBlock('CREATE_BOARD', [
				'{$DEFAULT_CDN_DIR}' => $this->config['CDN_DIR'],
				'{$DEFAULT_CDN_URL}' => $this->config['CDN_URL'],
				'{$DEFAULT_ROOT_URL}' => $this->board->getBoardRootURL(),
				'{$DEFAULT_PATH}' => dirname(getcwd()) . DIRECTORY_SEPARATOR
			]) : '',
		];

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

			$template_values['{$BOARD_UID}'] = $boardUID;
			$template_values['{$BOARD_IDENTIFIER}'] = $boardIdentifier;
			$template_values['{$BOARD_TITLE}'] = $boardTitle;
			$template_values['{$BOARD_SUB_TITLE}'] = $boardSubtitle;
			$template_values['{$BOARD_URL}'] = $boardURL;
			$template_values['{$BOARD_IS_LISTED}'] = $boardListed ? 'True' : 'False';
			$template_values['{$BOARD_DATE_ADDED}'] = $boardDate;
			$template_values['{$BOARD_CONFIG_FILE}'] = $boardConfig;
			$template_values['{$CHECKED}'] = $boardListed ? 'checked' : '';
			$template_values['{$BOARD_STORAGE_DIR}'] = $boardStorageDirectoryName;
			$template_values['{$EDIT_BOARD_HTML}'] = $this->adminTemplateEngine->ParseBlock('EDIT_BOARD', $template_values);

			$viewBoardHtml = $this->adminPageRenderer->ParseBlock('VIEW_BOARD', $template_values);
			echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $viewBoardHtml], true);
			return;
		}

		$boardPageHtml = $this->adminTemplateEngine->ParseBlock('BOARD_PAGE', $template_values);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $boardPageHtml], true);
	}
}
