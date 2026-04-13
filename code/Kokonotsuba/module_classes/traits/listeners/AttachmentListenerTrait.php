<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait AttachmentListenerTrait {
	protected function listenAttachment(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('Attachment',
			function(string &$imageBar, string &$imageHtml, string &$imageUrl, array &$fileData) use ($methodName) {
				$this->$methodName($imageBar, $imageHtml, $imageUrl, $fileData);
			},
			$priority
		);
	}
}
