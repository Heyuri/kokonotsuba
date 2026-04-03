<?php

namespace Kokonotsuba\module_classes\listeners;

trait PostFormFileListenerTrait {
	protected function listenPostFormFile(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostFormFile',
			function(string &$postFormFile) use ($methodName) {
				$this->$methodName($postFormFile);
			},
			$priority
		);
	}
}
