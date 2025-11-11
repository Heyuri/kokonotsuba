<?php

// sticky module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';

use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'Sticky';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addListener('OpeningPost', function(array &$templateValues, array $post) {
			$this->onRenderOpeningPost($templateValues['{$POSTINFO_EXTRA}'], $post);
		});
	}

	public function onRenderOpeningPost(string &$postInfoExtra, array $post): void {
		// indicates whether the thread is sticky'd or not 
		$stickyFlag = new FlagHelper($post['status']);

		// get the sticky indicator
		$stickyIndicator = getStickyIndicator($this->getConfig('STATIC_URL'));

		if ($stickyFlag->value('sticky')) {
			$postInfoExtra .= $stickyIndicator;
		}
	}

}
