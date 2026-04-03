<?php
// komeo 2024

namespace Kokonotsuba\Modules\fieldTraps;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\RegistBeginListenerTrait;
use Kokonotsuba\module_classes\listeners\PostFormListenerTrait;

class moduleMain extends abstractModuleMain {
	use RegistBeginListenerTrait;
	use PostFormListenerTrait;

	private $fields = array('e-mail', 'username', 'subject', 'comment', 'firstname', 'lastname', 'city', 'state', 'zipcode');

	public function initialize(): void {
		// Register the listener for the PostInfo hook
		$this->listenRegistBegin('onRegistBegin');
		
		$this->listenPostForm('onRenderPostForm');
	}

	public function getName(): string {
		return __CLASS__.' : Field traps';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function onRenderPostForm(string &$postForm){
		foreach ($this->fields as &$f) {
			$postForm .= '<input maxlength="100" type="text" name="'.$f.'" id="'.$f.'" size="28" value="" class="inputtext" style="display: none;">';
		}
	}

	public function onRegistBegin(): void {
		foreach ($this->fields as &$f) {
			if ($this->moduleContext->request->hasParameter($f, 'POST') && $this->moduleContext->request->getParameter($f, 'POST') != "") {
				throw new BoardException("You appear to be a bot! [ -c°▥°]-c");
			}
		}
	}
}