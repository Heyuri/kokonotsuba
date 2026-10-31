<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use function Puchiko\strings\sanitizeStr;

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
				$topLinkHtml .= ' [<a href="' . sanitizeStr($url) . '">' . sanitizeStr($text) . '</a>]';
			},
			$priority
		);
	}
}
