<?php
class mod_blotter extends moduleHelper {
	private $mypage;
	private $BLOTTER_PATH, $previewLimit = -1; // Path to blotter file

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->BLOTTER_PATH = $this->config['ModuleSettings']['BLOTTER_FILE'];
		if(!file_exists($this->BLOTTER_PATH)) touch($this->BLOTTER_PATH);
		
		$this->previewLimit = $this->config['ModuleSettings']['BLOTTER_PREVIEW_AMOUNT'];
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : Blotter';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}

	private function getBlotterFileData() {
		$data = [];
		if (file_exists($this->BLOTTER_PATH)) {
			$lines = file($this->BLOTTER_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				// Assuming each line in the file is formatted as COMMENT<>DATE
				list($date, $comment, $uid) = explode('<>', $line);
				$data[] = [
					'date' => $date,
					'comment' => $comment,
					'uid' => $uid ?? 0,
					];
			}
		}
		return $data;
	}

	private function drawBlotterTable() {
		$blotterData = $this->getBlotterFileData();

		usort($blotterData, function($a, $b) {
				return strtotime($b['date']) - strtotime($a['date']);
		});

		$rows = [];
		foreach ($blotterData as $entry) {
				$rows[] = [
						'{$DATE}' => $entry['date'],
						'{$COMMENT}' => $entry['comment'],
				];
		}

		$templateValues = [
				'{$ROWS}' => $rows,
				'{$EMPTY}' => empty($rows),
		];

		return $this->adminPageRenderer->ParseBlock('BLOTTER_PAGE', $templateValues);
	}



	private function deleteBlotterEntries($uidsToDelete) {
		$blotterData = $this->getBlotterFileData();
		if(!is_array($uidsToDelete)) $uidsToDelete = [$uidsToDelete];

		$updatedData = array_filter($blotterData, function($entry) use ($uidsToDelete) {
				return !in_array($entry['uid'], $uidsToDelete);
		});
		$blotterContent = '';
		foreach ($updatedData as $entry) {
				$blotterContent .= "{$entry['date']}<>{$entry['comment']}<>{$entry['uid']}\n"; // Ensure UID is stored as well
		}

			file_put_contents($this->BLOTTER_PATH, $blotterContent);
			$this->board->rebuildBoard();
	}

	private function prepareAdminBlotterPlaceHolders() {
		$blotterData = $this->getBlotterFileData();

		usort($blotterData, function($a, $b) {
			return strtotime($b['date']) - strtotime($a['date']);
		});

		$blotterPlaceholders = ['{$MODULE_URL}' => $this->mypage];
		foreach($blotterData as $blotterEntry) {
			$blotterPlaceholders['{$ROWS}'][] = [
				'{$DATE}' => $blotterEntry['date'],
				'{$COMMENT}' => $blotterEntry['comment'],
				'{$UID}' => $blotterEntry['uid'],
			];
		}
		return $blotterPlaceholders;
	}


	private function writeToBlotterFile($comment, $date, $uid) {
		$escapedComment = preg_replace('/<>/', '&lt;&gt;', $comment);
		$line = "{$date}<>{$escapedComment}<>{$uid}\n";
		
		file_put_contents($this->BLOTTER_PATH, $line, FILE_APPEND);
	}

	private function handleBlotterAddition() {
		$newText = strval($_POST['new_blot_txt']) ?? '';
		$newDate = date($this->config['ModuleSettings']['BLOTTER_DATE_FORMAT']) ?? '';
		$newUID = substr(bin2hex(random_bytes(10)), 0, 10);
		
		$this->writeToBlotterFile($newText, $newDate, $newUID);
		$this->board->rebuildBoard();//rebuild all pages so it takes effect immedietly
	}

	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		if ($level < $this->config['AuthLevels']['CAN_EDIT_BLOTTER']) return;
		
		$link.= '<li class="adminNavLink"><a href="'.$this->mypage.'">Manage blotter</a></li>';
	}

	public function autoHookBlotterPreview(&$html) {
		$blotterData = $this->getBlotterFileData();
		
		usort($blotterData, function($a, $b) {
			return strtotime($b['date']) - strtotime($a['date']);
		});

		$previewEntries = [];

		foreach ($blotterData as $i => $entry) {
				if ($i >= $this->previewLimit) break;
				$previewEntries[] = [
						'{$DATE}' => $entry['date'],
						'{$COMMENT}' => $entry['comment'],
				];
		}

		$templateValues = [
				'{$MODULE_URL}' => $this->mypage,
				'{$ENTRIES}' => $previewEntries,
				'{$EMPTY}' => empty($previewEntries),
		];

		$html .= $this->adminPageRenderer->ParseBlock('BLOTTER_PREVIEW', $templateValues);
	}


	public function ModulePage() {
		$staffSession = new staffAccountFromSession;
		
		$roleLevel = $staffSession->getRoleLevel();

		
		//If a regular user, draw blotter page
		if ($roleLevel < $this->config['AuthLevels']['CAN_EDIT_BLOTTER']) {
			$blotterTableHtml = $this->drawBlotterTable();

			echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $blotterTableHtml]);
			return;
		}
		
		// Admin panel to manage blotter
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && $roleLevel >= $this->config['AuthLevels']['CAN_EDIT_BLOTTER']) {
			if (!empty($_POST['new_blot_txt'])) {
				$this->handleBlotterAddition();
			}

			if (!empty($_POST['delete_submit']) && !empty($_POST['entrydelete'])) {
				$this->deleteBlotterEntries($_POST['entrydelete']);
			}
		}
		$templateValues = $this->prepareAdminBlotterPlaceHolders();
		$blotterAdminPageHtml = $this->adminPageRenderer->ParseBlock('BLOTTER_ADMIN_PAGE', $templateValues);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $blotterAdminPageHtml], true);
	}
}
?>