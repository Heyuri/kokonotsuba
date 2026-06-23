<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\error\BoardException;

use function Kokonotsuba\Modules\globalMessage\getCurrentGlobalMsg;
use function Kokonotsuba\Modules\globalMessage\writeToGlobalMsg;

/**
 * Unit tests for the globalMessage file-backed storage helpers.
 */
final class GlobalMessageLibraryTest extends TestCase {

	private string $tmp = '';

	protected function setUp(): void {
		requireModuleFile('globalMessage/globalMessageLibrary.php');
		$this->tmp = tempnam(sys_get_temp_dir(), 'koko_gmsg_');
	}

	protected function tearDown(): void {
		if ($this->tmp !== '' && file_exists($this->tmp)) {
			@unlink($this->tmp);
		}
	}

	public function testReadMissingFileReturnsEmptyString(): void {
		$this->assertSame('', getCurrentGlobalMsg('/no/such/koko/file.txt'));
	}

	public function testWriteThenReadRoundTrips(): void {
		writeToGlobalMsg($this->tmp, 'Welcome to the board');
		$this->assertSame('Welcome to the board', getCurrentGlobalMsg($this->tmp));
	}

	public function testWriteOverwritesPreviousContent(): void {
		writeToGlobalMsg($this->tmp, 'first');
		writeToGlobalMsg($this->tmp, 'second');
		$this->assertSame('second', getCurrentGlobalMsg($this->tmp));
	}

	public function testWriteToUnwritablePathThrows(): void {
		// A non-existent path is not writable, so the guard should fire.
		$this->assertThrows(
			fn() => writeToGlobalMsg('/no/such/koko/dir/file.txt', 'x'),
			BoardException::class
		);
	}
}
