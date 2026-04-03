<?php

// sticky module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';

use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\OpeningPostListenerTrait;

class moduleMain extends abstractModuleMain {
	use OpeningPostListenerTrait;
	public function getName(): string {
		return 'Sticky';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->listenOpeningPost('onRenderOpeningPost');
	}

	public function onRenderOpeningPost(array &$templateValues, Post $post): void {
		// indicates whether the thread is sticky'd or not 
		$stickyFlag = $post->getFlags();

		// get the sticky indicator
		$stickyIndicator = getStickyIndicator($this->getConfig('STATIC_URL'));

		if ($stickyFlag->value('sticky')) {
			$templateValues['{$POSTINFO_EXTRA}'] .= $stickyIndicator;
		}
	}

}
