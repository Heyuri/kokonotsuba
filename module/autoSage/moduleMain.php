<?php

namespace Kokonotsuba\Modules\autoSage;

use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'Thread autosage';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}

 	public function initialize(): void {
		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread) {
			if(!$isReply) {
				return;
			}

			$this->onBeforeCommit($thread, $age);  // Call the method to modify the form
		});

		$this->moduleContext->moduleEngine->addListener('OpeningPost', function (&$arrLabels, $post) {
			$this->renderAutosageIcon($arrLabels['{$POSTINFO_EXTRA}'], $post);
		});
	}

	public function renderAutosageIcon(string &$postInfoExtra, array $post) {
		$status = new FlagHelper($post['status']);
		
		if($status->value('as')) {
			$postInfoExtra .= ' <span class="autosage" title="Autosage. This thread cannot be bumped.">AS</span>';
		}
	}

	public function onBeforeCommit(array &$thread, bool &$age) {
		$post = $thread['posts'][0];
		$status = new FlagHelper($post['status']);
		
		if($status->value('as')) { 
			$age = false;
		}
	}
}