<?php

namespace Kokonotsuba\Modules\wordFilter;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostCommentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeforeCommitListenerTrait;
use Kokonotsuba\post\commentMarker;
use Kokonotsuba\post\Post;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use ModuleHeaderListenerTrait;
	use PostCommentListenerTrait;
	use RegistBeforeCommitListenerTrait;

	/** Marker kind for a colored word. */
	private const MARKER_KIND = 'wordcolor';

	/** Words drawn in random colors, rolled once at post time. */
	private const COLOR_WORDS = ['VAGINA', 'PENIS', 'ANUS'];

	/** The colored words, skipping URLs (autolinked later) and markers already in the comment. */
	private const COLOR_WORD_PATTERN = '~(?:https?://[^\s<\[]+|\[\[koko:[^\]]*\]\])(*SKIP)(*F)|vagina|penis|anus~i';

	private array $FILTERS;

	public function initialize(): void {
		$this->FILTERS = $this->getModuleConfig('FILTERS');
		
		$this->listenPostComment('onRenderComment');

		// The colors are rolled once, at post time
		$this->listenRegistBeforeCommit('onBeforeCommit');

		$this->listenModuleHeader('onModuleHeaderStylesheet');
	}

	/** The module's own stylesheet. */
	private function onModuleHeaderStylesheet(string &$moduleHeader): void {
		$this->includeStylesheet('wordFilter.css', $moduleHeader);
	}
		 
	public function getName(): string {
		return 'K! Word Filter';
	}
		 
	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	/**
	 * Apply the configured filters, then draw the colored words picked at post time.
	 *
	 * The stored comment keeps what was typed; filtering here means a change to FILTERS takes
	 * effect on every post rather than only on new ones. The colored words are markers so their
	 * colors stay the ones rolled when the post was made.
	 *
	 * @param string &$com Comment HTML being assembled.
	 * @param Post|null $post The post being drawn.
	 * @param bool $isThreadView Whether a thread page is being drawn.
	 * @return void
	 */
	private function onRenderComment(string &$com, ?Post $post = null, bool $isThreadView = false): void {
		// Apply each filter in $FILTERS array to user's comment
		foreach ($this->FILTERS as $filterin => $filterout) {
			// apply filter
			$com = preg_replace($filterin, $filterout, $com);
		}

		// Swap the colored word markers for their spans
		$com = commentMarker::expand($com, self::MARKER_KIND, [$this, 'renderColorMarker']);
	}

	/**
	 * Replace each colored word in the comment with a marker holding its rolled colors.
	 *
	 * URLs and existing markers are skipped so autolinking and other modules' markers stay whole.
	 *
	 * @param mixed &$name Poster name.
	 * @param string &$email Email field.
	 * @param string &$emailForInsertion Email as stored.
	 * @param mixed &$sub Subject.
	 * @param string &$com Plain-text comment as stored.
	 * @return void
	 */
	private function onBeforeCommit(&$name, string &$email, string &$emailForInsertion, &$sub, string &$com): void {
		// One pass over the comment for every colored word
		$com = (string)preg_replace_callback(self::COLOR_WORD_PATTERN, [$this, 'colorWordMarker'], $com);
	}

	/**
	 * Build the marker for one matched colored word.
	 *
	 * @param array $match preg match; [0] is the word as typed.
	 * @return string The marker.
	 */
	private function colorWordMarker(array $match): string {
		// The word is always drawn uppercase
		$word = strtoupper($match[0]);

		// Word, background color, text color
		return commentMarker::make(self::MARKER_KIND, $word . ':' . $this->randomHexColor() . ':' . $this->randomHexColor());
	}

	/**
	 * Draw a colored word marker's payload as its span.
	 *
	 * @param string $payload "WORD:rrggbb:rrggbb".
	 * @return string The span, or '' for a malformed payload.
	 */
	public function renderColorMarker(string $payload): string {
		// Split the word from its two colors
		$parts = explode(':', $payload);

		// Only a known word with two hex colors is drawn
		if (!$this->isValidColorPayload($parts)) {
			return '';
		}

		// Unpack the payload
		[$word, $background, $foreground] = $parts;

		// construct span
		return '<span style="background-color: #' . $background . '; color: #' . $foreground . ';">' . sanitizeStr($word) . '</span>';
	}

	/**
	 * Whether a split marker payload is a known word followed by two hex colors.
	 *
	 * @param string[] $parts The payload split on ':'.
	 * @return bool
	 */
	private function isValidColorPayload(array $parts): bool {
		// Exactly a word and two colors
		if (count($parts) !== 3) {
			return false;
		}

		// The word must be one of ours
		$knownWord = in_array($parts[0], self::COLOR_WORDS, true);

		// Both colors must be six hex digits
		$validColors = ctype_xdigit($parts[1]) && strlen($parts[1]) === 6
			&& ctype_xdigit($parts[2]) && strlen($parts[2]) === 6;

		return $knownWord && $validColors;
	}

	/**
	 * A random color as six hex digits.
	 *
	 * @return string e.g. "a0ff3c".
	 */
	private function randomHexColor(): string {
		// generate random color value
		return sprintf('%02x%02x%02x', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
	}
}
