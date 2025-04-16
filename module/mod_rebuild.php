<?php
class mod_rebuild extends moduleHelper {
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);
		
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return 'mod_rebuild : Cross-board html rebuilding';
	}

	public function getModuleVersionInfo(){
		return 'Kokontsuba 2025';
	}

	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		$link .= '<li class="adminNavLink"><a href="' . $this->mypage . '">Manage rebuild</a></li>';
	}

	public function ModulePage() {
		$softErrorHandler = new softErrorHandler($this->board);
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_MANAGE_REBUILD']);

		$globalHTML = new globalHTML($this->board);
		
		$formSubmit = $_POST['formSubmit'] ?? false;
		if($formSubmit) {
			$boardsUIDsToRebuild = $_POST['rebuildBoardUIDs'] ?? false;
			rebuildBoardsByUIDs($boardsUIDsToRebuild);

			redirect($this->mypage);
			/* Add more things here. TODO: Add thread cache rebuilding when those are added */
		} else {
			$templateValues = [
				'{$REBUILD_CHECK_LIST}' => $globalHTML->generateRebuildListCheckboxHTML($this->moduleBoardList),
				'{$MODULE_URL}' => $this->mypage];


			$adminRebuildPage = $this->adminPageRenderer->ParseBlock('ADMIN_REBUILD_PAGE', $templateValues);
			echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminRebuildPage], true);
		}
	}
}