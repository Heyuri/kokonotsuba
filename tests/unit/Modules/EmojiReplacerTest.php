<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\emoji\emojiReplacer;

require_once KOKO_TEST_ROOT . '/module/emoji/emojiReplacer.php';

/** One pass over the comment replaces every emoji character with its image. */
final class EmojiReplacerTest extends TestCase {

	private function replacer(array $emojis = ['😄' => 'Grinning-Face', '❤' => 'Red-Heart', '🙆' => 'Person-OK', '🙆‍♀️' => 'Woman-OK']): emojiReplacer {
		return new emojiReplacer($emojis, 'https://static.example/');
	}

	public function testReplacesACharacterWithItsImage(): void {
		$this->assertSame(
			'hi <img class="emoji" src="https://static.example/image/emoji/Grinning-Face.gif" title="Grinning-Face" alt="😄"> there',
			$this->replacer()->replace('hi 😄 there')
		);
	}

	public function testTextWithoutEmojiIsUntouched(): void {
		$text = "plain &lt;text&gt; with 'quotes'<br>and a 🐱 nobody knows";

		$this->assertSame($text, $this->replacer()->replace($text));
		$this->assertSame($text, (new emojiReplacer([], 'x/'))->replace($text));
	}

	public function testEveryOccurrenceIsReplacedAndNoneTwice(): void {
		$out = $this->replacer()->replace('❤❤ and ❤');

		$this->assertSame(3, substr_count($out, 'class="emoji"'));
		$this->assertSame(3, substr_count($out, 'alt="❤"'));
	}

	/** A joined sequence must win over the base character it starts with, whatever the map order. */
	public function testTheLongestSequenceWins(): void {
		$out = $this->replacer()->replace('🙆‍♀️ then 🙆');

		$this->assertSame(1, substr_count($out, 'Woman-OK.gif'));
		$this->assertSame(1, substr_count($out, 'Person-OK.gif'));
		// the joiner survives only inside the sequence image's alt text
		$this->assertSame(1, substr_count($out, "\u{200D}"));
	}

	public function testUrlAndNameAreEscaped(): void {
		$out = (new emojiReplacer(['😄' => 'a"b'], 'https://s/?x=1&y=2/'))->replace('😄');

		$this->assertStringContains('src="https://s/?x=1&amp;y=2/image/emoji/a&quot;b.gif"', $out);
		$this->assertStringNotContains('a"b', $out);
	}
}
