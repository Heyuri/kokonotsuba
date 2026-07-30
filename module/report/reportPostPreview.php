<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\renderers\postRenderer;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\searchBoardArrayForBoard;

/**
 * Renders a reported post the same way the board itself would.
 *
 * Used by both sides of the module: the no-JS report form shows the post the user is about to
 * report, and the moderator tables show the post each report points at. Posts can come from any
 * board, so the board is resolved per post and passed as the cross-link base.
 *
 * A page of the report queue renders one post per row, so the renderer is built once and the
 * rows' quote links are fetched in a single batch rather than a query per post.
 */
class reportPostPreview {
	private ?postRenderer $postRenderer = null;

	/** Rendered HTML keyed by post UID — the same post often appears in several report rows. */
	private array $renderCache = [];

	public function __construct(
		private readonly IBoard $board,
		private readonly array $config,
		private readonly moduleEngine $moduleEngine,
		private readonly templateEngine $templateEngine,
		private readonly quoteLinkService $quoteLinkService,
		private readonly request $request,
	) {}

	/**
	 * Fetch the quote links for a whole page of posts up front.
	 *
	 * @param array $postUids UIDs about to be rendered.
	 */
	public function preloadQuoteLinks(array $postUids): void {
		if (empty($postUids)) {
			return;
		}

		$this->getPostRenderer()->setQuoteLinks(
			$this->quoteLinkService->getQuoteLinksByPostUids(array_values(array_unique($postUids)), true)
		);
	}

	/**
	 * Render one post as a standalone block.
	 *
	 * @param Post $post      The post to render.
	 * @param bool $adminMode Whether to render with staff-only controls attached.
	 * @return string Post HTML.
	 */
	public function render(Post $post, bool $adminMode = false): string {
		$cacheKey = $post->getUid() . ':' . ($adminMode ? 'admin' : 'user');

		if (isset($this->renderCache[$cacheKey])) {
			return $this->renderCache[$cacheKey];
		}

		// Replies are rendered through the OP block so they stand on their own outside a thread.
		$postBoard = searchBoardArrayForBoard($post->getBoardUID()) ?? $this->board;
		$templateValues = [];

		return $this->renderCache[$cacheKey] = $this->getPostRenderer()->render(
			$post,
			$templateValues,
			$post->getOpNumber(),
			false,
			[$post],
			$adminMode,
			'',
			'',
			0,
			false,
			$postBoard->getBoardURL(),
			true
		);
	}

	private function getPostRenderer(): postRenderer {
		return $this->postRenderer ??= new postRenderer(
			$this->board,
			$this->config,
			$this->moduleEngine,
			$this->templateEngine,
			[],
			$this->request
		);
	}
}
