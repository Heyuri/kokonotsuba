<?php

namespace Kokonotsuba\Modules\emotes;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostCommentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\CommentExtrasListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\FormattingDetailsTrait;
use Kokonotsuba\post\Post;

use function Puchiko\strings\sanitizeStr;

require_once __DIR__ . '/emoteReplacer.php';

class moduleMain extends abstractModuleMain {
	use PostCommentListenerTrait;
	use CommentExtrasListenerTrait;
	use IncludeScriptTrait;
	use FormattingDetailsTrait;

    // The assoc emotes list used for the search and replace in comment
    private array $emotes;
    private array $kaomoji;

    // The url of where emotes are stored in the static emote web directory
    private string $baseEmoteUrl;

    private emoteReplacer $replacer;

	public function getName(): string {
		return 'Emote renderer';
	}

	public function getVersion(): string {
		return 'Version 9001.';
	}

	public function initialize(): void {
		// get emote list from config
		$this->emotes = $this->getModuleConfig('EMOTES', []);
		$this->kaomoji = $this->getModuleConfig('KAOMOJI', []);

        // get base emote url
        $this->baseEmoteUrl = $this->getConfig('STATIC_URL') . 'image/emote/';
        $this->replacer = new emoteReplacer($this->emotes, $this->baseEmoteUrl);

        // add hook point listener for post
		$this->listenPostComment('onRenderComment');

		// render emote picker in post form
		$this->listenCommentExtras('onRenderCommentExtras');

		$this->registerScript('addemotes.js');
	}

	private function onRenderCommentExtras(string &$html): void {
		$html .= $this->renderEmoteButtons();
		$html .= $this->renderKaomojiButtons();
	}

	private function renderEmoteButtons(): string {
		if (empty($this->emotes)) {
			return '';
		}
		$buttons = '';
		foreach ($this->emotes as $emo => $name) {
			$url = sanitizeStr($this->baseEmoteUrl . $name);
			$value = sanitizeStr(':' . $emo . ':');
			$buttons .= '<button type="button" class="buttonEmote emoteButton" title="' . $value . '">'
				. '<img class="emoteImage" src="' . $url . '" loading="lazy" title="' . $value . '" alt="' . $value . '">'
				. '</button>';
		}

		return $this->renderFormattingDetails('emotesContainer', 'Emotes', $buttons);
	}

	private function renderKaomojiButtons(): string {
		if (empty($this->kaomoji)) {
			return '';
		}
		$buttons = '';
		foreach ($this->kaomoji as $display => $value) {
			$escapedValue = sanitizeStr($value);
			$escapedDisplay = sanitizeStr($display);
			$buttons .= '<button type="button" class="buttonSJIS kaomojiButton" title="' . $escapedValue . '" data-value="' . $escapedValue . '">'
				. '<span class="ascii" title="' . $escapedValue . '">' . $escapedDisplay . '</span>'
				. '</button>';
		}

		return $this->renderFormattingDetails('kaomojiContainer', 'Kaomoji', $buttons);
	}

    private function onRenderComment(string &$comment, ?Post $post): void {
        $comment = $this->replacer->replace($comment);
    }
}
