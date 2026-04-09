<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait TopLinksListenerTrait {
	protected function listenTopLinks(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('TopLinks',
			function(string &$topLinkHtml, $isReply) use ($methodName) {
				$this->$methodName($topLinkHtml, $isReply);
			},
			$priority
		);
	}

	protected function addTopLink(string $url, string $text, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('TopLinks',
			function(string &$topLinkHtml) use ($url, $text) {
				$topLinkHtml .= ' [<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($text) . '</a>]';
			},
			$priority
		);
	}
}
