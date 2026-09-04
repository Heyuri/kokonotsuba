<?php

namespace Kokonotsuba\Modules\edit;

use Kokonotsuba\module_classes\moduleContext;
use Kokonotsuba\post\Post;
use Kokonotsuba\renderers\boardRendererFactory;

use function Kokonotsuba\libraries\isActiveStaffSession;

/**
 * Renders one edited post through the normal post pipeline.
 *
 * The window patches the live post with the pieces of this HTML that the edit could have changed,
 * so the result is whatever a page reload would have shown rather than a second, hand-written
 * idea of how a name or a comment is marked up.
 */
final class editedPostRenderer {
	/** One factory per view, since the two render a post with different board templates. */
	private array $rendererFactories = [];

	public function __construct(private readonly moduleContext $moduleContext) {}

	/**
	 * @param Post $post       The post as it now stands in the database.
	 * @param bool $threadView Whether the page the editor is looking at is a single thread. Index
	 *                         listings render the same post differently (truncated comments, the
	 *                         index template), so the caller passes its own context in.
	 * @return string Rendered post HTML, or '' when the board's template cannot render one.
	 */
	public function render(Post $post, bool $threadView): string {
		$rendererFactory = $this->getRendererFactory($threadView);

		$rendererFactory->setQuoteLinks(
			$this->moduleContext->quoteLinkService->getQuoteLinksByPostUids([$post->getUid()])
		);

		// threadMode is true only for index listings, which render the same post truncated
		return $rendererFactory->renderPost(
			$post,
			isActiveStaffSession(),
			false,
			!$threadView,
			[],
			$post->getOpNumber() ?: $post->getNumber()
		);
	}

	/**
	 * The factory rendering posts for one of the two views.
	 *
	 * The edit form is reachable from pages listing posts from every board, so posts render
	 * against their own board - including the template file that board names for the view, cloned
	 * from the page's own engine so the module template search paths come with it.
	 */
	private function getRendererFactory(bool $threadView): boardRendererFactory {
		return $this->rendererFactories[$threadView] ??= new boardRendererFactory(
			$this->moduleContext->templateEngine,
			$this->moduleContext->moduleEngine,
			$this->moduleContext->request,
			$this->moduleContext->board,
			null,
			$threadView ? 'REPLY_TEMPLATE_FILE' : 'TEMPLATE_FILE'
		);
	}
}
