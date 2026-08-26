<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\checkResult;
use Kokonotsuba\install\pathRequirement;
use Kokonotsuba\install\pathRequirements;
use Kokonotsuba\install\processIdentity;

/**
 * The directory map: what it reports, and the command it hands the user when access is missing.
 *
 * Permission cases are skipped when the suite runs as root, since root can write anything.
 */
class PathRequirementsTest extends TestCase {

	private string $root = '';

	private pathRequirements $requirements;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir().'/koko-paths-'.bin2hex(random_bytes(4));

		foreach (['', '/global', '/global/board-storages', '/static', '/code', '/module', '/configs', '/templates', '/migrations'] as $directory) {
			mkdir($this->root.$directory, 0770, true);
		}

		$this->requirements = pathRequirements::forAppRoot($this->root, new processIdentity('www-data', 'www-data'));
	}

	protected function tearDown(): void {
		foreach (array_reverse(glob($this->root.'/{,*/}{,*/}', GLOB_BRACE) ?: []) as $directory) {
			@chmod($directory, 0770);
			@rmdir($directory);
		}
		@rmdir($this->root);
	}

	private function runningAsRoot(): bool {
		return function_exists('posix_geteuid') && posix_geteuid() === 0;
	}

	/** @return array<string, checkResult> label => result */
	private function byLabel(): array {
		$results = [];
		foreach ($this->requirements->check() as $result) {
			$results[$result->label] = $result;
		}

		return $results;
	}

	public function testEveryRequiredDirectoryIsReported(): void {
		$results = $this->byLabel();

		$this->assertCount(10, $results);
		$this->assertTrue(isset($results['global/'], $results['boards/'], $results['static/']));
	}

	public function testAWritableTreePassesEveryCheck(): void {
		foreach ($this->byLabel() as $label => $result) {
			// boards/ does not exist yet, which is fine: the installer creates it.
			$this->assertSame(checkResult::OK, $result->status, $label.': '.$result->detail);
		}
	}

	public function testAMissingDirectoryTheInstallerCanCreateIsNotAFailure(): void {
		$boards = $this->byLabel()['boards/'];

		$this->assertSame(checkResult::OK, $boards->status);
		$this->assertStringContains('installer will create it', $boards->detail);
	}

	public function testAMissingDirectoryTheInstallerCannotCreateFails(): void {
		rmdir($this->root.'/static');

		$static = $this->byLabel()['static/'];

		$this->assertSame(checkResult::FAIL, $static->status);
		$this->assertStringContains('mkdir -p', (string)$static->fix);
	}

	public function testAnUnwritableDirectoryFailsWithAChownAndChmod(): void {
		if ($this->runningAsRoot()) {
			$this->pass();

			return;
		}

		chmod($this->root.'/global', 0500);
		$global = $this->byLabel()['global/'];
		chmod($this->root.'/global', 0770);

		$this->assertSame(checkResult::FAIL, $global->status);
		$this->assertStringContains('Not writable', $global->detail);
		$this->assertStringContains("sudo chown -R www-data:www-data '".$this->root."/global'", (string)$global->fix);
		$this->assertStringContains('chmod -R 770', (string)$global->fix);
	}

	public function testAReadOnlyDirectoryIsEnoughWhereOnlyReadingIsNeeded(): void {
		if ($this->runningAsRoot()) {
			$this->pass();

			return;
		}

		chmod($this->root.'/templates', 0500);
		$templates = $this->byLabel()['templates/'];
		chmod($this->root.'/templates', 0770);

		$this->assertSame(checkResult::OK, $templates->status);
	}

	public function testAnUnreadableDirectoryFails(): void {
		if ($this->runningAsRoot()) {
			$this->pass();

			return;
		}

		chmod($this->root.'/code', 0000);
		$code = $this->byLabel()['code/'];
		chmod($this->root.'/code', 0770);

		$this->assertSame(checkResult::FAIL, $code->status);
		$this->assertStringContains('Not readable', $code->detail);
	}

	public function testAFileWhereADirectoryBelongsFails(): void {
		rmdir($this->root.'/module');
		file_put_contents($this->root.'/module', 'not a directory');

		$module = $this->byLabel()['module/'];
		unlink($this->root.'/module');
		mkdir($this->root.'/module', 0770);

		$this->assertSame(checkResult::FAIL, $module->status);
		$this->assertStringContains('not a directory', $module->detail);
	}

	public function testTheFixCommandForAReadOnlyPathUses750(): void {
		$requirement = new pathRequirement($this->root.'/static', 'static/', 'assets', false);

		$this->assertStringContains('chmod -R 750', $this->requirements->permissionCommand($requirement));
	}

	public function testPathsAreQuotedInFixCommands(): void {
		$requirement = new pathRequirement('/var/www/my site', 'site/', 'boards', true);

		$this->assertStringContains("'/var/www/my site'", $this->requirements->permissionCommand($requirement));
	}
}
