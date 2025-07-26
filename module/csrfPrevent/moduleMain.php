<?php

namespace Kokonotsuba\Modules\csrfPrevent;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'mod_csrf_prevent : 防止偽造跨站請求 (CSRF)';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addListener('RegistBegin', function () {
			$this->onRegistBegin();  // Call the method to modify the form
		});
	}

	public function onRegistBegin(): void {
		$CSRFdetectd = false;
		
		/* Check HTTP_REFERER (to prevent cross-site form)
		 *  1. No HTTP_REFERER
		 *  2. HTTP_REFERER is not in this domain
		 */
		if(!strpos($_SERVER['HTTP_REFERER']??'', fullURL())) {
			$CSRFdetectd = true;
		}

		if($CSRFdetectd) {
			throw new \BoardException('ERROR: CSRF detected!');
		}
	}
}//End-Of-Module
