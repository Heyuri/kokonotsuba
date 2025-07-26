<?php

namespace Kokonotsuba\Modules\blotter;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

include_once __DIR__ . '/blotterLibrary.php';

class moduleMain extends abstractModuleMain {
	private $myPage;
	private $BLOTTER_PATH; 
	private $previewLimit = -1; // Path to blotter file

	public function getName(): string {
		return 'Blotter';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->BLOTTER_PATH = $this->getConfig('ModuleSettings.BLOTTER_FILE');
		
		if(!file_exists($this->BLOTTER_PATH)) {
			touch($this->BLOTTER_PATH);
		}
		
		$this->previewLimit = $this->getConfig('ModuleSettings.BLOTTER_PREVIEW_AMOUNT');
		
		$this->myPage = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addListener('PlaceHolderIntercept', function(array &$placeholderArray) {
			$this->onRenderBlotterPreview($placeholderArray);
		});
	}

	public function onRenderBlotterPreview(array &$placeHolderArray) {
		$globalMessage = &$placeHolderArray['{$BLOTTER}'];
		
		static $res;
		if(!is_null($res)){
			$globalMessage .= $res;
			return;
		}
		$blotterData = getBlotterFileData($this->BLOTTER_PATH);
		$previewEntries = [];

		foreach ($blotterData as $i => $entry) {
				if ($i >= $this->previewLimit) break;
				$previewEntries[] = [
						'{$DATE}' => $entry['date'],
						'{$COMMENT}' => $entry['comment'],
				];
		}

		$templateValues = [
				'{$MODULE_URL}' => $this->myPage,
				'{$ENTRIES}' => $previewEntries,
				'{$EMPTY}' => empty($previewEntries),
		];
		
		$res = $this->moduleContext->adminPageRenderer->ParseBlock('BLOTTER_PREVIEW', $templateValues);
		$globalMessage = $res;
	}

	private function drawBlotterTable() {
		$blotterData = getBlotterFileData($this->BLOTTER_PATH);

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

		return $this->moduleContext->adminPageRenderer->ParseBlock('BLOTTER_PAGE', $templateValues);
	}

	public function ModulePage() {
		$blotterTableHtml = $this->drawBlotterTable();

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $blotterTableHtml]);
	}
}
?>