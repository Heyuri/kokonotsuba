<?php

namespace Kokonotsuba\module_classes\listeners;

trait RegistBeforeCommitListenerTrait {
	protected function listenRegistBeforeCommit(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit',
			function(&$name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $files, $isReply, &$status, $thread, &$posterHash) use ($methodName) {
				$this->$methodName($name, $email, $emailForInsertion, $sub, $com, $category, $age, $files, $isReply, $status, $thread, $posterHash);
			},
			$priority
		);
	}
}
