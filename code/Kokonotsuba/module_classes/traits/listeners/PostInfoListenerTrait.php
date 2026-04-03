<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait PostInfoListenerTrait {
	protected function listenPostInfo(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostInfo',
			function(string &$postInfo) use ($methodName) {
				$this->$methodName($postInfo);
			},
			$priority
		);
	}
}
