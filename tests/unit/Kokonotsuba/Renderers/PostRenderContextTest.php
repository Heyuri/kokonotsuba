<?php

namespace Koko\Tests\Unit\Kokonotsuba\Renderers;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\Post;
use Kokonotsuba\renderers\post\postRenderContext;

/** What a render is told about its post, and what it works out from that. */
final class PostRenderContextTest extends TestCase {

	private function context(array $postData, bool $adminMode = false, bool $renderAsOp = false, bool $threadMode = true): postRenderContext {
		return new postRenderContext(
			post: new Post($postData),
			threadResno: 1,
			threadPosts: [],
			adminMode: $adminMode,
			replyCount: 0,
			threadMode: $threadMode,
			crossLink: '',
			killSensor: false,
			renderAsOp: $renderAsOp,
			thread: null,
			repliesPerPage: 200,
		);
	}

	public function testOpUsesTheOpBlock(): void {
		$ctx = $this->context(['is_op' => 1]);

		$this->assertTrue($ctx->isOp);
		$this->assertTrue($ctx->usesOpBlock());
	}

	public function testReplyUsesTheReplyBlockUnlessAskedOtherwise(): void {
		$this->assertFalse($this->context(['is_op' => 0])->usesOpBlock());
		$this->assertTrue($this->context(['is_op' => 0], renderAsOp: true)->usesOpBlock());
	}

	public function testThreadViewIsTheOppositeOfAListing(): void {
		$this->assertFalse($this->context([], threadMode: true)->isThreadView());
		$this->assertTrue($this->context([], threadMode: false)->isThreadView());
	}

	public function testDeletedOnlyCountsForStaff(): void {
		$deleted = ['open_flag' => 1, 'file_only_deleted' => 0];

		$this->assertTrue($this->context($deleted, adminMode: true)->isDeleted());
		$this->assertFalse($this->context($deleted, adminMode: false)->isDeleted());
	}

	public function testFileOnlyDeletionIsNotADeletedPost(): void {
		$ctx = $this->context(['open_flag' => 1, 'file_only_deleted' => 1], adminMode: true);

		$this->assertFalse($ctx->isDeleted());
	}
}
