<?php

namespace Kokonotsuba\Modules\youtubeEmbed;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;

class moduleMain extends abstractModuleMain {
	use IncludeScriptTrait;

	public function getName(): string {
		return 'YouTube Embed';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->registerScript('youtubeEmbed.js');
	}
}
