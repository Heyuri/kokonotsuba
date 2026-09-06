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

	public function testDotfilesAndBoardUidsAreDeniedButBoardsAreNot(): void {
		$snippet = webServerRules::nginxSnippet('/koko/');
		$this->assertSame(1, preg_match('/^location ~ (\S*ini\$\S*) \{$/m', $snippet, $matches));
		$regex = '#'.$matches[1].'#';

		$this->assertSame(1, preg_match($regex, '/koko/.git/config'));
		$this->assertSame(1, preg_match($regex, '/koko/boards/b/boardUID.ini'));
		$this->assertSame(0, preg_match($regex, '/koko/boards/b/koko.php'));
		$this->assertSame(0, preg_match($regex, '/koko/boards/b/src/1.png'));
		$this->assertSame(0, preg_match($regex, '/koko/static/js/postWidget.js'));
	}

	public function testEveryDeniedDirectoryShipsAnHtaccess(): void {
		foreach (webServerRules::DENIED_DIRECTORIES as $directory) {
			$path = KOKO_TEST_ROOT.'/'.$directory.'/.htaccess';

			$this->assertTrue(is_file($path), $directory.'/.htaccess is missing');
			$this->assertStringContains('Require all denied', (string)file_get_contents($path));
		}
	}

	public function testTheRootHtaccessDeniesTheCredentialsFileAndIniFiles(): void {
		$htaccess = (string)file_get_contents(KOKO_TEST_ROOT.'/.htaccess');

		$this->assertStringContains('databaseSettings', $htaccess);
		$this->assertStringContains('ini', $htaccess);
		$this->assertStringContains('RewriteRule ^koko\\.php$ - [F]', $htaccess);
		$this->assertStringNotContains('|koko|', $htaccess);
		$this->assertStringContains('Options -Indexes', $htaccess);
	}

	public function testTheApacheSnippetTurnsOnOverridesForTheInstallDirectory(): void {
		$snippet = webServerRules::apacheSnippet('/var/www/html/kokonotsuba/');

		$this->assertStringContains('<Directory "/var/www/html/kokonotsuba">', $snippet);
		$this->assertStringContains('AllowOverride All', $snippet);
	}
}
