<?php
/*
Mod_Readonly - Add this to the boards config to make it admin-only
*/

namespace Kokonotsuba\Modules\readOnly;

use Kokonotsuba\ModuleClasses\abstractModuleMain;
use BoardException;

class moduleMain extends abstractModuleMain {
	private $ALLOWREPLY, $MINIMUM_ROLE; // Allow replies

	public function initialize(): void {
		$this->ALLOWREPLY = $this->getConfig('ModuleSettings.ALLOW_REPLY');
		$this->MINIMUM_ROLE = $this->getConfig('ModuleSettings.MINIMUM_ROLE');

		$this->moduleContext->moduleEngine->addListener('RegistBegin', function (array &$registInfo) {
			$this->onRegistBegin($registInfo['roleLevel'], $registInfo['isThreadSubmit']);  // Call the method to modify the form
		});
	}

	public function getName(): string {
		return 'readOnly : Read-Only Board';
	}

	public function getVersion(): string {
		return 'VERSION 9001';
	}

	public function onRegistBegin($roleLevel, $isNewThread): void {
		if($this->ALLOWREPLY && !$isNewThread) return;
		if($roleLevel->isLessThan($this->MINIMUM_ROLE)){
			throw new BoardException('New posts cannot be made at this time.');
		}
	}
}
