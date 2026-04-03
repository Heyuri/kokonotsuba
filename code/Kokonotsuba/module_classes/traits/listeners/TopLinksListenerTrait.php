<?php

namespace Kokonotsuba\module_classes\listeners;

trait TopLinksListenerTrait {
	protected function listenTopLinks(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('TopLinks',
			function(string &$topLinkHtml, $isReply) use ($methodName) {
				$this->$methodName($topLinkHtml, $isReply);
			},
			$priority
		);
	}
}
