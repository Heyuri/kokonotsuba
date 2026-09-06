<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\installDefaults;

/** Working out the site's own URLs from the request that reached install.php. */
class InstallDefaultsTest extends TestCase {

	private function detect(array $server): installDefaults {
		return installDefaults::detect($server, '/var/www/html/kokonotsuba');
	}

	public function testBuildsTheUrlsFromASubdirectoryInstall(): void {
		$defaults = $this->detect([
			'HTTP_HOST' => 'example.net',
			'SCRIPT_NAME' => '/kokonotsuba/install.php',
			'HTTPS' => 'on',
		]);

		$this->assertSame('https://example.net/kokonotsuba/', $defaults->baseUrl());
		$this->assertSame('https://example.net/kokonotsuba/boards/', $defaults->websiteUrl());
		$this->assertSame('https://example.net/kokonotsuba/static/', $defaults->staticUrl());
		// The home link defaults to the site root, not to the boards directory.
		$this->assertSame('https://example.net/', $defaults->homeUrl());
		$this->assertSame('/var/www/html/kokonotsuba/static/', $defaults->staticPath());
		$this->assertSame('/var/www/html/kokonotsuba/boards/', $defaults->boardsPath());
	}

	public function testHandlesAnInstallInTheWebRoot(): void {
		$defaults = $this->detect(['HTTP_HOST' => 'example.net', 'SCRIPT_NAME' => '/install.php']);

		$this->assertSame('/', $defaults->urlPrefix);
		$this->assertSame('http://example.net/', $defaults->baseUrl());
		$this->assertSame('http://example.net/boards/', $defaults->websiteUrl());
	}

	public function testTreatsHttpsOffAsPlainHttp(): void {
		$defaults = $this->detect(['HTTP_HOST' => 'example.net', 'SCRIPT_NAME' => '/install.php', 'HTTPS' => 'off']);

		$this->assertSame('http', $defaults->scheme);
	}

	public function testHonoursTheProxyProtocolHeader(): void {
		$defaults = $this->detect([
			'HTTP_HOST' => 'example.net',
			'SCRIPT_NAME' => '/install.php',
			'HTTP_X_FORWARDED_PROTO' => 'https, http',
		]);

		$this->assertSame('https', $defaults->scheme);
	}

	public function testReadsPort443AsHttps(): void {
		$defaults = $this->detect(['HTTP_HOST' => 'example.net', 'SCRIPT_NAME' => '/install.php', 'SERVER_PORT' => '443']);

		$this->assertSame('https', $defaults->scheme);
	}

	public function testStripsInjectionAttemptsFromTheHostHeader(): void {
		$defaults = $this->detect([
			'HTTP_HOST' => "example.net\r\nX-Evil: 1",
			'SCRIPT_NAME' => '/install.php',
		]);

		$this->assertSame('example.netX-Evil:1', $defaults->host);
		$this->assertStringNotContains("\n", $defaults->baseUrl());
	}

	public function testKeepsAPortInTheHost(): void {
		$defaults = $this->detect(['HTTP_HOST' => 'localhost:8080', 'SCRIPT_NAME' => '/install.php']);

		$this->assertSame('http://localhost:8080/', $defaults->baseUrl());
	}

	public function testFallsBackToServerNameAndLocalhost(): void {
		$this->assertSame('srv.local', $this->detect(['SERVER_NAME' => 'srv.local', 'SCRIPT_NAME' => '/install.php'])->host);
		$this->assertSame('localhost', $this->detect(['SCRIPT_NAME' => '/install.php'])->host);
	}

	public function testTrimsTheTrailingSlashOffTheAppRoot(): void {
		$defaults = installDefaults::detect(['SCRIPT_NAME' => '/install.php'], '/srv/koko/');

		$this->assertSame('/srv/koko/static/', $defaults->staticPath());
	}
}
