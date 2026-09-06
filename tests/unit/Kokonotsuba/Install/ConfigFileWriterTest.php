<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\configFileWriter;
use RuntimeException;

/** Generating and atomically replacing the config files the installer writes. */
class ConfigFileWriterTest extends TestCase {

	private string $dir = '';

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir().'/koko-config-writer-'.bin2hex(random_bytes(4));
		mkdir($this->dir, 0777, true);
	}

	protected function tearDown(): void {
		foreach (glob($this->dir.'/*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->dir);
	}

	private function load(string $path): array {
		// Each write lands in a new file, so include (not include_once) is what reads it back.
		return include $path;
	}

	public function testRendersAFileThatLoadsBackAsTheSameArray(): void {
		$values = ['DATABASE_USERNAME' => 'koko', 'DATABASE_PORT' => 3306, 'USE_CDN' => false];
		$path = $this->dir.'/settings.php';

		(new configFileWriter())->write($path, configFileWriter::render($values, 'Header line.'));

		$this->assertSame($values, $this->load($path));
	}

	public function testEscapesQuotesAndBackslashesInAPassword(): void {
		$password = "it's a \\ 'trap' \$var \n newline";
		$path = $this->dir.'/settings.php';

		(new configFileWriter())->write($path, configFileWriter::render(['DATABASE_PASSWORD' => $password]));

		$this->assertSame($password, $this->load($path)['DATABASE_PASSWORD']);
	}

	public function testWritesTheHeaderAndPerKeyComments(): void {
		$rendered = configFileWriter::render(
			['TRIPSALT' => 'abc'],
			"Written by install.php.\nSecond line.",
			['TRIPSALT' => 'Never change this.']
		);

		$this->assertStringContains(' * Written by install.php.', $rendered);
		$this->assertStringContains(' * Second line.', $rendered);
		$this->assertStringContains("\t// Never change this.", $rendered);
	}

	public function testBacksUpAndRestoresAFileItReplaced(): void {
		$path = $this->dir.'/settings.php';
		file_put_contents($path, "<?php\n\nreturn ['KEY' => 'original'];\n");

		$writer = new configFileWriter();
		$backup = $writer->write($path, configFileWriter::render(['KEY' => 'replaced']));

		$this->assertNotNull($backup);
		$this->assertSame('replaced', $this->load($path)['KEY']);

		$writer->rollback();

		$this->assertSame('original', $this->load($path)['KEY']);
		$this->assertFalse(file_exists($backup), 'the backup is moved back, not left behind');
	}

	public function testRollbackDeletesAFileThatDidNotExistBefore(): void {
		$path = $this->dir.'/fresh.php';

		$writer = new configFileWriter();
		$this->assertNull($writer->write($path, configFileWriter::render(['KEY' => 'value'])));
		$this->assertTrue(file_exists($path));

		$writer->rollback();

		$this->assertFalse(file_exists($path));
	}

	public function testDiscardingBackupsLeavesTheNewFileInPlace(): void {
		$path = $this->dir.'/settings.php';
		file_put_contents($path, "<?php return ['KEY' => 'original'];\n");

		$writer = new configFileWriter();
		$backup = $writer->write($path, configFileWriter::render(['KEY' => 'new']));
		$writer->discardBackups();

		$this->assertSame('new', $this->load($path)['KEY']);
		$this->assertFalse(file_exists((string)$backup));
	}

	public function testLeavesNoTemporaryFilesBehind(): void {
		$path = $this->dir.'/settings.php';
		(new configFileWriter())->write($path, configFileWriter::render(['KEY' => 'value']));

		$this->assertCount(0, glob($this->dir.'/{,.}*.tmp-*', GLOB_BRACE) ?: []);
	}

	public function testSidecarFilesAreDotfilesSoTheDenyRulesCoverThem(): void {
		$this->assertSame('/srv/koko/.databaseSettings.php.tmp-1a2b', configFileWriter::sidecarPath('/srv/koko/databaseSettings.php', '.tmp-1a2b'));
	}

	public function testRefusesToWriteIntoADirectoryThatIsNotThere(): void {
		$writer = new configFileWriter();

		$this->assertThrows(
			fn () => $writer->write($this->dir.'/missing/settings.php', configFileWriter::render(['A' => 'b'])),
			RuntimeException::class,
			'not writable'
		);
	}

	public function testKeepsTheOldFileWhenTheNewContentIsNotValidPhp(): void {
		$path = $this->dir.'/settings.php';
		file_put_contents($path, "<?php return ['KEY' => 'original'];\n");

		$writer = new configFileWriter();
		$this->assertThrows(fn () => $writer->write($path, "<?php return 'not an array';\n"), RuntimeException::class);

		$this->assertSame('original', $this->load($path)['KEY']);
		$this->assertCount(0, glob($this->dir.'/{,.}*.tmp-*', GLOB_BRACE) ?: []);
	}
}
