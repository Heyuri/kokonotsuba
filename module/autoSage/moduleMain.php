<?php

namespace Kokonotsuba\Modules\autoSage;

require_once __DIR__ . '/autoSageLibrary.php';

use Kokonotsuba\post\FlagHelper;
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

		$this->listenOpeningPost('renderAutosageIcon');
	}

	public function renderAutosageIcon(array &$templateValues, Post $post) {
		$status = $post->getFlags();
		
		if($status->value('as')) {
			$templateValues['{$POSTINFO_EXTRA}'] .= getAutoSageIndicator();
		}
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