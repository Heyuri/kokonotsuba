<?php

// sticky module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';

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
		$this->listenOpeningPost('onRenderOpeningPost', 30);
	}

	public function onRenderOpeningPost(array &$templateValues, Post $post): void {
		$stickyFlag = $post->getFlags();
		$stickyIndicator = getStickyIndicator($this->getConfig('STATIC_URL'));
		$isActive = $stickyFlag->value('sticky');
		$hiddenClass = $isActive ? '' : ' indicatorHidden';

		$templateValues['{$POSTINFO_EXTRA}'] .= '<span class="indicator indicator-sticky' . $hiddenClass . '">' . $stickyIndicator . '</span>';
	}

}
