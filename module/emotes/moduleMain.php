<?php

namespace Kokonotsuba\Modules\emotes;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostCommentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\CommentExtrasListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\FormattingDetailsTrait;

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

	public function getName(): string {
		return 'Emote renderer';
	}

	public function getVersion(): string {
		return 'Version 9001.';
	}

	public function initialize(): void {
		// get emote list from config
		$this->emotes = $this->getConfig('ModuleSettings.EMOTES', []);
		$this->kaomoji = $this->getConfig('ModuleSettings.KAOMOJI', []);

        // get base emote url
        $this->baseEmoteUrl = $this->getConfig('STATIC_URL') . 'image/emote/';

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
			$url = htmlspecialchars($this->baseEmoteUrl . $name);
			$value = htmlspecialchars(':' . $emo . ':');
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
			$escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
			$escapedDisplay = htmlspecialchars($display, ENT_QUOTES, 'UTF-8');
			$buttons .= '<button type="button" class="buttonSJIS kaomojiButton" title="' . $escapedValue . '" data-value="' . $escapedValue . '">'
				. '<span class="ascii" title="' . $escapedValue . '">' . $escapedDisplay . '</span>'
				. '</button>';
		}

		return $this->renderFormattingDetails('kaomojiContainer', 'Kaomoji', $buttons);
	}

    private function onRenderComment(string &$comment): void {
        // modify rendered comment to include emotes
        $this->searchAndReplaceEmotes($comment);
    }

    private function searchAndReplaceEmotes(string &$comment): void {
        // loop through comment and str replace
        foreach ($this->emotes as $emo=>$name) {
            // build url
            $url = $this->baseEmoteUrl . $name;

            // perform replacement outside img tags
            $comment = preg_replace_callback(
                '/<img\b[^>]*>|:(?:' . preg_quote($emo, '/') . '):/i',
                function ($m) use ($emo, $url) {
                    // if it's an img tag, return unchanged
                    if (str_starts_with($m[0], '<img')) {
                        return $m[0];
                    }
                    // otherwise replace the emote
                    return "<img title=\":$emo:\" class=\"emote\" src=\"$url\" alt=\":$emo:\">";
                },
                $comment
            );

        }
    }
}