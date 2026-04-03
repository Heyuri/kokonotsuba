<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait ImageUrlListenerTrait {
	protected function listenImageUrl(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ImageUrl',
			function(string &$imageUrl, string $fileId, bool $isThumb) use ($methodName) {
				$this->$methodName($imageUrl, $fileId, $isThumb);
			},
			$priority
		);
	}
}
