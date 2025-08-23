<?php

// sticky module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\sticky;

use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private readonly string $STICKYICON;

	public function getName(): string {
		return 'Sticky';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->STICKYICON = $this->getConfig('STATIC_URL') . 'image/sticky.png';
	
		$this->moduleContext->moduleEngine->addListener('OpeningPost', function(array &$templateValues, array $post) {
			$this->onRenderOpeningPost($templateValues['{$POSTINFO_EXTRA}'], $post);
		});
	}

	public function onRenderOpeningPost(string &$postInfoExtra, array $post): void {
		$fh = new FlagHelper($post['status']);

		if ($fh->value('sticky')) {
			$postInfoExtra .= '<img src="' . $this->STICKYICON . '" class="icon" height="18" width="18" title="Sticky">';
		}
	}

}
