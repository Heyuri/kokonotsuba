<?php

namespace Kokonotsuba\Modules\autoSage;

require_once __DIR__ . '/autoSageLibrary.php';

use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeforeCommitListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\OpeningPostListenerTrait;

class moduleMain extends abstractModuleMain {
	use RegistBeforeCommitListenerTrait;
	use OpeningPostListenerTrait;

	public function getName(): string {
		return 'Thread autosage';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}

 	public function initialize(): void {
		$this->listenRegistBeforeCommit('onBeforeCommit');

		$this->registerOpeningPostIndicator('autosage', getAutoSageIndicator(), fn(Post $p) => $p->getFlags()->value('as'), 10);
	}

	public function onBeforeCommit(&$name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $files, $isReply, $status, $thread) {
		if(empty($thread)) return;
		$post = $thread->getOpeningPost();
		$flags = $post->getFlags();
		
		if($flags->value('as')) { 
			$age = false;
		}
	}
}