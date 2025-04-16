<?php
class mod_globalmsg extends moduleHelper {
	private $mypage, $globalMessageFile;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->globalMessageFile = $this->config['ModuleSettings']['GLOBAL_TXT'];
		if(!file_exists($this->globalMessageFile)) touch($this->globalMessageFile);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.': Admin global message manager';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}

	private function getCurrentGlobalMsg() {
		if (file_exists($this->globalMessageFile)) {
			return file_get_contents($this->globalMessageFile);
		}
		return '';
	}

	private function writeToGlobalMsg($message) {
		$globalHTML = new globalHTML($this->board);
		if (!is_writable($this->globalMessageFile)) {
			$globalHTML->error('Error: Unable to write to the file.');
		}
		file_put_contents($this->globalMessageFile, $message);
	}

	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		if ($level < $this->config['AuthLevels']['CAN_EDIT_GLOBAL_MESSAGE'] || $pageId != 'admin') return;
		$link .= '<li class="adminNavLink"><a title="Manage the global warning/message that will appear across all boards" href="'.$this->mypage.'">Manage global message</a></li>';
	}

	public function autoHookGlobalMessage(&$msg) {
		$msg .= $this->getCurrentGlobalMsg() ?? '';
	}

	public function ModulePage() {
		$softErrorHandler = new softErrorHandler($this->board);
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_EDIT_GLOBAL_MESSAGE']);
	
		$action = $_GET['action'] ?? '';

		if ($action === 'setmessage' && isset($_POST['submit'])) {
			$message = $_POST['content'] ?? '';
			$this->writeToGlobalMsg($message);
			rebuildAllBoards();
		}

		$templateValues = [
			'{$CURRENT_GLOBAL_MESSAGE}' => $this->getCurrentGlobalMsg(),
			'{$MODULE_URL}' => $this->mypage
		];

		$globalMessagePageHtml = $this->adminPageRenderer->ParseBlock('GLOBALMSG_PAGE', $templateValues);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $globalMessagePageHtml], true);
	}
}

