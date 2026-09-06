<?php

namespace Koko\Tests\Unit\Kokonotsuba\Install;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\install\databaseErrorAdvice;

/** Driver errors turned into something the user can act on. */
class DatabaseErrorAdviceTest extends TestCase {

	private function advise(string $error): databaseErrorAdvice {
		return databaseErrorAdvice::forError($error, '127.0.0.1', 'koko_user', 'kokonotsuba');
	}

	public function testUnknownDatabaseSuggestsCreatingIt(): void {
		$advice = $this->advise("SQLSTATE[HY000] [1049] Unknown database 'kokonotsuba'");

		$this->assertStringContains('does not exist', $advice->message);
		$this->assertStringContains('CREATE DATABASE', (string)$advice->fix);
	}

	public function testAccessDeniedSuggestsAGrantForTheHostAsEntered(): void {
		$advice = $this->advise("SQLSTATE[HY000] [1045] Access denied for user 'koko_user'@'localhost'");

		$this->assertStringContains('refused the username or password', $advice->message);
		$this->assertStringContains("'koko_user'@'127.0.0.1'", (string)$advice->fix);
		$this->assertStringContains('GRANT ALL PRIVILEGES', (string)$advice->fix);
	}

	public function testNoPrivilegesSuggestsOnlyTheGrant(): void {
		$advice = $this->advise('SQLSTATE[42000] [1044] Access denied for user to database');

		$this->assertStringContains('no privileges', $advice->message);
		$this->assertStringContains('GRANT ALL PRIVILEGES', (string)$advice->fix);
		$this->assertStringNotContains('CREATE USER', (string)$advice->fix);
	}

	public function testConnectionRefusedPointsAtTheService(): void {
		$advice = $this->advise('SQLSTATE[HY000] [2002] Connection refused');

		$this->assertStringContains('Nothing answered at 127.0.0.1', $advice->message);
		$this->assertStringContains('systemctl status mariadb', (string)$advice->fix);
	}

	public function testUnknownHostHasNoCommandToOffer(): void {
		$advice = $this->advise('SQLSTATE[HY000] [2005] Unknown MySQL server host');

		$this->assertNull($advice->fix);
		$this->assertStringContains('could not be resolved', $advice->message);
	}

	public function testAMissingDriverSuggestsInstallingIt(): void {
		$advice = $this->advise('could not find driver');

		$this->assertStringContains('apt install php-mysql', (string)$advice->fix);
	}

	public function testAnUnrecognisedErrorIsPassedThroughUnchanged(): void {
		$advice = $this->advise('SQLSTATE[HY000] [9999] Something new');

		$this->assertSame('SQLSTATE[HY000] [9999] Something new', $advice->message);
		$this->assertNull($advice->fix);
	}
}
