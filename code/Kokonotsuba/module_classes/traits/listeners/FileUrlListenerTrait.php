<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait FileUrlListenerTrait {
	/**
	 * Listen for the FileUrl event, fired after a file URL is resolved for both
	 * PHP-served and direct (web-server) attachments.
	 *
	 * Callback signature: onMethod(string &$url, array $attachment, bool $isThumb)
	 */
	protected function listenFileUrl(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('FileUrl',
			function(string &$url, array $attachment, bool $isThumb) use ($methodName) {
				$this->$methodName($url, $attachment, $isThumb);
			},
			$priority
		);
	}
}
