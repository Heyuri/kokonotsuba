<?php

namespace Kokonotsuba\Modules\emoji;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeforeCommitListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\CommentExtrasListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\FormattingDetailsTrait;

use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use RegistBeforeCommitListenerTrait;
	use CommentExtrasListenerTrait;
	use IncludeScriptTrait;
	use FormattingDetailsTrait;

	private array $emojiFilter;
	private array $emojis;
	private readonly string $staticUrl;
		 
	public function getName(): string {
		return 'Emoji!';
	}
		 
	public function getVersion(): string {
		return 'Kokonutz';
	}

	public function initialize(): void {
		$this->staticUrl = $this->getConfig('STATIC_URL');
		
		$this->emojis = require __DIR__ . '/emojis.php';
		$this->emojiFilter = $this->buildEmojiFilter();

		$this->listenRegistBeforeCommit('onBeforeCommit');
		$this->listenCommentExtras('onRenderCommentExtras');
		$this->registerScript('addemoji.js');
	}

	private function onRenderCommentExtras(string &$html): void {
		$html .= $this->renderEmojiContainer();
	}

	private function renderEmojiContainer(): string {
		if (empty($this->emojis)) {
			return '';
		}
		$baseUrl = sanitizeStr($this->staticUrl) . 'image/emoji/';
		$templateEngine = $this->moduleContext->templateEngine;

		$buttons = '';
		$i = 0;
		foreach ($this->emojis as $char => $name) {
			$i++;
			$buttons .= $templateEngine->ParseBlock('EMOJI_BUTTON', [
				'{$BASE_URL}' => $baseUrl,
				'{$NAME}' => sanitizeStr($name),
				'{$CHAR}' => sanitizeStr($char),
				'{$TITLE}' => sanitizeStr(str_replace('-', ' ', $name)),
				'{$ROW_END}' => ($i % 70 === 0) ? ' row-end' : '',
			]);
		}

		return $this->renderFormattingDetails('emojiContainer', 'Emoji', $buttons);
	}

	private function buildEmojiFilter(): array {
		$emojiFilter = [];

		foreach ($this->emojis as $char => $name) {
			$emojiFilter["/$char/u"] =
				'<img class="emoji" src="' . sanitizeStr($this->staticUrl) . 'image/emoji/' . sanitizeStr($name) . '.gif" title="' . sanitizeStr($name) . '" alt="' . sanitizeStr($char) . '">';
		}

		return $emojiFilter;
	}

	public function onBeforeCommit(&$name, &$email, &$emailForInsertion, &$sub, string &$com): void {
		// Loop through emoji regex and apply it on the comment 
		foreach ($this->emojiFilter as $filterin => $filterout) {
			// apply filter
			$com = preg_replace($filterin, $filterout, $com);
		}
	}


}