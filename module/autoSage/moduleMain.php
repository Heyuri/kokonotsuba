<?php

namespace Kokonotsuba\Modules\autoSage;

require_once __DIR__ . '/autoSageLibrary.php';

use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\RegistBeforeCommitListenerTrait;
use Kokonotsuba\module_classes\listeners\OpeningPostListenerTrait;

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
		$this->listenRegistBeforeCommit(function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			if(!$isReply) {
				return;
			}

			$this->onBeforeCommit($thread, $age);  // Call the method to modify the form
		});

		$this->listenOpeningPost(function (&$arrLabels, $post) {
			$this->renderAutosageIcon($arrLabels['{$POSTINFO_EXTRA}'], $post);
		});
	}

	public function renderAutosageIcon(string &$postInfoExtra, Post $post) {
		$status = $post->getFlags();
		
		if($status->value('as')) {
			$postInfoExtra .= getAutoSageIndicator();
		}
	}

	public function onBeforeCommit(array &$thread, bool &$age) {
		$post = $thread['posts'][0];
		$status = $post->getFlags();
		
		if($status->value('as')) { 
			$age = false;
		}
	}
}