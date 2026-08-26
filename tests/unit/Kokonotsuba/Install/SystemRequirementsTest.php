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

	public function testAFullyEquippedHostPassesEverything(): void {
		$results = $this->check('8.2.10', self::ALL_EXTENSIONS, ['ffmpeg', 'exiftool']);

		foreach ($results as $label => $result) {
			$this->assertSame(checkResult::OK, $result->status, $label.': '.$result->detail);
		}
	}

	public function testTooOldPhpFails(): void {
		$result = $this->check('8.0.30', self::ALL_EXTENSIONS)['PHP 8.0.30'];

		$this->assertSame(checkResult::FAIL, $result->status);
		$this->assertStringContains('8.1.0 or newer', $result->detail);
	}

	public function testUntestedPhpOnlyWarns(): void {
		$result = $this->check('8.4.1', self::ALL_EXTENSIONS)['PHP 8.4.1'];

		$this->assertSame(checkResult::WARN, $result->status);
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
