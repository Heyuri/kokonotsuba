<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\emotes\emoteReplacer;

require_once KOKO_TEST_ROOT . '/module/emotes/emoteReplacer.php';

/** One pass over the comment replaces every :code: with its image and leaves markup alone. */
final class EmoteReplacerTest extends TestCase {

	private function replacer(): emoteReplacer {
		return new emoteReplacer([
			'angry' => 'angry.gif',
			'nyaoo' => 'nyaoo.gif',
			'nyaoo2' => 'nyaoo2.gif',
			'Glare' => 'glare.gif',
		], 'https://static.example/image/emote/');
	}

	public function testReplacesACodeWithItsImage(): void {
		$this->assertSame(
			'so <img title=":angry:" class="emote" src="https://static.example/image/emote/angry.gif" alt=":angry:"> now',
			$this->replacer()->replace('so :angry: now')
		);
	}

	public function testMatchesCaseInsensitivelyButKeepsTheConfiguredName(): void {
		$out = $this->replacer()->replace(':ANGRY: :glare:');

		$this->assertSame(2, substr_count($out, 'class="emote"'));
		$this->assertStringContains('title=":angry:"', $out);
		$this->assertStringContains('title=":Glare:"', $out);
	}

	public function testACodeInsideATagIsLeftAlone(): void {
		$in = '<a href="http://x/:angry:/y">:angry:</a>';
		$out = $this->replacer()->replace($in);

		$this->assertStringContains('href="http://x/:angry:/y"', $out);
		$this->assertSame(1, substr_count($out, 'class="emote"'));
	}

	public function testUnknownCodesAndPlainTextAreUntouched(): void {
		$this->assertSame(':nope: 10:30 a:b', $this->replacer()->replace(':nope: 10:30 a:b'));
		$this->assertSame('no colons here', $this->replacer()->replace('no colons here'));
		$this->assertSame(':angry:', (new emoteReplacer([], 'x/'))->replace(':angry:'));
	}

	public function testALongerCodeIsNotCutShortByAPrefix(): void {
		$out = $this->replacer()->replace(':nyaoo2: :nyaoo:');

		$this->assertSame(1, substr_count($out, 'nyaoo2.gif'));
		$this->assertSame(1, substr_count($out, '/nyaoo.gif'));
	}
}
