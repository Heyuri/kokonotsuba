<?php

namespace Kokonotsuba\Modules\emoji;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private array $emojiFilter;
	private readonly string $staticUrl;
		 
	public function getName(): string {
		return 'Emoji!';
	}
		 
	public function getVersion(): string {
		return 'Kokonutz';
	}

	public function initialize(): void {
		$this->staticUrl = $this->getConfig('STATIC_URL');
		
		$this->emojiFilter = $this->buildEmojiFilter();

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			$this->onBeforeCommit($com);
		});
	}

	private function buildEmojiFilter(): array {
		// get the emoji array from the file
		$emojis = require __DIR__ . '/emojis.php';
		
		// init emoji filter
		$emojiFilter = [];

		// loop through and add emoji filters
		foreach ($emojis as $char => $name) {
			// add filter
			$emojiFilter["/$char/u"] =
				"<img class=\"emoji\" src=\"" . $this->staticUrl . "image/emoji/$name.gif\" title=\"$name\" alt=\"$char\">";
		}

		// return the filter array
		return $emojiFilter;
	}

	public function onBeforeCommit(string &$com): void {
		// Loop through emoji regex and apply it on the comment 
		foreach ($this->emojiFilter as $filterin => $filterout) {
			// apply filter
			$com = preg_replace($filterin, $filterout, $com);
		}
	}


}