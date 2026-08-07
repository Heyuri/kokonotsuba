<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\account\legacyRoleLevelMap;
use Kokonotsuba\userRole;

/**
 * Unit tests for the legacy role numbering migration map.
 *
 * These pin the properties the migration relies on to be safe: the mapping is total over the old
 * numbering, unambiguous against the new one, and idempotent. The SQL it produces is exercised
 * against a real database in tests/integration/roleLevelMigration.php.
 */
final class LegacyRoleLevelMapTest extends TestCase {

	public function testMapsEveryLegacyValue(): void {
		$this->assertSame(0, legacyRoleLevelMap::newValueFor(0));   // None
		$this->assertSame(10, legacyRoleLevelMap::newValueFor(1));  // User
		$this->assertSame(25, legacyRoleLevelMap::newValueFor(2));  // Janitor
		$this->assertSame(50, legacyRoleLevelMap::newValueFor(3));  // Moderator
		$this->assertSame(100, legacyRoleLevelMap::newValueFor(4)); // Admin
		$this->assertSame(200, legacyRoleLevelMap::newValueFor(5)); // System
	}

	public function testUnknownLegacyValueMapsToNothing(): void {
		$this->assertNull(legacyRoleLevelMap::newValueFor(6));
		$this->assertNull(legacyRoleLevelMap::newValueFor(-1));
		$this->assertNull(legacyRoleLevelMap::newValueFor(70));
	}

	public function testEveryTargetIsARealRole(): void {
		foreach (legacyRoleLevelMap::map() as $legacy => $new) {
			$this->assertNotNull(userRole::tryFrom($new), "legacy $legacy maps to a non-role value $new");
		}
	}

	public function testManagerIsNotAMigrationTarget(): void {
		// Manager is new, so no old row can have meant it.
		$this->assertFalse(in_array(userRole::LEV_MANAGER->value, legacyRoleLevelMap::map(), true));
	}

	public function testTheTwoNumberingsOnlyOverlapAtNone(): void {
		// This is what makes the migration unambiguous and re-runnable: no legacy value can be
		// mistaken for a current value that means something else.
		$overlap = array_intersect(
			array_keys(legacyRoleLevelMap::map()),
			array_map(fn(userRole $role): int => $role->value, userRole::cases())
		);

		$this->assertSame([0], array_values($overlap));
	}

	public function testChangingMapDropsTheNoOpEntry(): void {
		$changing = legacyRoleLevelMap::changingMap();

		$this->assertFalse(array_key_exists(0, $changing));
		$this->assertCount(5, $changing);
	}

	public function testNeedsMigration(): void {
		$this->assertTrue(legacyRoleLevelMap::needsMigration(1));
		$this->assertTrue(legacyRoleLevelMap::needsMigration(4));
		// 0 is a legacy value but means None either way, so it is not rewritten
		$this->assertFalse(legacyRoleLevelMap::needsMigration(0));
		// already-current values
		$this->assertFalse(legacyRoleLevelMap::needsMigration(70));
		$this->assertFalse(legacyRoleLevelMap::needsMigration(100));
		// junk
		$this->assertFalse(legacyRoleLevelMap::needsMigration(6));
	}

	public function testMigrationIsIdempotent(): void {
		// running the map over its own output changes nothing
		foreach (legacyRoleLevelMap::map() as $new) {
			$this->assertFalse(legacyRoleLevelMap::needsMigration($new));
		}
	}

	public function testClassifySortsValues(): void {
		$classification = legacyRoleLevelMap::classify([0, 1, 4, 70, 100, 6, 1]);

		$this->assertSame([1, 4], $classification['migrate']);
		$this->assertSame([0, 70, 100], $classification['current']);
		$this->assertSame([6], $classification['unknown']);
	}

	public function testClassifyOnAnEmptySet(): void {
		$this->assertSame(
			['migrate' => [], 'current' => [], 'unknown' => []],
			legacyRoleLevelMap::classify([])
		);
	}

	public function testCaseExpressionCoversEveryChangingValue(): void {
		$sql = legacyRoleLevelMap::caseExpression('role');

		$this->assertStringContains('CASE `role`', $sql);
		$this->assertStringContains('ELSE `role` END', $sql);

		foreach (legacyRoleLevelMap::changingMap() as $legacy => $new) {
			$this->assertStringContains("WHEN {$legacy} THEN {$new}", $sql);
		}
	}

	public function testCaseExpressionRejectsAnInjectedColumnName(): void {
		$this->assertThrows(
			fn() => legacyRoleLevelMap::caseExpression('role`; DROP TABLE accounts; --'),
			\InvalidArgumentException::class
		);
	}
}
