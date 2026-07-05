<?php
/*
Mod_Readonly - Add this to the boards config to make it admin-only
*/

namespace Kokonotsuba\Modules\readOnly;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\userRole;

class moduleMain extends abstractModuleMain {
	use RegistBeginListenerTrait;

	private $ALLOWREPLY, $MINIMUM_ROLE; // Allow replies

	public function initialize(): void {
		$this->ALLOWREPLY = $this->getModuleConfig('ALLOW_REPLY');
		$this->MINIMUM_ROLE = $this->getModuleConfig('MINIMUM_ROLE', userRole::LEV_MODERATOR);

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
