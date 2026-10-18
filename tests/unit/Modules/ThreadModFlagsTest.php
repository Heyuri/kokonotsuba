<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\threadMod\threadModService;
use Kokonotsuba\Modules\threadMod\threadRole;
use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;

/**
 * The OP deletion flag as it lives in the post's status column: written and read back through
 * FlagHelper alongside other flags, and the role enum's stored forms.
 */
final class ThreadModFlagsTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('threadMod/threadRole.php');
		requireModuleFile('threadMod/threadModPolicy.php');
		requireModuleFile('threadMod/threadModRepository.php');
		requireModuleFile('threadMod/threadModService.php');
	}

	public function testDeletionFlagRoundTripsWithRoleAndHash(): void {
		$flags = new FlagHelper('_sage_');
		$flags->update(threadModService::DELETED_FLAG, threadRole::MOD->key() . ':abcdef0123456789');

		$post = new Post(['status' => $flags->toString()]);
		$deletion = threadModService::deletionOf($post);

		$this->assertNotNull($deletion);
		$this->assertSame(threadRole::MOD, $deletion['role']);
		$this->assertSame('abcdef0123456789', $deletion['hash']);
		$this->assertTrue(threadModService::isOpDeleted($post));
		// the neighbouring flag survives
		$this->assertTrue((bool)$post->getFlags()->value('sage'));
	}

	public function testOwnerDeletionWithoutTokenStillParses(): void {
		$post = new Post(['status' => '_opdel:owner:_']);
		$deletion = threadModService::deletionOf($post);

		$this->assertNotNull($deletion);
		$this->assertSame(threadRole::OWNER, $deletion['role']);
		$this->assertSame('', $deletion['hash']);
	}

	public function testPostsWithoutTheFlagAreNotDeleted(): void {
		$this->assertNull(threadModService::deletionOf(new Post(['status' => ''])));
		$this->assertNull(threadModService::deletionOf(new Post(['status' => '_stop__sticky_'])));
		$this->assertFalse(threadModService::isOpDeleted(new Post([])));
	}

	public function testRemovingTheFlagLeavesTheRest(): void {
		$flags = new FlagHelper('_opdel:owner:ffff__stop_');
		$flags->remove(threadModService::DELETED_FLAG);

		$this->assertSame('_stop_', $flags->toString());
	}

	public function testRoleStoredFormsAndKeys(): void {
		$this->assertSame(threadRole::OWNER, threadRole::fromStored('2'));
		$this->assertSame(threadRole::MOD, threadRole::fromStored(1));
		$this->assertSame(threadRole::NONE, threadRole::fromStored(9));
		$this->assertSame(threadRole::OWNER, threadRole::fromKey('owner'));
		$this->assertSame(threadRole::NONE, threadRole::fromKey('staff'));
		$this->assertSame('mod', threadRole::MOD->key());
		$this->assertTrue(threadRole::MOD->isAtLeastMod());
		$this->assertFalse(threadRole::NONE->isAtLeastMod());
		$this->assertTrue(threadRole::OWNER->isOwner());
	}
}
