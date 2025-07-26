<?php
// komeo 2024

namespace Kokonotsuba\Modules\fieldTraps;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private $fields = array('e-mail', 'username', 'subject', 'comment', 'firstname', 'lastname', 'city', 'state', 'zipcode');

	public function initialize(): void {
		// Register the listener for the PostInfo hook
		$this->moduleContext->moduleEngine->addListener('RegistBegin', function () {
			$this->onRegistBegin();  // Call the method to modify the form
		});
		
		$this->moduleContext->moduleEngine->addListener('PostForm', function (&$postForm) {
			$this->onRenderPostForm($postForm);  // Call the method to modify the form
		});
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
			if (isset($_POST[$f]) && $_POST[$f] != "") {
				throw new BoardException("You appear to be a bot! [ -c°▥°]-c");
			}
		}
	}
}