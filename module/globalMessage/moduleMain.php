<?php

namespace Kokonotsuba\Modules\globalMessage;

// include helper functions
include_once __DIR__ . '/globalMessageLibrary.php';

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private readonly string $globalMessageFile;

	public function getName(): string {
		return 'Global Message';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}
	
	public function initialize(): void {
		$this->globalMessageFile = $this->getConfig('ModuleSettings.GLOBAL_TXT');

		if(!file_exists($this->globalMessageFile)) touch($this->globalMessageFile);
		
		$this->moduleContext->moduleEngine->addListener('PlaceHolderIntercept', function(array &$placeholderArray) {
			$this->onPlaceHolderIntercept($placeholderArray);
		});
	}

	private function onPlaceHolderIntercept(array &$placeholderArray): void {
		$placeholderArray['{$GLOBAL_MESSAGE}'] = getGlobalMessage($this->globalMessageFile);
	}

}

