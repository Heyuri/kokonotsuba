<?php

namespace Kokonotsuba\Modules\blotter;

require_once __DIR__ . '/blotterEntry.php';
require_once __DIR__ . '/blotterRepository.php';
require_once __DIR__ . '/blotterService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\rebuildAllBoards;
use function Puchiko\request\isPostRequest;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	private blotterService $blotterService;
	private readonly string $modulePage;

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
		$databaseSettings = getDatabaseSettings();
		$blotterRepository = new blotterRepository(
			databaseConnection::getInstance(),
			$databaseSettings['BLOTTER_TABLE'],
			$databaseSettings['ACCOUNT_TABLE']
		);
		$this->blotterService = new blotterService($blotterRepository, $this->moduleContext->transactionManager);
		$this->modulePage = $this->getModulePageURL([], false);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);
	}

	public function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a title="' . _T('admin_nav_blotter_title') . '" href="' . sanitizeStr($this->modulePage) . '">' . _T('admin_nav_blotter') . '</a></li>';
	}

	public function ModulePage(): void {
		// Admin panel to manage blotter
		if (isPostRequest()) {
			if (!empty($_POST['new_blot_txt'])) {
				$this->handleBlotterAddition();

				$this->moduleContext->actionLoggerService->logAction("Added new blotter entry", GLOBAL_BOARD_UID);

				rebuildAllBoards(); //rebuild all pages so it takes effect immedietly
				
				redirect($this->getModulePageURL([], false));
			}
			if (!empty($_POST['edit_submit']) && !empty($_POST['entryedit']) && is_array($_POST['entryedit'])) {
				$editedIds = $this->editBlotterEntries($_POST['entryedit']);

				if (!empty($editedIds)) {
					$this->moduleContext->actionLoggerService->logAction("Edited blotter entries with IDs: " . implode(", ", $editedIds), GLOBAL_BOARD_UID);
					rebuildAllBoards(); //rebuild all pages so it takes effect immedietly
				}

				redirect($this->getModulePageURL([], false));
			}
			if (!empty($_POST['delete_submit']) && !empty($_POST['entrydelete'])) {
				$this->deleteBlotterEntries($_POST['entrydelete']);

				// log deletion
				$this->moduleContext->actionLoggerService->logAction("Deleted blotter entries with IDs: " . implode(", ", $_POST['entrydelete']), GLOBAL_BOARD_UID);

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
		if(!is_array($uidsToDelete)) {
			$uidsToDelete = [$uidsToDelete];
		}

		$this->blotterService->deleteEntries($uidsToDelete);
	}

	/**
	 * @param array<int|string, string> $entryEdits
	 * @return int[]
	 */
	private function editBlotterEntries(array $entryEdits): array {
		return $this->blotterService->editEntries($entryEdits);
	}

	private function prepareAdminBlotterPlaceHolders() {
		$blotterEntries = $this->blotterService->getEntries();

		$blotterPlaceholders = [
			'{$MODULE_URL}' => sanitizeStr($this->modulePage),
			'{$ROWS}' => [],
		];

		foreach($blotterEntries as $blotterEntry) {
			$blotterPlaceholders['{$ROWS}'][] = $blotterEntry->toAdminTemplateRow();
		}
		
		return $blotterPlaceholders;
	}

	private function handleBlotterAddition() {
		$newText = (string) ($_POST['new_blot_txt'] ?? '');
		$this->blotterService->addEntry($newText, $this->moduleContext->currentUserId);
	}

}