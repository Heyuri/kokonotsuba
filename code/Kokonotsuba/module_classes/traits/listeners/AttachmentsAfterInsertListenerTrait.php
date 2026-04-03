<?php

namespace Kokonotsuba\module_classes\listeners;

trait AttachmentsAfterInsertListenerTrait {
	protected function listenAttachmentsAfterInsert(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('AttachmentsAfterInsert',
			function(array &$attachments) use ($methodName) {
				$this->$methodName($attachments);
			},
			$priority
		);
	}
}
