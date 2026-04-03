<?php

namespace Kokonotsuba\module_classes\listeners;

trait PostFormAdminListenerTrait {
	protected function listenPostFormAdmin(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostFormAdmin',
			function(string &$postFormAdmin) use ($methodName) {
				$this->$methodName($postFormAdmin);
			},
			$priority
		);
	}
}
