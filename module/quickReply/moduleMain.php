<?php

namespace Kokonotsuba\Modules\quickReply;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\FormFuncsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;

use function Kokonotsuba\libraries\_T;

class moduleMain extends abstractModuleMain {
	use FormFuncsListenerTrait;
	use IncludeScriptTrait;

	public function getName(): string {
		return 'Quick Reply';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		// add the quick reply link to the form functions (the links above the post form textarea)
		$this->addFormFuncLink('javascript:kkqr.openqr();', _T('quick_reply_link'), true);
		
		// register the quick reply script
		$this->registerScript('quickReply.js');
	}
}
