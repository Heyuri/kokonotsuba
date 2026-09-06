<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\checkResult;
use Kokonotsuba\install\systemRequirements;

/** PHP version, extension, binary and php.ini reporting, with every probe stubbed. */
class SystemRequirementsTest extends TestCase {

	/**
	 * @param list<string>          $extensions Extensions to report as loaded.
	 * @param list<string>          $binaries   Binaries to report as on PATH.
	 * @param array<string, string> $ini        php.ini values.
	 */
	private function check(string $phpVersion = '8.3.0', array $extensions = [], array $binaries = [], array $ini = []): array {
		$defaultIni = [
			'file_uploads' => '1',
			'upload_max_filesize' => '8M',
			'post_max_size' => '16M',
			'memory_limit' => '256M',
		];
		$ini = array_merge($defaultIni, $ini);

		$results = (new systemRequirements(
			$phpVersion,
			static fn (string $name): bool => in_array($name, $extensions, true),
			static fn (string $name): bool => in_array($name, $binaries, true),
			static fn (string $key) => $ini[$key] ?? false
		))->check();

		$byLabel = [];
		foreach ($results as $result) {
			$byLabel[$result->label] = $result;
		}

		return $byLabel;
	}

	private const ALL_EXTENSIONS = ['mbstring', 'pdo', 'pdo_mysql', 'gd', 'bcmath', 'json', 'fileinfo', 'posix', 'curl'];

	/** The PHP version check, for a version given as a string. */
	private function phpVersionResult(string $phpVersion): checkResult {
		return $this->check($phpVersion, self::ALL_EXTENSIONS)['PHP '.$phpVersion];
	}

	public function testAFullyEquippedHostPassesEverything(): void {
		$results = $this->check(
			systemRequirements::TESTED_PHP_MIN.'.0',
			self::ALL_EXTENSIONS,
			['ffmpeg', 'exiftool']
		);

		foreach ($results as $label => $result) {
			$this->assertSame(checkResult::OK, $result->status, $label.': '.$result->detail);
		}
	}

	public function testTooOldPhpFails(): void {
		$result = $this->phpVersionResult('8.0.30');

		$this->assertSame(checkResult::FAIL, $result->status);
		$this->assertStringContains(systemRequirements::MIN_PHP_VERSION.' or newer', $result->detail);
	}

	/**
	 * Both ends of the range are inclusive, so the last patch of the top series still passes.
	 * Written against the constants: moving the range must not mean editing these.
	 */
	public function testBothEndsOfTheTestedRangeAreAccepted(): void {
		foreach ([systemRequirements::TESTED_PHP_MIN.'.0', systemRequirements::TESTED_PHP_MAX.'.99'] as $version) {
			$result = $this->phpVersionResult($version);

			$this->assertSame(checkResult::OK, $result->status, $version.': '.$result->detail);
			$this->assertStringContains(systemRequirements::testedRangeLabel(), $result->detail);
		}
	}

	public function testNewerThanTheTestedRangeOnlyWarns(): void {
		$result = $this->phpVersionResult(systemRequirements::untestedFromVersion());

		$this->assertSame(checkResult::WARN, $result->status);
		$this->assertStringContains('Newer than the tested range', $result->detail);
	}

	/** Still supported, just no longer exercised, so it warns rather than blocking the install. */
	public function testOlderThanTheTestedRangeWarnsButStillInstalls(): void {
		$result = $this->phpVersionResult(systemRequirements::MIN_PHP_VERSION);

		$this->assertSame(checkResult::WARN, $result->status);
		$this->assertStringContains('Older than the tested range', $result->detail);
	}

