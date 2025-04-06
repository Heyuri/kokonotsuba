<?php
class mod_rebuild extends moduleHelper {
	private $mypage;

	public function __construct($moduleEngine) {
		parent::__construct($moduleEngine);
		
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return 'mod_rebuild : Cross-board html rebuilding';
	}

	public function getModuleVersionInfo(){
		return 'Kokontsuba 2025';
	}

	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		$link .= '[<a href="' . $this->mypage . '">Manage rebuild</a>] ';
	}

	private function rebuildBoards(array $boards) {
		//go through each board and rebuild its html
		foreach($boards as $board) {
			$board->rebuildBoard(0, -1, false, -1, true);
		}
	}

	public function ModulePage() {
		$softErrorHandler = new softErrorHandler($this->board);
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_MANAGE_REBUILD']);

		$boardIO = boardIO::getInstance();
		$globalHTML = new globalHTML($this->board);
		
		$formSubmit = $_POST['formSubmit'] ?? false;
		if($formSubmit) {
			$boardsUIDsToRebuild = $_POST['rebuildBoardUIDs'] ?? false;
			$boardsToRebuild = $boardIO->getBoardsFromUIDs($boardsUIDsToRebuild);
			if($boardsToRebuild) $this->rebuildBoards($boardsToRebuild);

			redirect($this->mypage);
			/* Add more things here. TODO: Add thread cache rebuilding when those are added */
		} else {
			$staffSession = new staffAccountFromSession;
			$templateValues = [
				'{$REBUILD_CHECK_LIST}' => $globalHTML->generateRebuildListCheckboxHTML($this->moduleBoardList),
				'{$MODULE_URL}' => $this->mypage];

			$htmlOutput = '';

			$globalHTML->head($htmlOutput);
			$htmlOutput .= $globalHTML->generateAdminLinkButtons();
			$htmlOutput .= $globalHTML->drawAdminTheading($thead, $staffSession);
			$htmlOutput .= $this->adminTemplateEngine->ParseBLock('ADMIN_REBUILD_PAGE', $templateValues);
			$globalHTML->foot($htmlOutput);

			echo $htmlOutput;
		}
	}
}