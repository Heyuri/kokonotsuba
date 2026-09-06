<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\post\Post;
use Kokonotsuba\thread\Thread;

use function Kokonotsuba\libraries\html\getPageForPostPosition;

/**
 * Everything one post render is told about the post and where it is being drawn.
 *
 * Built once at the top of postRenderer::render() and handed to each stage, so the stages
 * share one vocabulary instead of a dozen positional parameters.
 */
final class postRenderContext {
	/** The post opens its thread. */
	public readonly bool $isOp;

	/**
	 * @param Post    $post           Post being rendered. Its comment is rewritten to html in place.
	 * @param int     $threadResno    Number of the thread the post is linked back to.
	 * @param Post[]  $threadPosts    The other posts drawn with it, for listeners that need them.
	 * @param bool    $adminMode      Draw the staff-only controls and widgets.
	 * @param int     $replyCount     Replies the thread holds, for paging and the OP's counter.
	 * @param bool    $threadMode     True in a board index listing (truncated), false in a thread view.
	 * @param string  $crossLink      Board url prefix for links to another board; empty on its own board.
	 * @param bool    $killSensor     The thread is about to fall off the board.
	 * @param bool    $renderAsOp     Draw a reply through the OP block so it stands on its own.
	 * @param ?Thread $thread         Thread row, when the render path has one to hand.
	 * @param int     $repliesPerPage The board's replies per thread page.
	 */
	public function __construct(
		public readonly Post $post,
		public readonly int $threadResno,
		public readonly array $threadPosts,
		public readonly bool $adminMode,
		public readonly int $replyCount,
		public readonly bool $threadMode,
		public readonly string $crossLink,
		public readonly bool $killSensor,
		public readonly bool $renderAsOp,
		public readonly ?Thread $thread,
		public readonly int $repliesPerPage,
	) {
		$this->isOp = $post->isOp();
	}

	/** Whether the OP template block draws this post rather than the REPLY block. */
	public function usesOpBlock(): bool {
		return $this->isOp || $this->renderAsOp;
	}

	/** Inside a single thread's full view (thread page, AJAX insertion) rather than a listing. */
	public function isThreadView(): bool {
		return !$this->threadMode;
	}

	/** A deleted post shown to staff; its files are then served through php. */
	public function isDeleted(): bool {
		return $this->adminMode && $this->post->getOpenFlag() && !$this->post->isFileOnlyDeleted();
	}

	/** The thread page its last reply sits on. */
	public function lastPage(): int {
		return getPageForPostPosition($this->replyCount, $this->repliesPerPage);
	}

	/** The thread page this post sits on. */
	public function page(): int {
		return getPageForPostPosition($this->post->getObjectivePosition(), $this->repliesPerPage);
	}
}
