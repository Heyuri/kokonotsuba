<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;

/**
 * The post a ban was filed on, rendered the way its own board renders it.
 *
 * Hand-building a cut-down copy of a post here would mean maintaining a second, worse post
 * template and losing everything the hooks add - including this module's own "(USER WAS BANNED
 * FOR THIS POST)" notice, which is a BelowComment listener and so only exists on a post that
 * went through the real renderer.
 *
 * Built against the post's own board rather than whichever board is serving the page, because
 * the post template takes its element IDs and links from the renderer's board.
 */
class bannedPostPreview {
	private readonly boardRendererFactory $rendererFactory;

	public function __construct(
		IBoard $fallbackBoard,
		moduleEngine $moduleEngine,
		templateEngine $templateEngine,
		private readonly quoteLinkService $quoteLinkService,
		request $request,
	) {
		$this->rendererFactory = new boardRendererFactory($templateEngine, $moduleEngine, $request, $fallbackBoard);
	}

	/**
	 * Render one post as a standalone block.
	 *
	 * @param Post $post      The post the ban names.
	 * @param bool $adminMode Whether to draw the staff-only controls. Off by default: the ban
	 *                        page's reader is the person who was banned. The moderator pages
	 *                        turn it on, since theirs is the staff view of the same post.
	 * @return string Post HTML.
	 */
	public function render(Post $post, bool $adminMode = false): string {
		$this->rendererFactory->setQuoteLinks($this->quoteLinkService->getQuoteLinksByPostUids([$post->getUid()], true));

		// Rendered through the OP block so a reply stands on its own outside its thread.
		return $this->rendererFactory->renderPost($post, $adminMode);
	}
}
