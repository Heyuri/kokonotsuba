<?php

namespace Koko\Tests\Unit\Puchiko;

use Koko\Tests\Framework\TestCase;

use function Puchiko\request\absoluteUrl;

/**
 * Unit tests for the Puchiko\request helpers.
 */
final class RequestHelpersTest extends TestCase {

	public function testLeavesAbsoluteUrlsUntouched(): void {
		$this->assertSame(
			'https://example.net/boards/test/koko.php',
			absoluteUrl('https://example.net/boards/test/koko.php', 'http', 'localhost', '/koko.php')
		);
		$this->assertSame(
			'http://other.example/x',
			absoluteUrl('http://other.example/x', 'https', 'localhost', '/koko.php')
		);
	}

	public function testResolvesRootRelativeUrls(): void {
		$this->assertSame(
			'http://localhost/kokonotsuba/boards/test/koko.php?mode=module',
			absoluteUrl('/kokonotsuba/boards/test/koko.php?mode=module', 'http', 'localhost', '/kokonotsuba/boards/test/koko.php')
		);
	}

	public function testKeepsSchemeAndPortOfCurrentRequest(): void {
		$this->assertSame(
			'https://example.net:8443/boards/test/',
			absoluteUrl('/boards/test/', 'https', 'example.net:8443', '/boards/test/koko.php')
		);
	}

	public function testResolvesProtocolRelativeUrls(): void {
		$this->assertSame(
			'https://cdn.example/image/banner.png',
			absoluteUrl('//cdn.example/image/banner.png', 'https', 'localhost', '/koko.php')
		);
	}

	public function testResolvesDocumentRelativeUrlsAgainstCurrentDirectory(): void {
		$this->assertSame(
			'http://localhost/boards/test/koko.php?load=banner',
			absoluteUrl('koko.php?load=banner', 'http', 'localhost', '/boards/test/koko.php')
		);
		// A directory-shaped document path keeps its trailing slash.
		$this->assertSame(
			'http://localhost/boards/test/koko.php',
			absoluteUrl('koko.php', 'http', 'localhost', '/boards/test/')
		);
	}

	public function testDocumentPathWithoutSlashResolvesFromRoot(): void {
		$this->assertSame(
			'http://localhost/koko.php',
			absoluteUrl('koko.php', 'http', 'localhost', 'koko.php')
		);
	}

	public function testReturnsInputWhenHostIsUnknown(): void {
		// CLI and rebuild contexts have no Host header; a relative URL is better than a broken one.
		$this->assertSame('/boards/test/', absoluteUrl('/boards/test/', 'http', '', '/koko.php'));
	}

	public function testQueryStringColonIsNotMistakenForAScheme(): void {
		$this->assertSame(
			'http://localhost/b/koko.php?t=12:30',
			absoluteUrl('koko.php?t=12:30', 'http', 'localhost', '/b/koko.php')
		);
	}
}
