<?php
/*
Mod_Readonly - Add this to the boards config to make it admin-only
*/

namespace Kokonotsuba\Modules\readOnly;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;
use Kokonotsuba\error\BoardException;

class moduleMain extends abstractModuleMain {
	use RegistBeginListenerTrait;

	private $ALLOWREPLY, $MINIMUM_ROLE; // Allow replies

	public function initialize(): void {
		$this->ALLOWREPLY = $this->getConfig('ModuleSettings.ALLOW_REPLY');
		$this->MINIMUM_ROLE = $this->getConfig('ModuleSettings.MINIMUM_ROLE');

		$this->listenRegistBegin('onRegistBegin');
	}

	public function getName(): string {
		return 'readOnly : Read-Only Board';
	}

	public function getVersion(): string {
		return 'VERSION 9001';
	}

	public function onRegistBegin(array &$registInfo): void {
		$roleLevel = $registInfo['roleLevel'];
		$isNewThread = $registInfo['isThreadSubmit'];

		if($this->ALLOWREPLY && !$isNewThread) return;
		if($roleLevel->isLessThan($this->MINIMUM_ROLE)){
			throw new BoardException('New posts cannot be made at this time.');
		}
	}
}
