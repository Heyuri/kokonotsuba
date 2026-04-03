<?php

namespace Kokonotsuba\module_classes\listeners;

trait PostMenuListListenerTrait {
	protected function listenPostMenuList(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostMenuList',
			function(string &$postMenuListHtml) use ($methodName) {
				$this->$methodName($postMenuListHtml);
			},
			$priority
		);
	}
}
