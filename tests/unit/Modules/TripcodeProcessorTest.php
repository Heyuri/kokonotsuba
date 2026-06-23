<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\userRole;
use Kokonotsuba\Modules\tripcode\tripcodeProcessor;

/**
 * Unit tests for the tripcode module's name/tripcode/capcode processor.
 */
final class TripcodeProcessorTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('tripcode/tripcode_src/tripcodeProcessor.php');
	}

	private function makeProcessor(): tripcodeProcessor {
		return new tripcodeProcessor([
			'TRIPSALT' => 'unit-test-salt',
			'staffCapcodes' => [
				'ADMINKEY' => ['requiredRole' => userRole::LEV_ADMIN],
			],
		]);
	}

	public function testFraudSymbolInNameIsFlaggedAndHollowed(): void {
		$proc = $this->makeProcessor();
		$name = 'Impostor ◆'; $trip = ''; $secure = ''; $capcode = '';

		$proc->apply($name, $trip, $secure, $capcode, userRole::LEV_USER);

		$this->assertStringContains('(fraudster)', $name);
		// The filled diamond must be replaced by the hollow one.
		$this->assertStringNotContains('◆', $name);
		$this->assertStringContains('◇', $name);
	}

	public function testStaffCapcodeAppliedWhenRoleSufficient(): void {
		$proc = $this->makeProcessor();
		$name = 'Boss'; $trip = ''; $secure = 'ADMINKEY'; $capcode = '';

		$proc->apply($name, $trip, $secure, $capcode, userRole::LEV_ADMIN);

		$this->assertSame('ADMINKEY', $capcode);
		// Secure tripcode is cleared once a staff capcode is granted.
		$this->assertSame('', $secure);
	}

	public function testStaffCapcodeDeniedWhenRoleTooLow(): void {
		$proc = $this->makeProcessor();
		$name = 'Pretender'; $trip = ''; $secure = 'ADMINKEY'; $capcode = '';

		$proc->apply($name, $trip, $secure, $capcode, userRole::LEV_USER);

		$this->assertSame('', $capcode);
		// No capcode → the secure tripcode is hashed like any normal one.
		$this->assertNotSame('ADMINKEY', $secure);
		$this->assertSame(10, strlen($secure));
	}

	public function testRegularTripcodeIsHashedToTenChars(): void {
		$proc = $this->makeProcessor();
		$name = 'Anon'; $trip = 'password'; $secure = ''; $capcode = '';

		$proc->apply($name, $trip, $secure, $capcode, userRole::LEV_USER);

		$this->assertSame(10, strlen($trip));
		$this->assertSame('', $capcode);
		$this->assertSame('Anon', $name);
	}

	public function testTripcodeGenerationIsDeterministic(): void {
		$a = $this->makeProcessor();
		$b = $this->makeProcessor();
		$n1 = 'x'; $t1 = 'secret'; $s1 = ''; $c1 = '';
		$n2 = 'x'; $t2 = 'secret'; $s2 = ''; $c2 = '';

		$a->apply($n1, $t1, $s1, $c1, userRole::LEV_USER);
		$b->apply($n2, $t2, $s2, $c2, userRole::LEV_USER);

		$this->assertSame($t1, $t2);
	}
}
