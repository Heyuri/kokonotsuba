<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait HeadListenerTrait {
	protected function listenHead(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('Head',
			function(string &$html, int $resno) use ($methodName) {
				$this->$methodName($html, $resno);
			},
			$priority
		);
	}
}
