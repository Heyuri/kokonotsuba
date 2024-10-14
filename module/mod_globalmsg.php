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
		if (!is_writable($this->globalMessageFile)) {
			error('Error: Unable to write to the file.');
		}
		file_put_contents($this->globalMessageFile, $message);
	}

	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		if ($AccountIO->valid() < $this->config['roles']['LEV_ADMIN'] || $pageId != 'admin') return;
		$link .= '[<a title="Manage the global warning/message that will appear across all boards" href="'.$this->mypage.'">Manage Global Message</a>] ';
	}

	public function autoHookGlobalMessage(&$msg) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		$msg .= $this->getCurrentGlobalMsg() ?? '';
	}

	public function ModulePage() {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		if ($AccountIO->valid() < $this->config['roles']['LEV_ADMIN']) error("403 DENIED!");
	
	
		$pageHTML = '';
		$success = '';
		$action = isset($_GET['action']) ? $_GET['action'] : '';

		if ($action === 'setmessage' && isset($_POST['submit'])) {
			$message = isset($_POST['content']) ? $_POST['content'] : '';
			$result = $this->writeToGlobalMsg($message);
			updatelog();
			$success .= '<b class="good">Global message updated!</b>';
		}

		$currentMessage = $this->getCurrentGlobalMsg();
		
		$returnButton = '[<a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]';
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
		head($pageHTML);
		$pageHTML .= $returnButton.$adminGlobalMessageForm.$currentPreview.$success;
		foot($pageHTML);
		
		echo $pageHTML;
	}
}

