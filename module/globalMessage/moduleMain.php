<?php

namespace Kokonotsuba\Modules\globalMessage;

// include helper functions
include_once __DIR__ . '/globalMessageLibrary.php';

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PlaceHolderInterceptListenerTrait;

class moduleMain extends abstractModuleMain {
	use PlaceHolderInterceptListenerTrait;
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
		
		$this->listenPlaceHolderIntercept('onPlaceHolderIntercept');
	}

	private function onPlaceHolderIntercept(array &$placeholderArray): void {
		$placeholderArray['{$GLOBAL_MESSAGE}'] = getGlobalMessage($this->globalMessageFile);
	}

}

