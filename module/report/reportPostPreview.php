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
 * Renders a reported post the same way its own board would.
 *
 * Reports span every board, so a renderer is built per board rather than once for whichever
 * board happens to be serving the page: the post template takes {$BOARD_UID}, {$BOARD_URL} and
 * {$BOARD_IDENTIFIER} from the renderer's board, so a shared renderer would give every post the
 * serving board's element IDs and links — colliding IDs across boards and links that go nowhere.
 *
 * Renderers, their quote links and the rendered HTML are all cached for the request, so a page
 * of reports costs one renderer per board and one post render per post however often it repeats.
 */
class reportPostPreview {
	/** postRenderer keyed by board UID. */
	private array $renderers = [];

	/** Quote links for every post preloaded so far, shared with each renderer as it is built. */
	private array $quoteLinks = [];

	/** Rendered HTML keyed by "postUid:mode" — the same post often appears in several rows. */
	private array $renderCache = [];

	public function __construct(
		private readonly IBoard $fallbackBoard,
		private readonly moduleEngine $moduleEngine,
		private readonly templateEngine $templateEngine,
		private readonly quoteLinkService $quoteLinkService,
		private readonly request $request,
	) {}

	/**
	 * Fetch the quote links for a whole page of posts up front, instead of a query per post.
	 *
	 * @param array $postUids UIDs about to be rendered.
	 */
	public function preloadQuoteLinks(array $postUids): void {
		$postUids = array_values(array_unique(array_filter(array_map('intval', $postUids))));

		if (empty($postUids)) {
			return;
		}

		$this->quoteLinks = $this->quoteLinkService->getQuoteLinksByPostUids($postUids, true);

		// Renderers built before this call need the links too.
		foreach ($this->renderers as $renderer) {
			$renderer->setQuoteLinks($this->quoteLinks);
		}
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

		$postBoard = searchBoardArrayForBoard($post->getBoardUID()) ?? $this->fallbackBoard;
		$templateValues = [];

		// Replies render through the OP block so they stand on their own outside a thread.
		return $this->renderCache[$cacheKey] = $this->getRenderer($postBoard)->render(
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

	private function getRenderer(IBoard $board): postRenderer {
		$boardUid = $board->getBoardUID();

		if (!isset($this->renderers[$boardUid])) {
			$renderer = new postRenderer(
				$board,
				$board->loadBoardConfig(),
				$this->moduleEngine,
				$this->templateEngine,
				[],
				$this->request
			);

			$renderer->setQuoteLinks($this->quoteLinks);

			$this->renderers[$boardUid] = $renderer;
		}

		return $this->renderers[$boardUid];
	}
}
