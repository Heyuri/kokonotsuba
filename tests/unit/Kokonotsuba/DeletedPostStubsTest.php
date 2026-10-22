<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\deletion\deletedPostStubs;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

/** Which deleted posts become stubs, what each stub is labelled, and what it keeps. */
final class DeletedPostStubsTest extends TestCase {
	private function post(array $deletion = []): Post {
		return new Post($deletion + [
			'post_uid' => 7, 'no' => 42, 'boardUID' => 2, 'thread_uid' => 't1', 'post_position' => 3,
			'com' => 'secret', 'name' => 'Anon', 'email' => 'sage', 'host' => '127.0.0.1', 'status' => '_opdel:owner:ab_',
		]);
	}

	private function kindOf(Post $post): ?string {
		deletedPostStubs::apply([$post]);
		return $post->getStubKind();
	}

	public function testAPostThatIsNotDeletedIsLeftAlone(): void {
		$post = $this->post();
		$this->assertNull($this->kindOf($post));
		$this->assertSame('secret', $post->getComment());
	}

	public function testAFileOnlyDeletionIsNotAStub(): void {
		$this->assertNull($this->kindOf($this->post(['open_flag' => 1, 'file_only_deleted' => 1, 'deleted_by' => null])));
	}

	public function testAnAnonymousDeletionIsTheUsers(): void {
		$this->assertSame(deletedPostStubs::BY_USER, $this->kindOf($this->post(['open_flag' => 1, 'deleted_by' => null])));
	}

	public function testADeletionByAnAccountBelowStaffIsTheUsers(): void {
		$this->assertSame(deletedPostStubs::BY_USER, $this->kindOf($this->post(['open_flag' => 1, 'deleted_by' => 5, 'deleted_by_role' => userRole::LEV_USER->value])));
	}

	public function testADeletionByAStaffAccountIsStaffs(): void {
		$this->assertSame(deletedPostStubs::BY_STAFF, $this->kindOf($this->post(['open_flag' => 1, 'deleted_by' => 8, 'deleted_by_role' => userRole::LEV_JANITOR->value])));
	}

	public function testADeletionByNoAccountSuchAsAntiFloodIsStaffs(): void {
		$this->assertSame(deletedPostStubs::BY_STAFF, $this->kindOf($this->post(['open_flag' => 1, 'deleted_by' => 0, 'deleted_by_role' => null])));
	}

	public function testAStubKeepsItsPlaceAndNothingElse(): void {
		$post = $this->post(['open_flag' => 1, 'deleted_by' => null]);
		deletedPostStubs::apply([$post]);

		$this->assertSame([7, 42, 2, 't1', 3], [$post->getUid(), $post->getNumber(), $post->getBoardUID(), $post->getThreadUid(), $post->getPostPosition()]);
		$this->assertSame(['', '', '', '', ''], [$post->getComment(), $post->getName(), $post->getEmail(), $post->getStatus(), (string)($post->toArray()['host'] ?? '')]);
		$this->assertSame([], $post->getAttachments());
	}
}
