<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\userRole;

/**
 * Who may delete and purge in one step. Purging skips the deleted posts queue, so it follows
 * CAN_DELETE_ALL rather than the janitor-level CAN_DELETE_POST.
 */
final class PostPolicyTest extends TestCase {
	private function policy(array $authLevels, userRole $role): postPolicy {
		return new postPolicy($authLevels, $role, 1);
	}

	public function testPurgeFollowsCanDeleteAll(): void {
		$levels = ['CAN_DELETE_POST' => userRole::LEV_JANITOR, 'CAN_DELETE_ALL' => userRole::LEV_ADMIN];

		$this->assertFalse($this->policy($levels, userRole::LEV_JANITOR)->canStaffPurge());
		$this->assertFalse($this->policy($levels, userRole::LEV_MODERATOR)->canStaffPurge());
		$this->assertTrue($this->policy($levels, userRole::LEV_ADMIN)->canStaffPurge());
	}

	public function testPurgeDefaultsToModerator(): void {
		$this->assertFalse($this->policy([], userRole::LEV_NONE)->canStaffPurge());
		$this->assertFalse($this->policy([], userRole::LEV_JANITOR)->canStaffPurge());
		$this->assertTrue($this->policy([], userRole::LEV_MODERATOR)->canStaffPurge());
	}

	public function testAJanitorWhoMayDeleteMayStillNotPurge(): void {
		$policy = $this->policy(['CAN_DELETE_POST' => userRole::LEV_JANITOR, 'CAN_DELETE_ALL' => userRole::LEV_MODERATOR], userRole::LEV_JANITOR);

		$this->assertTrue($policy->canStaffDelete());
		$this->assertFalse($policy->canStaffPurge());
	}
}
