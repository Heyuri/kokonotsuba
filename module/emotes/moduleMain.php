<?php

namespace Kokonotsuba\Modules\emotes;

use board;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
    // The assoc emotes list used for the search and replace in comment
    private array $emotes;

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

        // get base emote url
        $this->baseEmoteUrl = $this->getConfig('STATIC_URL') . 'image/emote/';

        // add hook point listener for post
		$this->moduleContext->moduleEngine->addListener('PostComment', function(string &$postComment, array &$post) {
			$this->onRenderComment($postComment);
		});
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