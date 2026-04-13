<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait AttachmentWidgetListenerTrait {
	protected function listenAttachmentWidget(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('AttachmentWidget',
			function(array &$widgetArray, array &$fileData) use ($methodName) {
				$this->$methodName($widgetArray, $fileData);
			},
			$priority
		);
	}
}
