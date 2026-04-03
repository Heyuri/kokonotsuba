<?php

namespace Kokonotsuba\Modules\blotter;

require_once __DIR__ . '/blotterEntry.php';
require_once __DIR__ . '/blotterRepository.php';
require_once __DIR__ . '/blotterService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\rebuildAllBoards;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;

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

		$this->registerLinksAboveBarHook('onRenderLinksAboveBar');
	}

	public function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a title="' . _T('admin_nav_blotter_title') . '" href="' . sanitizeStr($this->modulePage) . '">' . _T('admin_nav_blotter') . '</a></li>';
	}

	public function ModulePage(): void {
		// Admin panel to manage blotter
		if ($this->moduleContext->request->isPost()) {
			if (!empty($this->moduleContext->request->getParameter('new_blot_txt', 'POST'))) {
				$this->handleBlotterAddition();

				$this->logAction("Added new blotter entry", GLOBAL_BOARD_UID);

				rebuildAllBoards(); //rebuild all pages so it takes effect immedietly
				
				redirect($this->getModulePageURL([], false));
			}
			if (!empty($this->moduleContext->request->getParameter('edit_submit', 'POST')) && !empty($this->moduleContext->request->getParameter('entryedit', 'POST')) && is_array($this->moduleContext->request->getParameter('entryedit', 'POST'))) {
				$editedIds = $this->editBlotterEntries($this->moduleContext->request->getParameter('entryedit', 'POST'));

				if (!empty($editedIds)) {
					$this->logAction("Edited blotter entries with IDs: " . implode(", ", $editedIds), GLOBAL_BOARD_UID);
					rebuildAllBoards(); //rebuild all pages so it takes effect immedietly
				}

				redirect($this->getModulePageURL([], false));
			}
			if (!empty($this->moduleContext->request->getParameter('delete_submit', 'POST')) && !empty($this->moduleContext->request->getParameter('entrydelete', 'POST'))) {
				$this->deleteBlotterEntries($this->moduleContext->request->getParameter('entrydelete', 'POST'));

				// log deletion
				$this->logAction("Deleted blotter entries with IDs: " . implode(", ", $this->moduleContext->request->getParameter('entrydelete', 'POST')), GLOBAL_BOARD_UID);

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
		$newText = (string) ($this->moduleContext->request->getParameter('new_blot_txt', 'POST', ''));
		$this->blotterService->addEntry($newText, $this->moduleContext->currentUserId);
	}

}