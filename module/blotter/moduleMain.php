<?php

namespace Kokonotsuba\Modules\blotter;

require_once __DIR__ . '/blotterEntry.php';
require_once __DIR__ . '/blotterRepository.php';
require_once __DIR__ . '/blotterService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\PlaceHolderInterceptListenerTrait;

use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use PlaceHolderInterceptListenerTrait;

	private string $modulePage;
	private int $previewLimit = -1;
	private blotterService $blotterService;

	public function getName(): string {
		return 'Blotter';
	}

	public function getVersion(): string  {
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
		$this->previewLimit = (int) $this->getConfig('ModuleSettings.BLOTTER_PREVIEW_AMOUNT');
		
		$this->modulePage = $this->getModulePageURL([], false);

		$this->listenPlaceHolderIntercept('onRenderBlotterPreview');
	}

	public function onRenderBlotterPreview(array &$placeHolderArray) {
		$globalMessage = &$placeHolderArray['{$BLOTTER}'];
		
		static $res;
		if(!is_null($res)){
			$globalMessage .= $res;
			return;
		}
		$blotterEntries = $this->blotterService->getEntries($this->previewLimit);
		$previewEntries = [];

		foreach ($blotterEntries as $entry) {
			$previewEntries[] = $entry->toPublicTemplateRow();
		}

		$templateValues = [
				'{$MODULE_URL}' => sanitizeStr($this->modulePage),
				'{$ENTRIES}' => $previewEntries,
				'{$EMPTY}' => empty($previewEntries),
		];
		
		$res = $this->moduleContext->adminPageRenderer->ParseBlock('BLOTTER_PREVIEW', $templateValues);
		$globalMessage = $res;
	}

	private function drawBlotterTable() {
		$blotterEntries = $this->blotterService->getEntries();

		$rows = [];
		foreach ($blotterEntries as $entry) {
				$rows[] = $entry->toPublicTemplateRow();
		}

		$templateValues = [
				'{$STATIC_INDEX_FILE}' => sanitizeStr($this->getConfig('STATIC_INDEX_FILE')),
				'{$ROWS}' => $rows,
				'{$EMPTY}' => empty($rows),
		];

		return $this->moduleContext->adminPageRenderer->ParseBlock('BLOTTER_PAGE', $templateValues);
	}

	public function ModulePage() {
		$blotterTableHtml = $this->drawBlotterTable();

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $blotterTableHtml]);
	}
}