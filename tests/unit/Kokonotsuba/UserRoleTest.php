<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\userRole;

/**
 * Unit tests for the userRole permission enum.
 */
final class UserRoleTest extends TestCase {

	public function testOrdering(): void {
		$this->assertTrue(userRole::LEV_ADMIN->isAtLeast(userRole::LEV_MODERATOR));
		$this->assertTrue(userRole::LEV_ADMIN->isAtLeast(userRole::LEV_ADMIN));
		$this->assertFalse(userRole::LEV_USER->isAtLeast(userRole::LEV_ADMIN));
	}

	public function testIsAtMost(): void {
		$this->assertTrue(userRole::LEV_USER->isAtMost(userRole::LEV_ADMIN));
		$this->assertTrue(userRole::LEV_ADMIN->isAtMost(userRole::LEV_ADMIN));
		$this->assertFalse(userRole::LEV_SYSTEM->isAtMost(userRole::LEV_ADMIN));
	}

	public function testIsLessThan(): void {
		$this->assertTrue(userRole::LEV_NONE->isLessThan(userRole::LEV_USER));
		$this->assertFalse(userRole::LEV_USER->isLessThan(userRole::LEV_USER));
		$this->assertFalse(userRole::LEV_SYSTEM->isLessThan(userRole::LEV_ADMIN));
	}

	public function testIsStaff(): void {
		$this->assertFalse(userRole::LEV_NONE->isStaff());
		$this->assertFalse(userRole::LEV_USER->isStaff());
		$this->assertTrue(userRole::LEV_JANITOR->isStaff());
		$this->assertTrue(userRole::LEV_MODERATOR->isStaff());
		$this->assertTrue(userRole::LEV_ADMIN->isStaff());
	}

	public function testDisplayRoleName(): void {
		$this->assertSame('System', userRole::LEV_SYSTEM->displayRoleName());
		$this->assertSame('Admin', userRole::LEV_ADMIN->displayRoleName());
		$this->assertSame('Janitor', userRole::LEV_JANITOR->displayRoleName());
		$this->assertSame('None', userRole::LEV_NONE->displayRoleName());
	}

	public function testEveryCaseHasADisplayName(): void {
		// match() in displayRoleName() is exhaustive; this guards against a new
		// case being added without a label (which would throw \UnhandledMatchError).
		foreach (userRole::cases() as $role) {
			$this->assertIsString($role->displayRoleName());
		}
	}
}
