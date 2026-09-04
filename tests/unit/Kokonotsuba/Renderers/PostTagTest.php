<?php

namespace Koko\Tests\Unit\Kokonotsuba\Renderers;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\renderers\post\postTag;

/** The tag drawn on a post, resolved against the board's tag list. */
final class PostTagTest extends TestCase {

	private const CONFIG = [
		'ENABLE_TAGS' => true,
		'DEFAULT_TAG' => 'gen',
		'TAGS' => ['gen' => 'General', 'art' => 'Art & <Design>'],
	];

	public function testNothingWhenTagsAreOff(): void {
		$tag = postTag::resolve(['ENABLE_TAGS' => false] + self::CONFIG, 'art', true);

		$this->assertSame('', $tag->label);
		$this->assertSame('', $tag->title);
	}

	public function testListedTagGetsItsTitle(): void {
		$tag = postTag::resolve(self::CONFIG, 'gen', false);

		$this->assertSame('gen', $tag->label);
		$this->assertSame('General', $tag->title);
	}

	public function testOpFallsBackToTheDefaultTag(): void {
		$tag = postTag::resolve(self::CONFIG, '', true);

		$this->assertSame('gen', $tag->label);
	}

	public function testReplyWithoutTagStaysUntagged(): void {
		$tag = postTag::resolve(self::CONFIG, '', false);

		$this->assertSame('', $tag->label);
		$this->assertSame('', $tag->title);
	}

	public function testUnlistedTagIsMarkedUnknown(): void {
		$tag = postTag::resolve(self::CONFIG, 'gone', false);

		$this->assertSame('[?]', $tag->label);
		$this->assertSame('???', $tag->title);
	}

	public function testTitleIsEscaped(): void {
		$tag = postTag::resolve(self::CONFIG, 'art', false);

		$this->assertStringNotContains('<', $tag->title);
		$this->assertStringContains('&lt;Design&gt;', $tag->title);
	}
}
