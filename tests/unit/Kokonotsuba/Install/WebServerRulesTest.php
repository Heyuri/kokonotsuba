<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\webServerRules;

/** The nginx rules printed by the installer, and the .htaccess files shipped beside them. */
class WebServerRulesTest extends TestCase {

	public function testTheSnippetCarriesTheInstallsOwnPrefix(): void {
		$snippet = webServerRules::nginxSnippet('/kokonotsuba/');

		$this->assertStringContains('location ~ ^/kokonotsuba/(bootstrap|code|', $snippet);
		$this->assertStringContains('deny all;', $snippet);
	}

	public function testAWebRootInstallGetsNoPrefix(): void {
		$snippet = webServerRules::nginxSnippet('/');

		$this->assertStringContains('location ~ ^/(bootstrap|code|', $snippet);
		$this->assertStringNotContains('^//', $snippet);
	}

	public function testEveryDeniedDirectoryAppears(): void {
		$snippet = webServerRules::nginxSnippet('/koko/');

		foreach (webServerRules::DENIED_DIRECTORIES as $directory) {
			$this->assertStringContains($directory, $snippet);
		}
	}

	public function testFileDotsAreEscapedForTheRegex(): void {
		$snippet = webServerRules::nginxSnippet('/koko/');

		$this->assertStringContains('databaseSettings\.php', $snippet);
	}

	public function testTheInstallerItselfIsNotDenied(): void {
		$this->assertStringNotContains('install.php', webServerRules::nginxSnippet('/koko/'));
		$this->assertFalse(in_array('install.php', webServerRules::DENIED_FILES, true));
	}

	public function testDotfilesAreDenied(): void {
		$this->assertStringContains('/\.', webServerRules::nginxSnippet('/koko/'));
	}

	public function testEveryDeniedDirectoryShipsAnHtaccess(): void {
		foreach (webServerRules::DENIED_DIRECTORIES as $directory) {
			$path = KOKO_TEST_ROOT.'/'.$directory.'/.htaccess';

			$this->assertTrue(is_file($path), $directory.'/.htaccess is missing');
			$this->assertStringContains('Require all denied', (string)file_get_contents($path));
		}
	}

	public function testTheRootHtaccessDeniesTheCredentialsFile(): void {
		$htaccess = (string)file_get_contents(KOKO_TEST_ROOT.'/.htaccess');

		$this->assertStringContains('databaseSettings', $htaccess);
		$this->assertStringContains('Options -Indexes', $htaccess);
	}
}