	/**
	 * The range this release actually declares, spelled out.
	 *
	 * The only test here that names versions, and the only one to edit when the range moves -
	 * which is the point: changing what is tested should be a deliberate line in a test, not a
	 * silent constant edit.
	 */
	public function testTheDeclaredTestedRange(): void {
		$this->assertSame('8.3', systemRequirements::TESTED_PHP_MIN);
		$this->assertSame('8.5', systemRequirements::TESTED_PHP_MAX);
		$this->assertSame('8.3 to 8.5', systemRequirements::testedRangeLabel());
		$this->assertSame('8.6.0', systemRequirements::untestedFromVersion());
	}

	public function testAMissingRequiredExtensionFailsWithAnInstallCommand(): void {
		$result = $this->check('8.3.0', array_diff(self::ALL_EXTENSIONS, ['gd']))['Extension gd'];

		$this->assertSame(checkResult::FAIL, $result->status);
		$this->assertStringContains('apt install php-gd', (string)$result->fix);
	}

	public function testTheMysqlDriverMapsToThePhpMysqlPackage(): void {
		$result = $this->check('8.3.0', array_diff(self::ALL_EXTENSIONS, ['pdo_mysql']))['Extension pdo_mysql'];

		$this->assertStringContains('apt install php-mysql', (string)$result->fix);
	}

	public function testTheRestartCommandNamesTheRunningPhpsFpmService(): void {
		$result = $this->check('8.1.27', array_diff(self::ALL_EXTENSIONS, ['gd']))['Extension gd'];

		$this->assertStringContains('systemctl restart php8.1-fpm', (string)$result->fix);
		$this->assertSame('php8.3-fpm', systemRequirements::fpmServiceName('8.3.12'));
	}

	public function testAMissingOptionalExtensionOnlyWarns(): void {
		$result = $this->check('8.3.0', array_diff(self::ALL_EXTENSIONS, ['curl']))['Extension curl'];

		$this->assertSame(checkResult::WARN, $result->status);
	}

	public function testAMissingBinaryWarnsAndNamesItsPackage(): void {
		$results = $this->check('8.3.0', self::ALL_EXTENSIONS, []);

		$this->assertSame(checkResult::WARN, $results['Command ffmpeg']->status);
		$this->assertStringContains('libimage-exiftool-perl', (string)$results['Command exiftool']->fix);
	}

	public function testFileUploadsOffIsAFailure(): void {
		$result = $this->check('8.3.0', self::ALL_EXTENSIONS, [], ['file_uploads' => '0'])['file_uploads'];

		$this->assertSame(checkResult::FAIL, $result->status);
	}

	public function testAnUploadLimitAbovePostMaxSizeWarns(): void {
		$results = $this->check('8.3.0', self::ALL_EXTENSIONS, [], ['upload_max_filesize' => '64M', 'post_max_size' => '8M']);

		$this->assertSame(checkResult::WARN, $results['upload_max_filesize / post_max_size']->status);
		$this->assertStringContains('64M / 8M', $results['upload_max_filesize / post_max_size']->detail);
	}

	public function testASmallMemoryLimitWarnsAndUnlimitedDoesNot(): void {
		$this->assertSame(
			checkResult::WARN,
			$this->check('8.3.0', self::ALL_EXTENSIONS, [], ['memory_limit' => '64M'])['memory_limit']->status
		);
		$this->assertSame(
			checkResult::OK,
			$this->check('8.3.0', self::ALL_EXTENSIONS, [], ['memory_limit' => '-1'])['memory_limit']->status
		);
	}

	public function testParsesIniSizeShorthand(): void {
		$this->assertSame(8 * 1024 * 1024, systemRequirements::parseByteSize('8M'));
		$this->assertSame(512 * 1024, systemRequirements::parseByteSize('512K'));
		$this->assertSame(1024 ** 3, systemRequirements::parseByteSize('1G'));
		$this->assertSame(1024, systemRequirements::parseByteSize('1024'));
		$this->assertSame(0, systemRequirements::parseByteSize('-1'));
		$this->assertSame(0, systemRequirements::parseByteSize(''));
		$this->assertSame(0, systemRequirements::parseByteSize('nonsense'));
	}
}
