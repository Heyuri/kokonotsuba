<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait PostFormListenerTrait {
	protected function listenPostForm(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostForm',
			function(string &$postForm) use ($methodName) {
				$this->$methodName($postForm);
			},
			$priority
		);
	}
}
