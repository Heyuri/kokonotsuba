<?php

namespace Kokonotsuba\Modules\globalMessage;

// include helper functions
include_once __DIR__ . '/globalMessageLibrary.php';

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PlaceHolderInterceptListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;

class moduleMain extends abstractModuleMain {
	use ModuleHeaderListenerTrait;
	use PlaceHolderInterceptListenerTrait;
	private readonly string $globalMessageFile;

	public function getName(): string {
		return 'Global Message';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}
	
	public function initialize(): void {
		$this->globalMessageFile = $this->getModuleConfig('GLOBAL_TXT', getBackendGlobalDir().'globalmsg.txt');

		if(!file_exists($this->globalMessageFile)) touch($this->globalMessageFile);
		
		$this->listenPlaceHolderIntercept('onPlaceHolderIntercept');
		$this->listenModuleHeader('onModuleHeaderStylesheet');
	}

	/** The module's own stylesheet. */
	private function onModuleHeaderStylesheet(string &$moduleHeader): void {
		$this->includeStylesheet('globalMessage.css', $moduleHeader);
	}

	private function onPlaceHolderIntercept(array &$placeholderArray): void {
		$placeholderArray['{$GLOBAL_MESSAGE}'] = getGlobalMessage($this->globalMessageFile);
	}

}

