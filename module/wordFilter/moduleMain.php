<?php

namespace Kokonotsuba\Modules\wordFilter;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private array $FILTERS;
	private readonly string $staticUrl;

	public function initialize(): void {
		$this->staticUrl = $this->getConfig('STATIC_URL');
		
		$this->FILTERS = $this->getConfig('ModuleSettings.FILTERS');
		
		$this->addEmojiFilters();

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			$this->onBeforeCommit($com);  // Call the method to modify the form
		});
	}
		 
	public function getName(): string {
		return 'K! Word Filter';
	}
		 
	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	private function addEmojiFilters() {
		// get the emoji array from the file
		$emojis = require __DIR__ . '/emojis.php';
		
		// loop through and add emoji filters
		foreach ($emojis as $char => $name) {
			// only apply person pattern if the emoji name contains 'person'
			if (str_contains($name, 'Person')) {
				// generate pattern (with capturing group)
				$pattern = '(' . $this->buildPersonEmojiPattern($char) . ')';
			} 
			// default to regular pattern
			else {
				// escape the char and wrap in a capturing group
				$pattern = '(' . preg_quote($char, '/') . ')';
			}

			// add filter — use $1 (matched text) for the alt attribute
			$this->FILTERS["/$pattern/u"] =
				"<img class=\"emoji\" src=\"" . $this->staticUrl . "image/emoji/$name.gif\" title=\"$name\" alt=\"$1\">";
		}
	}

	private function firstCodepointPattern(string $s): string {
		$ucs4 = mb_convert_encoding($s, 'UCS-4BE', 'UTF-8');
		
		if ($ucs4 === false || strlen($ucs4) < 4) {
			return preg_quote($s, '/');
		}

		$cp = current(unpack('N', substr($ucs4, 0, 4)));
		
		return sprintf('\\x{%X}', $cp);
	}

	private function buildPersonEmojiPattern(string $char): string {
		$base	= $this->firstCodepointPattern($char);	// e.g. \x{1F645}
		$skin	= '[\x{1F3FB}-\x{1F3FF}]?';				// optional skin tone
		$vs		= '\x{FE0F}?';							// optional VS16
		$keycap	= '\x{20E3}?';							// optional keycap combining mark

		// After stripping ZWJ+gender, we only need base + optional skin tone + optional VS16 (+ optional keycap)
		return '(?:' . $base . $skin . $vs . $keycap . ')';
	}

	private function cleanEmojiArtifacts(string $comment): string {
		// Remove ZWJ + gender or standalone gender signs that remain
		return preg_replace(
			[
				'/\x{200D}(?:\x{2640}|\x{2642})\x{FE0F}?/u',	// joined gender sequence
				'/(?:\x{2640}|\x{2642})\x{FE0F}?/u'			// leftover gender alone
			],
			'',
			$comment
		);
	}

	public function onBeforeCommit(&$com): void {
		//VAGINA filter
		$this->FILTERS['/vagina/i'] = $this->generateColorSpan('VAGINA');
		 
		//PENIS filter
		$this->FILTERS['/penis/i'] = $this->generateColorSpan('PENIS');
		 
		//ANUS filter
		$this->FILTERS['/anus/i'] = $this->generateColorSpan('ANUS');
		 
		// Apply each filter in $FILTERS array to user's comment
		foreach ($this->FILTERS as $filterin => $filterout) {
			// apply filter
			$com = preg_replace($filterin, $filterout, $com);

			// clean artifacts (gender and race modifiers)
			$com = $this->cleanEmojiArtifacts($com);
		}
	}

	private function generateColorSpan(string $textContent): string {
		// generate random color values
		$red5 = mt_rand(0, 255);
		$green5 = mt_rand(0, 255);
		$blue5 = mt_rand(0, 255);
		$red6 = mt_rand(0, 255);
		$green6 = mt_rand(0, 255);
		$blue6 = mt_rand(0, 255);
				
		// style for span
		$bgstyle = sprintf('background-color: rgb(%d, %d, %d);', $red5, $green5, $blue5);
		$fontstyle = sprintf('color: rgb(%d, %d, %d);', $red6, $green6, $blue6);

		// construct span
		$span = '<span style="' . $bgstyle . ' ' . $fontstyle . '"> ' . htmlspecialchars($textContent) . '</span>';

		// return the span html
		return $span;
	}
}
