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
		$this->assertTrue(userRole::LEV_MANAGER->isStaff());
		$this->assertTrue(userRole::LEV_ADMIN->isStaff());
	}

	public function testDisplayRoleName(): void {
		$this->assertSame('System', userRole::LEV_SYSTEM->displayRoleName());
		$this->assertSame('Admin', userRole::LEV_ADMIN->displayRoleName());
		$this->assertSame('Manager', userRole::LEV_MANAGER->displayRoleName());
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

	// ---- Manager placement --------------------------------------------------

	public function testManagerSitsBetweenModeratorAndAdmin(): void {
		$this->assertTrue(userRole::LEV_MANAGER->isAtLeast(userRole::LEV_MODERATOR));
		$this->assertTrue(userRole::LEV_MANAGER->isLessThan(userRole::LEV_ADMIN));
		// a manager therefore inherits every moderator permission by ordering alone
		$this->assertFalse(userRole::LEV_MODERATOR->isAtLeast(userRole::LEV_MANAGER));
	}

	public function testBackingValues(): void {
		$this->assertSame(0, userRole::LEV_NONE->value);
		$this->assertSame(10, userRole::LEV_USER->value);
		$this->assertSame(25, userRole::LEV_JANITOR->value);
		$this->assertSame(50, userRole::LEV_MODERATOR->value);
		$this->assertSame(70, userRole::LEV_MANAGER->value);
		$this->assertSame(100, userRole::LEV_ADMIN->value);
		$this->assertSame(200, userRole::LEV_SYSTEM->value);
	}

	public function testCasesAreOrderedByValue(): void {
		// accountRoles()/assignableRoles() and the promote ladder all assume ascending declaration
		// order, and so does anything building a role list from cases().
		$values = array_map(fn(userRole $role): int => $role->value, userRole::cases());
		$sorted = $values;
		sort($sorted);

		$this->assertSame($sorted, $values);
		$this->assertSame(count($values), count(array_unique($values)));
	}

	// ---- Role sets ----------------------------------------------------------

	public function testAccountRolesExcludeSystem(): void {
		$this->assertContains(userRole::LEV_NONE, userRole::accountRoles());
		$this->assertContains(userRole::LEV_MANAGER, userRole::accountRoles());
		$this->assertFalse(in_array(userRole::LEV_SYSTEM, userRole::accountRoles(), true));
	}

	public function testAssignableRolesExcludeNoneAndSystem(): void {
		$assignable = userRole::assignableRoles();

		$this->assertSame([
			userRole::LEV_USER,
			userRole::LEV_JANITOR,
			userRole::LEV_MODERATOR,
			userRole::LEV_MANAGER,
			userRole::LEV_ADMIN,
		], $assignable);
	}

	public function testIsAssignable(): void {
		$this->assertTrue(userRole::LEV_MANAGER->isAssignable());
		$this->assertTrue(userRole::LEV_USER->isAssignable());
		$this->assertFalse(userRole::LEV_NONE->isAssignable());
		$this->assertFalse(userRole::LEV_SYSTEM->isAssignable());
	}

	// ---- Promote / demote ---------------------------------------------------

	public function testPromotionWalksTheLadder(): void {
		$this->assertSame(userRole::LEV_USER, userRole::LEV_NONE->promoted());
		$this->assertSame(userRole::LEV_JANITOR, userRole::LEV_USER->promoted());
		$this->assertSame(userRole::LEV_MODERATOR, userRole::LEV_JANITOR->promoted());
		$this->assertSame(userRole::LEV_MANAGER, userRole::LEV_MODERATOR->promoted());
		$this->assertSame(userRole::LEV_ADMIN, userRole::LEV_MANAGER->promoted());
	}

	public function testDemotionWalksTheLadder(): void {
		$this->assertSame(userRole::LEV_MANAGER, userRole::LEV_ADMIN->demoted());
		$this->assertSame(userRole::LEV_MODERATOR, userRole::LEV_MANAGER->demoted());
		$this->assertSame(userRole::LEV_JANITOR, userRole::LEV_MODERATOR->demoted());
		$this->assertSame(userRole::LEV_USER, userRole::LEV_JANITOR->demoted());
	}

	public function testPromotionStopsAtAdminAndDemotionAtUser(): void {
		$this->assertNull(userRole::LEV_ADMIN->promoted());
		$this->assertNull(userRole::LEV_USER->demoted());
		// LEV_NONE is below the ladder, LEV_SYSTEM is off it entirely
		$this->assertNull(userRole::LEV_NONE->demoted());
		$this->assertNull(userRole::LEV_SYSTEM->promoted());
	}

	public function testPromoteThenDemoteIsIdentity(): void {
		foreach (userRole::assignableRoles() as $role) {
			$promoted = $role->promoted();
			if ($promoted !== null) {
				$this->assertSame($role, $promoted->demoted());
			}
		}
	}

	// ---- fromStored ---------------------------------------------------------

	public function testFromStoredResolvesKnownValues(): void {
		$this->assertSame(userRole::LEV_MANAGER, userRole::fromStored(70));
		$this->assertSame(userRole::LEV_ADMIN, userRole::fromStored('100'));
	}

	public function testFromStoredFallsBackToNone(): void {
		// legacy values from before the renumbering, plus junk
		$this->assertSame(userRole::LEV_NONE, userRole::fromStored(4));
		$this->assertSame(userRole::LEV_NONE, userRole::fromStored(-1));
		$this->assertSame(userRole::LEV_NONE, userRole::fromStored(null));
		$this->assertSame(userRole::LEV_NONE, userRole::fromStored(''));
	}
}
