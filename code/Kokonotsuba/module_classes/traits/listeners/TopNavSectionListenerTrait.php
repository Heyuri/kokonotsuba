<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * Emit markup at the top of the nav section, above the board list ({$TOP_NAV_SECTION_HOOK}).
 *
 * The target method signature must be: (string &$topNavSection): void
 */
trait TopNavSectionListenerTrait {
	protected function listenTopNavSection(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('TopNavSection',
			function(string &$topNavSection) use ($methodName) {
				$this->$methodName($topNavSection);
			},
			$priority
		);
	}
}
