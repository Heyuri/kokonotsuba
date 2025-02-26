<?php
class mod_globalmsg extends ModuleHelper {
	private $mypage, $globalMessageFile;

	public function __construct($PMS) {
		parent::__construct($PMS);
		
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
		$staffSession = new staffAccountFromSession;
		
		if ($staffSession->getRoleLevel() < $this->config['roles']['LEV_ADMIN'] || $pageId != 'admin') return;
		$link .= '[<a title="Manage the global warning/message that will appear across all boards" href="'.$this->mypage.'">Manage global message</a>] ';
	}

	public function autoHookGlobalMessage(&$msg) {
		$msg .= $this->getCurrentGlobalMsg() ?? '';
	}

	public function ModulePage() {
		$globalHTML = new globalHTML($this->board);
		$staffSession = new staffAccountFromSession;
		$softErrorHandler = new softErrorHandler($this->board);
		$softErrorHandler->handleAuthError($this->config['roles']['LEV_ADMIN']);
	
		$pageHTML = '';
		$success = '';
		$action = $_GET['action'] ?? '';

		if ($action === 'setmessage' && isset($_POST['submit'])) {
			
			$message = isset($_POST['content']) ? $_POST['content'] : '';
			$result = $this->writeToGlobalMsg($message);
			$this->board->rebuildBoard();
		}

		$currentMessage = $this->getCurrentGlobalMsg();
		
		$returnButton = $globalHTML->generateAdminLinkButtons();
		
		$adminGlobalMessageForm = '
		<fieldset class="adminfieldset"> <legend>Edit global message</legend>
			<form action="'.$this->mypage.'&action=setmessage" method="post">
				<table cellpadding="1" cellspacing="1" id="postform_tbl" style="margin: 0px auto; text-align: left;">
					<tbody>
						<tr>
							<td class="postblock"><b>Global Message (Raw HTML)</b></td>
							<td><textarea cols="150" rows="5" name="content">'.$currentMessage.'</textarea></td>
						</tr>
						<tr>
							<td colspan="2" align="right"><input type="submit" name="submit" value="Submit"></td>
						</tr>
					</tbody>
				</table>
			</form>
		</fieldset>';
		
		$currentPreview = '
		<fieldset class="adminfieldset"><legend>Current global message</legend>
				<div id="globalMessagePreviewCurrent">
					'.$currentMessage.'
				</div>
		</fieldset>
		';
		//assemble page output
		$globalHTML->head($pageHTML);
		$pageHTML .= $returnButton;
		$globalHTML->drawAdminTheading($pageHTML, $staffSession);
		$pageHTML .= $adminGlobalMessageForm.$currentPreview;
		$globalHTML->foot($pageHTML);
		
		echo $pageHTML;
	}
}

