<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;

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
	private readonly boardRendererFactory $rendererFactory;

	/** Rendered HTML keyed by "postUid:mode" — the same post often appears in several rows. */
	private array $renderCache = [];

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
	 * Fetch the quote links for a whole page of posts up front, instead of a query per post.
	 *
	 * @param array $postUids UIDs about to be rendered.
	 */
	public function preloadQuoteLinks(array $postUids): void {
		$postUids = array_values(array_unique(array_filter(array_map('intval', $postUids))));

		if (empty($postUids)) {
			return;
		}

		// Renderers built before this call get the links too.
		$this->rendererFactory->setQuoteLinks($this->quoteLinkService->getQuoteLinksByPostUids($postUids, true));
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

		// Replies render through the OP block so they stand on their own outside a thread.
		return $this->renderCache[$cacheKey] = $this->rendererFactory->renderPost($post, $adminMode);
	}
}
