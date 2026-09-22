<?php

namespace Kokonotsuba;

use Kokonotsuba\board\board;
use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\renderers\threadPageRenderer;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;

use function Kokonotsuba\libraries\html\buildThreadAreaTemplateValues;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\generateHeadHtml;
use function Kokonotsuba\libraries\isActiveStaffSession;

/**
 * Threads from every board the reader has selected, drawn on the viewing board's page.
 *
 * Only what is peculiar to the overboard lives here - its own head values, its board filter and
 * its pager. The head, the thread-area values and the page of threads themselves are the same
 * ones a board page is built from.
 */
class overboard {
	private bool $adminMode, $canViewDeleted;

	private ?boardRendererFactory $rendererFactory = null;

	private ?threadPageRenderer $pageRenderer = null;

	public function __construct(
		private board $board,
		private readonly array $config, 
		private readonly softErrorHandler $softErrorHandler,
		private readonly threadRepository $threadRepository,
		private readonly quoteLinkService $quoteLinkService,
		private readonly threadService $threadService,
		private moduleEngine $moduleEngine, 
		private templateEngine $templateEngine,
		private postRenderingPolicy $postRenderingPolicy,
		private readonly appContainer $container,
		private readonly request $request,
	) {
		// whether staff is logged in or not
		$this->adminMode = isActiveStaffSession();
		
		// can view deleted posts
		$this->canViewDeleted = $postRenderingPolicy->viewDeleted();
	}
	
	/** The board head, titled and sub-headed as the overboard rather than as the board it is served from. */
	public function drawOverboardHead(&$dat, $resno = 0) {
		$html = generateHeadHtml(
			$this->config,
			$this->templateEngine,
			$this->moduleEngine,
			strip_tags($this->config['OVERBOARD_TITLE']),
			$resno,
			$this->adminMode,
			[
				'extraPteVals' => [
					'{$TITLE}' => $this->config['OVERBOARD_TITLE'],
					'{$TITLESUB}' => $this->config['OVERBOARD_SUBTITLE'],
					'{$LIVE_INDEX_FILE}' => $this->config['LIVE_INDEX_FILE'],
				],
				'dispatchPlaceHolderIntercept' => true,
				'renderPostArea' => true,
				'suffixHtml' => $this->config['OVERBOARD_SUB_HEADER_HTML'],
			]
		);

		$dat .= $html;
		return $html;
	}

	public function drawOverboardThreads(array $filters) {
		$page = $this->request->getParameter('page', null, 1);
		if (!filter_var($page, FILTER_VALIDATE_INT) && $page != 1) $this->softErrorHandler->errorAndExit("Page number was not a valid int.");
		$page = ($page >= 1) ? $page : 1;
		
		$limit = $this->config['OVERBOARD_THREADS_PER_PAGE'];
		$offset = ($page - 1) * $limit;

		// the delete form keeps its func field, which sends the poster back to the overboard, and
		// the board-scoped hooks are left out: they draw chrome belonging to one board
		$templateValues = buildThreadAreaTemplateValues($this->moduleEngine, false, $this->adminMode, [
			'returnAfterDelete' => true,
		]);

		// If no boards are selected, return prematurely
		if (!$filters['board']) {
			return '<div class="bbls"> <b class="error"> - No threads - </b> </div>';
		}

		$previewCount = $this->config['RE_DEF'];

		$threadList = $this->threadService->getFilteredThreadList($limit, $offset, $filters, $this->canViewDeleted);

		$numberThreadsFiltered = $this->threadRepository->getFilteredThreadCount($filters, $this->canViewDeleted);

		$templateValues['{$THREADS}'] = $this->getPageRenderer()->renderThreads(
			$threadList,
			threadPageRenderer::boardsForThreads($threadList),
			$previewCount,
			threadFragmentCache::overboardVariant($previewCount, $this->board->getBoardUID()),
			$templateValues
		);

		$templateValues['{$BOTTOM_PAGENAV}'] = drawPager($limit, $numberThreadsFiltered, $this->board->getBoardURL(true) . '?mode=overboard', $this->request);

		return $this->templateEngine->ParseBlock('MAIN', $templateValues);
	}

	/**
	 * The renderer for the page's threads.
	 *
	 * Fragments are shared with the boards the threads come from, so the viewing board's own
	 * switch has to be on as well as theirs.
	 */
	private function getPageRenderer(): threadPageRenderer {
		return $this->pageRenderer ??= new threadPageRenderer(
			$this->getRendererFactory(),
			$this->threadService,
			$this->quoteLinkService,
			$this->board,
			$this->adminMode,
			$this->canViewDeleted,
			!$this->adminMode && !$this->canViewDeleted && !empty($this->config['THREAD_FRAGMENT_CACHE']),
			true
		);
	}

	/*
	* Every thread from a board shares one module engine, the same way a board renders
	* its own index. Building one per thread re-instantiated every module - along with
	* an admin template engine and page renderer - for each thread on the page.
	*/
	private function getRendererFactory(): boardRendererFactory {
		return $this->rendererFactory ??= new boardRendererFactory(
			$this->templateEngine,
			$this->moduleEngine,
			$this->request,
			$this->board,
			$this->container
		);
	}
}
