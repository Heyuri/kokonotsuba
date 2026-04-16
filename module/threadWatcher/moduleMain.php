<?php

namespace Kokonotsuba\Modules\threadWatcher;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\FormFuncsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;

use function Kokonotsuba\libraries\_T;

class moduleMain extends abstractModuleMain {
	use FormFuncsListenerTrait;
	use IncludeScriptTrait;

	public function getName(): string {
		return 'Thread Watcher';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->addFormFuncLink('javascript:void(0)', _T('thread_watch_link'), true);
		$this->registerScript('threadWatcher.js');
	}

}
