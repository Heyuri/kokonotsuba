<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\cookie\cookieDomain;

/** The domain the visitor token cookie is shared across. */
final class CookieDomainTest extends TestCase {

	public function testSubdomainGivesItsParent(): void {
		$this->assertSame('example.net', cookieDomain::shared('', 'https://boards.example.net/', 'boards.example.net'));
	}

	public function testTwoLabelHostIsUsedAsIs(): void {
		$this->assertSame('example.net', cookieDomain::shared('', 'https://example.net/', 'example.net'));
	}

	public function testRelativeWebsiteUrlFallsBackToTheRequestHost(): void {
		$this->assertSame('example.net', cookieDomain::shared('', '/', 'img.example.net:8080'));
	}

	public function testExplicitDomainWins(): void {
		$this->assertSame('example.co.uk', cookieDomain::shared('.Example.co.uk', 'https://boards.example.co.uk/', 'boards.example.co.uk'));
	}

	/** The browser would refuse a domain the request is not on, leaving no cookie at all. */
	public function testForeignDomainFallsBackToHostOnly(): void {
		$this->assertSame('', cookieDomain::shared('', 'https://boards.example.net/', 'mirror.other.org'));
		$this->assertSame('', cookieDomain::shared('example.net', '/', 'notexample.net'));
	}

	public function testAddressesAndBareHostsAreHostOnly(): void {
		$this->assertSame('', cookieDomain::shared('', '/', '127.0.0.1:8000'));
		$this->assertSame('', cookieDomain::shared('', '/', '[::1]:8000'));
		$this->assertSame('', cookieDomain::shared('', '/', 'localhost'));
		$this->assertSame('', cookieDomain::shared('', '/', ''));
	}
}
