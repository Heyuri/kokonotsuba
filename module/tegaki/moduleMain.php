<?php

namespace Kokonotsuba\Modules\tegaki;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\CommentBlockListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;

class moduleMain extends abstractModuleMain {
	use CommentBlockListenerTrait;
	use IncludeScriptTrait;

	public function getName(): string {
		return 'Tegaki drawing';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		if ($this->getConfig('TEXTBOARD_ONLY')) {
			return;
		}

		$this->listenCommentBlock('onCommentBlock');
	}

	private function onCommentBlock(string &$commentBlock): void {
		$this->registerScript('momo/tegaki.js');

		$moduleTemplateEngine = $this->initModuleTemplateEngine('ModuleSettings.TEGAKI_TEMPLATE', 'tegaki');
		$commentBlock .= $moduleTemplateEngine->ParseBlock('button', []);
	}
}
