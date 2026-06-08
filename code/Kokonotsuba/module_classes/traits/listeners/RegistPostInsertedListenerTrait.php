<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait RegistPostInsertedListenerTrait {
	protected function listenRegistPostInserted(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('RegistPostInserted',
			function(int $postUid, string $ip) use ($methodName) {
				$this->$methodName($postUid, $ip);
			},
			$priority
		);
	}
}
