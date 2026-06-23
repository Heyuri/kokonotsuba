<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\privateMessage\messageUtility;

/**
 * Unit tests for the private-message tripcode/login utility.
 *
 * The utility stores login state in $_SESSION; each test resets it.
 */
final class MessageUtilityTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('privateMessage/messageUtility.php');
		$_SESSION = [];
	}

	protected function tearDown(): void {
		$_SESSION = [];
	}

	private function make(): messageUtility {
		return new messageUtility(fn() => 'URL', 'unit-test-salt');
	}

	public function testIsValidTripCodeAcceptsSymbolPrefixes(): void {
		$u = $this->make();
		$this->assertTrue($u->isValidTripCode('◆' . str_repeat('a', 10)));
		$this->assertTrue($u->isValidTripCode('★' . str_repeat('b', 10)));
		// Legacy ! / !! prefixes.
		$this->assertTrue($u->isValidTripCode('!' . str_repeat('c', 10)));
		$this->assertTrue($u->isValidTripCode('!!' . str_repeat('d', 10)));
	}

	public function testIsValidTripCodeRejectsJunk(): void {
		$u = $this->make();
		$this->assertFalse($u->isValidTripCode('short'));
		$this->assertFalse($u->isValidTripCode('◆tooShort'));
		$this->assertFalse($u->isValidTripCode(''));
	}

	public function testIsValidTripCodeInput(): void {
		$u = $this->make();
		$this->assertTrue($u->isValidTripCodeInput('#secret'));
		$this->assertTrue($u->isValidTripCodeInput('##secure'));
		$this->assertFalse($u->isValidTripCodeInput('nohash'));
		$this->assertFalse($u->isValidTripCodeInput('#'));
	}

	public function testParseNameSplitsRegularTripcode(): void {
		$parsed = $this->make()->parseName('Bob#secret');
		$this->assertSame('Bob', $parsed['name']);
		$this->assertStringContains('◆', $parsed['tripcode']);
		// ◆ marker (1 char) + 10-character hash.
		$this->assertSame(11, mb_strlen($parsed['tripcode']));
	}

	public function testParseNameSplitsSecureTripcode(): void {
		$parsed = $this->make()->parseName('Bob##secure');
		$this->assertSame('Bob', $parsed['name']);
		$this->assertStringContains('★', $parsed['tripcode']);
	}

	public function testParseNameWithoutTripcode(): void {
		$parsed = $this->make()->parseName('JustAName');
		$this->assertSame('JustAName', $parsed['name']);
		$this->assertSame('', $parsed['tripcode']);
	}

	public function testParseNameEscapesNamePart(): void {
		$parsed = $this->make()->parseName('<b>x</b>#trip');
		$this->assertStringNotContains('<b>', $parsed['name']);
	}

	public function testLoginAndLogoutLifecycle(): void {
		$u = $this->make();
		$this->assertFalse($u->isLoggedIn());

		$u->loginUser('#mysecret');
		$this->assertTrue($u->isLoggedIn());
		$stored = $u->getUsertripCode();
		$this->assertNotNull($stored);
		$this->assertStringContains('◆', $stored);

		$u->logoutUser();
		$this->assertNull($u->getUsertripCode());
		$this->assertFalse($u->isLoggedIn());
	}

	public function testSecureLoginStoresStarTripcode(): void {
		$u = $this->make();
		$u->loginUser('##supersecure');
		$this->assertStringContains('★', $u->getUsertripCode());
	}
}
