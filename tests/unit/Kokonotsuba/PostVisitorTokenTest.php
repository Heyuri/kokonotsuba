<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\postRegistData;
use Kokonotsuba\post\textFormat;

/**
 * What a post says about the browser that made it.
 *
 * Three states, and RECORD_POST_BROWSER depends on them staying distinct: a token hash, an
 * empty string meaning the browser was asked and kept nothing, and null meaning it was never
 * asked - a board with recording switched off, or a post from before the column existed. A board
 * that turns recording off must not end up telling staff that everyone posting there refuses
 * cookies.
 */
final class PostVisitorTokenTest extends TestCase {

	private function post(mixed $stored, bool $present = true): Post {
		return new Post($present ? ['visitor_token_hash' => $stored] : []);
	}

	public function testATokenHashReadsBackVerbatim(): void {
		$post = $this->post('a3f9c1d2b4e60718');

		$this->assertTrue($post->hasVisitorTokenHash(), 'a recorded browser read as unrecorded');
		$this->assertSame('a3f9c1d2b4e60718', $post->getVisitorTokenHash());
	}

	/** The browser was asked and handed nothing back. That is a fact about it, so it is kept. */
	public function testACookielessPostIsRecordedAsSuch(): void {
		$post = $this->post('');

		$this->assertTrue($post->hasVisitorTokenHash(), 'a cookieless post read as never recorded');
		$this->assertSame('', $post->getVisitorTokenHash());
	}

	/** Nothing was asked, so nothing is claimed - the case a board with recording off produces. */
	public function testAnUnrecordedPostClaimsNothing(): void {
		$post = $this->post(null);

		$this->assertFalse($post->hasVisitorTokenHash(), 'null read as a recorded value');
		$this->assertSame('', $post->getVisitorTokenHash());
	}

	/** A query that never selected the column is unrecorded too, not cookieless. */
	public function testAnAbsentColumnIsNotMistakenForCookieless(): void {
		$post = $this->post(null, false);

		$this->assertFalse($post->hasVisitorTokenHash(), 'an unselected column read as recorded');
	}

	/**
	 * The switch works by handing the row a null, so that null has to survive all the way into
	 * the insert parameters rather than being flattened to an empty string on the way.
	 */
	public function testRecordingOffStoresNullNotAnEmptyString(): void {
		$params = $this->registData(null)->toParams(1, '2026-01-01 00:00:00');

		$this->assertNull($params[':visitor_token_hash'], 'a board with recording off stored a value');
	}

	public function testRecordingOnStoresWhatItWasGiven(): void {
		foreach (['a3f9c1d2b4e60718', ''] as $recorded) {
			$params = $this->registData($recorded)->toParams(1, '2026-01-01 00:00:00');

			$this->assertSame($recorded, $params[':visitor_token_hash']);
		}
	}

	private function registData(?string $tokenHash): postRegistData {
		return new postRegistData(
			1, '', 'thread', 1, '', '', 'pwd', 'now', 'name', '', '', '', 'email', 'sub', 'com',
			'1.2.3.4', false, '', textFormat::PLAIN_TEXT, 0, $tokenHash
		);
	}
}
