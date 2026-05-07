<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait PostSeparateListenerTrait {
	protected function listenPostSeparate(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostSeparate',
			function(string &$html, int $replyIterator) use ($methodName) {
				$this->$methodName($html, $replyIterator);
			},
			$priority
		);
	}
}
