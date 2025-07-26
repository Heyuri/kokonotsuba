<?php

namespace Kokonotsuba\Modules\blotter;

include_once __DIR__ . '/blotterLibrary.php';

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	private readonly string $myPage;
	private readonly string $BLOTTER_PATH;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_EDIT_BLOTTER');
	}

	public function getName(): string {
		return 'Blotter manager tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->BLOTTER_PATH = $this->getConfig('ModuleSettings.BLOTTER_FILE');
		
		if(!file_exists($this->BLOTTER_PATH)) {
			touch($this->BLOTTER_PATH);
		}

		$this->myPage = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);
	}

	public function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a href="' . $this->myPage . '">Manage blotter</a></li>';
	}

	public function ModulePage(): void {
		// Admin panel to manage blotter
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if (!empty($_POST['new_blot_txt'])) {
				$this->handleBlotterAddition();

				rebuildAllBoards(); //rebuild all pages so it takes effect immedietly
				
				redirect($this->getModulePageURL([], false));
			}
			if (!empty($_POST['delete_submit']) && !empty($_POST['entrydelete'])) {
				$this->deleteBlotterEntries($_POST['entrydelete']);
				
				rebuildAllBoards(); //rebuild all pages so it takes effect immedietly

				redirect($this->getModulePageURL([], false));
			}
		}

		//If the user has the correct role -,draw blotter page
		$templateValues = $this->prepareAdminBlotterPlaceHolders();
		
		$blotterAdminPageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('BLOTTER_ADMIN_PAGE', $templateValues);
		
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $blotterAdminPageHtml], true);
	}


	private function deleteBlotterEntries($uidsToDelete) {
		$blotterData = getBlotterFileData($this->BLOTTER_PATH);
		
		if(!is_array($uidsToDelete)) {
			$uidsToDelete = [$uidsToDelete];
		}

		$updatedData = array_filter($blotterData, function($entry) use ($uidsToDelete) {
				return !in_array($entry['uid'], $uidsToDelete);
		});

		$blotterContent = '';

		foreach ($updatedData as $entry) {
				$blotterContent .= "{$entry['date']}<>{$entry['comment']}<>{$entry['uid']}\n"; // Ensure UID is stored as well
		}

		file_put_contents($this->BLOTTER_PATH, $blotterContent);
	}

	private function prepareAdminBlotterPlaceHolders() {
		$blotterData = getBlotterFileData($this->BLOTTER_PATH);

		$blotterPlaceholders = ['{$MODULE_URL}' => $this->myPage];
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
		$newDate = date($this->getConfig('ModuleSettings.BLOTTER_DATE_FORMAT')) ?? '';
		$newUID = substr(bin2hex(random_bytes(10)), 0, 10);
		
		$this->writeToBlotterFile($newText, $newDate, $newUID);
	}

}