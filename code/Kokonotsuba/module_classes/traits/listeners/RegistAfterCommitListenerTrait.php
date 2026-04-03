<?php

namespace Kokonotsuba\module_classes\listeners;

trait RegistAfterCommitListenerTrait {
	protected function listenRegistAfterCommit(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('RegistAfterCommit',
			function(int $postNo, string $threadUid, string $name, string $email, string $sub, string $comment) use ($methodName) {
				$this->$methodName($postNo, $threadUid, $name, $email, $sub, $comment);
			},
			$priority
		);
	}
}
