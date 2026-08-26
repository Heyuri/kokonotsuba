<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\siteSettings;

/** The generated global/siteSettings.php overlay that sits on top of globalconfig.php. */
class SiteSettingsTest extends TestCase {

	private string $dir = '';

	private string $errorLog = '';

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir().'/koko-site-settings-'.bin2hex(random_bytes(4));
		mkdir($this->dir, 0777, true);

		// A malformed overlay is reported through error_log(); keep that out of the test output.
		$this->errorLog = (string)ini_get('error_log');
		ini_set('error_log', $this->dir.'/error.log');

		siteSettings::forget();
	}

	protected function tearDown(): void {
		ini_set('error_log', $this->errorLog);

		foreach (glob($this->dir.'/*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->dir);
		siteSettings::forget();
	}

	private function writeSettings(string $body): string {
		$path = $this->dir.'/siteSettings-'.bin2hex(random_bytes(4)).'.php';
		file_put_contents($path, "<?php\n\nreturn {$body};\n");

		return $path;
	}

	public function testAnAbsentFileIsNotAnError(): void {
		$this->assertSame([], siteSettings::load($this->dir.'/nothing-here.php'));
	}

	public function testAFileThatDoesNotReturnAnArrayIsIgnored(): void {
		$path = $this->writeSettings("'a string'");

		$this->assertSame([], siteSettings::load($path));
	}

	public function testLoadsTheKnownKeys(): void {
		$path = $this->writeSettings("['WEBSITE_URL' => 'https://example.net/boards/', 'TRIPSALT' => 'abc']");

		$this->assertSame(
			['WEBSITE_URL' => 'https://example.net/boards/', 'TRIPSALT' => 'abc'],
			siteSettings::load($path)
		);
	}

	public function testIgnoresKeysOutsideTheAllowlist(): void {
		$path = $this->writeSettings("['AuthLevels' => ['CAN_BAN' => 0], 'TRIPSALT' => 'abc']");

		$this->assertSame(['TRIPSALT' => 'abc'], siteSettings::load($path));
	}

	public function testPutsTrailingSlashesBackOnPathsAndUrls(): void {
		$normalized = siteSettings::normalize([
			'WEBSITE_URL' => 'https://example.net/boards',
			'STATIC_PATH' => '/var/www/static',
			'CDN_URL' => 'https://cdn.example.net///',
		]);

		$this->assertSame('https://example.net/boards/', $normalized['WEBSITE_URL']);
		$this->assertSame('/var/www/static/', $normalized['STATIC_PATH']);
		$this->assertSame('https://cdn.example.net/', $normalized['CDN_URL']);
	}

	public function testAnEmptyValueLeavesTheDefaultAlone(): void {
		$normalized = siteSettings::normalize(['TRIPSALT' => '', 'IDSEED' => '   ', 'WEBSITE_URL' => '/boards/']);

		$this->assertSame(['WEBSITE_URL' => '/boards/'], $normalized);
	}

	public function testTheHomeLinkKeepsItsValueVerbatim(): void {
		// Unlike the URL prefixes, HOME is a link target: 'index.html' must not gain a slash.
		$normalized = siteSettings::normalize(['HOME' => 'index.html']);

		$this->assertSame('index.html', $normalized['HOME']);
		$this->assertSame('https://example.net/', siteSettings::normalize(['HOME' => 'https://example.net/'])['HOME']);
	}

	public function testTheHomeLinkFeedsTheBoardConfigDefault(): void {
		// configs/appearance.php takes its HOME default from the resolved global config, so the
		// site-wide setting reaches every board that has not overridden it.
		$this->assertSame(getGlobalConfig()['HOME'], configSchema::getFieldMeta('HOME')['default']);
	}

	public function testUseCdnBecomesABoolean(): void {
		$this->assertSame(false, siteSettings::normalize(['USE_CDN' => 0])['USE_CDN']);
		$this->assertSame(true, siteSettings::normalize(['USE_CDN' => '1'])['USE_CDN']);
	}

	public function testNonScalarValuesAreDropped(): void {
		$this->assertSame([], siteSettings::normalize(['STATIC_URL' => ['not', 'a', 'string']]));
	}

	public function testTheReadIsCachedUntilForgotten(): void {
		$path = $this->writeSettings("['TRIPSALT' => 'first']");
		$this->assertSame('first', siteSettings::load($path)['TRIPSALT']);

		file_put_contents($path, "<?php\n\nreturn ['TRIPSALT' => 'second'];\n");
		$this->assertSame('first', siteSettings::load($path)['TRIPSALT'], 'cached within the request');

		siteSettings::forget($path);
		$this->assertSame('second', siteSettings::load($path)['TRIPSALT']);
	}

	public function testGlobalConfigTakesItsDefaultsWhenThereIsNoOverlay(): void {
		$config = getGlobalConfig();

		// The shipped tree has no siteSettings.php, so globalconfig's own values stand.
		$this->assertSame('/', $config['WEBSITE_URL']);
		$this->assertStringContains('static', $config['STATIC_URL']);
		$this->assertSame($config['STATIC_URL'].'image/audio.png', $config['AUDIO_THUMB']);
	}
}
