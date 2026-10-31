<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\front_pages\frontPageService;

/** Front page URLs built on HOME, and the page slug rules. */
final class FrontPageLinksTest extends TestCase {
	public function testLinksAppendToHome(): void {
		$links = new frontPageLinks('https://example.net/');

		$this->assertSame('https://example.net/?p=faq', $links->page('faq'));
		$this->assertSame('https://example.net/?news=12', $links->news(12));
		$this->assertSame('https://example.net/?page=3', $links->newsPage(3));
	}

	public function testTheFirstNewsPageIsHomeItself(): void {
		$this->assertSame('https://example.net/', (new frontPageLinks('https://example.net/'))->newsPage(1));
	}

	public function testAHomeWithAQueryOrFragmentStillWorks(): void {
		$this->assertSame('/index.php?lang=en&p=faq', (new frontPageLinks('/index.php?lang=en'))->page('faq'));
		$this->assertSame('/?news=4', (new frontPageLinks('/#top'))->news(4));
	}

	public function testSlugsAreEncoded(): void {
		$this->assertSame('/?p=a%26b', (new frontPageLinks('/'))->page('a&b'));
	}

	public function testSlugRules(): void {
		$this->assertTrue(frontPageService::isValidSlug('faq'));
		$this->assertTrue(frontPageService::isValidSlug('japanese_2-old'));
		$this->assertFalse(frontPageService::isValidSlug(''));
		$this->assertFalse(frontPageService::isValidSlug('FAQ'));
		$this->assertFalse(frontPageService::isValidSlug('with space'));
		$this->assertFalse(frontPageService::isValidSlug(str_repeat('a', 65)));
		$this->assertFalse(frontPageService::isValidSlug('news'));
	}

	public function testSlugsAreNormalizedBeforeTheyAreChecked(): void {
		$this->assertSame('faq', frontPageService::normalizeSlug('  FAQ '));
	}
}
