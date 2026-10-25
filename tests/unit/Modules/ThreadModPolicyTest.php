<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\threadMod\threadModPolicy;
use Kokonotsuba\Modules\threadMod\threadRole;

/**
 * Who may do what in a thread: the owner against the mods, the OP post and staff posts kept
 * out of reach, the per-thread cap, and a stripped thread switching everything off.
 */
final class ThreadModPolicyTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('threadMod/threadRole.php');
		requireModuleFile('threadMod/threadModPolicy.php');
	}

	private function policy(int $cap = 0): threadModPolicy {
		return new threadModPolicy($cap);
	}

	/** canDelete() with sensible defaults, overridden by name. */
	private function canDelete(threadModPolicy $policy, array $facts = []): bool {
		$facts += [
			'actor' => threadRole::OWNER,
			'stripped' => false,
			'targetIsOp' => false,
			'staffCapcode' => false,
			'targetAuthor' => threadRole::NONE,
			'alreadyDeleted' => false,
			'deletions' => 0,
		];

		return $policy->canDelete(
			$facts['actor'], $facts['stripped'], $facts['targetIsOp'], $facts['staffCapcode'],
			$facts['targetAuthor'], $facts['alreadyDeleted'], $facts['deletions']
		);
	}

	public function testOwnerAndModMayDeleteAnOrdinaryReply(): void {
		$this->assertTrue($this->canDelete($this->policy()));
		$this->assertTrue($this->canDelete($this->policy(), ['actor' => threadRole::MOD]));
		$this->assertFalse($this->canDelete($this->policy(), ['actor' => threadRole::NONE]));
	}

	public function testOpPostAndStaffPostsAreProtected(): void {
		$this->assertFalse($this->canDelete($this->policy(), ['targetIsOp' => true]));
		$this->assertFalse($this->canDelete($this->policy(), ['staffCapcode' => true]));
	}

	public function testModMayNotDeleteTheOwnerButOwnerMayDeleteAMod(): void {
		$this->assertFalse($this->canDelete($this->policy(), ['actor' => threadRole::MOD, 'targetAuthor' => threadRole::OWNER]));
		$this->assertTrue($this->canDelete($this->policy(), ['actor' => threadRole::MOD, 'targetAuthor' => threadRole::MOD]));
		$this->assertTrue($this->canDelete($this->policy(), ['actor' => threadRole::OWNER, 'targetAuthor' => threadRole::MOD]));
	}

	public function testAlreadyDeletedAndStrippedRefuse(): void {
		$this->assertFalse($this->canDelete($this->policy(), ['alreadyDeleted' => true]));
		$this->assertFalse($this->canDelete($this->policy(), ['stripped' => true]));
	}

	public function testCapCountsAcrossTheThreadAndZeroMeansNone(): void {
		$this->assertTrue($this->canDelete($this->policy(3), ['deletions' => 2]));
		$this->assertFalse($this->canDelete($this->policy(3), ['deletions' => 3]));
		$this->assertTrue($this->canDelete($this->policy(0), ['deletions' => 500]));
		$this->assertTrue($this->policy(1)->capReached(1));
		$this->assertFalse($this->policy(0)->capReached(1));
	}

	public function testOwnerUndoesAnyDeletionModOnlyTheirOwn(): void {
		$policy = $this->policy();

		$this->assertTrue($policy->canUndo(threadRole::OWNER, false, threadRole::MOD, 'aaaa', 'bbbb'));
		$this->assertTrue($policy->canUndo(threadRole::MOD, false, threadRole::MOD, 'aaaa', 'aaaa'));
		$this->assertFalse($policy->canUndo(threadRole::MOD, false, threadRole::MOD, 'aaaa', 'bbbb'));
		$this->assertFalse($policy->canUndo(threadRole::MOD, false, threadRole::OWNER, 'cccc', 'cccc'));
		// a mod with no token of their own cannot claim an empty hash as theirs
		$this->assertFalse($policy->canUndo(threadRole::MOD, false, threadRole::MOD, '', ''));
		$this->assertFalse($policy->canUndo(threadRole::OWNER, true, threadRole::MOD, 'aaaa', 'bbbb'));
	}

	public function testOnlyOwnerGrantsAndNeverToOpOrSelfOrTokenless(): void {
		$policy = $this->policy();

		$this->assertTrue($policy->canGrant(threadRole::OWNER, false, false, 'target', 'owner'));
		$this->assertFalse($policy->canGrant(threadRole::MOD, false, false, 'target', 'mod'));
		$this->assertFalse($policy->canGrant(threadRole::OWNER, false, true, 'target', 'owner'));
		$this->assertFalse($policy->canGrant(threadRole::OWNER, false, false, '', 'owner'));
		$this->assertFalse($policy->canGrant(threadRole::OWNER, false, false, 'owner', 'owner'));
		$this->assertFalse($policy->canGrant(threadRole::OWNER, true, false, 'target', 'owner'));
	}

	public function testOwnerRevokesOthersModOnlySteppingDown(): void {
		$policy = $this->policy();

		$this->assertTrue($policy->canRevoke(threadRole::OWNER, false, 'mod', 'owner'));
		$this->assertFalse($policy->canRevoke(threadRole::OWNER, false, 'owner', 'owner'));
		$this->assertTrue($policy->canRevoke(threadRole::MOD, false, 'mod', 'mod'));
		$this->assertFalse($policy->canRevoke(threadRole::MOD, false, 'other', 'mod'));
		$this->assertFalse($policy->canRevoke(threadRole::OWNER, false, '', 'owner'));
	}

	public function testTransferFollowsGrantRules(): void {
		$policy = $this->policy();

		$this->assertTrue($policy->canTransfer(threadRole::OWNER, false, false, 'target', 'owner'));
		$this->assertFalse($policy->canTransfer(threadRole::MOD, false, false, 'target', 'mod'));
		$this->assertFalse($policy->canTransfer(threadRole::OWNER, false, true, 'target', 'owner'));
	}

	public function testCapcodeByRoleAndNoneWhenStripped(): void {
		$policy = $this->policy();

		$this->assertSame(threadModPolicy::CAPCODE_OWNER, $policy->capcodeFor(threadRole::OWNER, false));
		$this->assertSame(threadModPolicy::CAPCODE_MOD, $policy->capcodeFor(threadRole::MOD, false));
		$this->assertNull($policy->capcodeFor(threadRole::NONE, false));
		$this->assertNull($policy->capcodeFor(threadRole::OWNER, true));
	}

	public function testThreadCapcodesAreRecognisedAndMappedBack(): void {
		$this->assertTrue(threadModPolicy::isThreadCapcode('threadOwner'));
		$this->assertTrue(threadModPolicy::isThreadCapcode('threadMod'));
		$this->assertFalse(threadModPolicy::isThreadCapcode('Mod'));
		$this->assertSame(threadRole::OWNER, threadModPolicy::roleOfCapcode('threadOwner'));
		$this->assertSame(threadRole::MOD, threadModPolicy::roleOfCapcode('threadMod'));
		$this->assertSame(threadRole::NONE, threadModPolicy::roleOfCapcode('Admin'));
	}
}
