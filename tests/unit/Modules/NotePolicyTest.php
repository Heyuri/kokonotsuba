<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\userRole;
use Kokonotsuba\Modules\notes\notePolicy;
use Kokonotsuba\Modules\notes\noteService;

/**
 * Unit tests for the notes moderation permission policy.
 *
 * noteService is replaced by a stub (empty constructor, configurable ownership)
 * so the policy's role/ownership logic is tested in isolation from the DB.
 */
final class NotePolicyTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('notes/noteService.php');
		requireModuleFile('notes/notePolicy.php');
	}

	private function stubService(bool $owned): noteService {
		return new class($owned) extends noteService {
			public function __construct(private bool $owned) {}
			public function noteOwnedByAccount(int $accountId, int $noteId): bool {
				return $this->owned;
			}
		};
	}

	private function makePolicy(array $authLevels, userRole $role, ?int $accountId, bool $owned): notePolicy {
		$policy = new notePolicy($authLevels, $role, $accountId);
		$policy->setNoteService($this->stubService($owned));
		return $policy;
	}

	public function testCanLeaveNoteDefaultsToJanitor(): void {
		$this->assertTrue($this->makePolicy([], userRole::LEV_JANITOR, 1, false)->canLeaveNote());
		$this->assertFalse($this->makePolicy([], userRole::LEV_USER, 1, false)->canLeaveNote());
	}

	public function testCanLeaveNoteRespectsConfiguredLevel(): void {
		$auth = ['CAN_LEAVE_NOTE' => userRole::LEV_MODERATOR];
		$this->assertFalse($this->makePolicy($auth, userRole::LEV_JANITOR, 1, false)->canLeaveNote());
		$this->assertTrue($this->makePolicy($auth, userRole::LEV_MODERATOR, 1, false)->canLeaveNote());
	}

	public function testOwnerCanModifyOwnNoteRegardlessOfRole(): void {
		$policy = $this->makePolicy([], userRole::LEV_JANITOR, 1, true);
		$this->assertTrue($policy->canModifyNote(55));
	}

	public function testNonOwnerNeedsDeleteRole(): void {
		// Default CAN_DELETE_NOTE is admin.
		$this->assertFalse($this->makePolicy([], userRole::LEV_MODERATOR, 1, false)->canModifyNote(55));
		$this->assertTrue($this->makePolicy([], userRole::LEV_ADMIN, 1, false)->canModifyNote(55));
	}
}
