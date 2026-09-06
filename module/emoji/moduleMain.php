<?php

namespace Kokonotsuba\Modules\emoji;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostCommentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\CommentExtrasListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\FormattingDetailsTrait;
use Kokonotsuba\post\Post;

use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use PostCommentListenerTrait;
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

		$this->listenPostComment('onRenderComment');
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
		$baseUrl = $this->staticUrl . 'image/emoji/';

		// Build picker data from the same emojis.php used for text replacement
		$items = [];
		foreach ($this->emojis as $char => $name) {
			$items[] = [
				'src' => $name . '.gif',
				'value' => $char,
				'title' => str_replace('-', ' ', $name),
			];
		}

		$content = '<div id="emojiButtons"></div>'
			. '<script type="application/json" id="emojiData">'
			. json_encode(['baseUrl' => $baseUrl, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			. '</script>';

		return $this->renderFormattingDetails('emojiContainer', 'Emoji', $content);
	}

	private function buildEmojiFilter(): array {
		$emojiFilter = [];

		foreach ($this->emojis as $char => $name) {
			$emojiFilter["/$char/u"] =
				'<img class="emoji" src="' . sanitizeStr($this->staticUrl) . 'image/emoji/' . sanitizeStr($name) . '.gif" title="' . sanitizeStr($name) . '" alt="' . sanitizeStr($char) . '">';
		}

		return $emojiFilter;
	}

	/**
	 * Swap emoji characters for their images.
	 *
	 * Done at render time so the stored comment keeps the characters the poster typed, which
	 * also means the images follow the board's current STATIC_URL.
	 */
	private function onRenderComment(string &$comment, ?Post $post = null, bool $isThreadView = false): void {
		// Loop through emoji regex and apply it on the comment 
		foreach ($this->emojiFilter as $filterin => $filterout) {
			// apply filter
			$comment = preg_replace($filterin, $filterout, $comment);
		}
	}


}