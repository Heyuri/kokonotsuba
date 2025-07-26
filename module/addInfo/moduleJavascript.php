<?php

namespace Kokonotsuba\Modules\addInfo;

use Kokonotsuba\ModuleClasses\abstractModuleJavascript;

class moduleJavascript extends abstractModuleJavascript {
	public function initialize(): void {
	}

	public function getName(): string {
		return 'Add info js';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function cssHookPoint(string &$urlList): void {
	
	}

	public function javascriptHookPoint(string &$urlList): void {
		
	}
}