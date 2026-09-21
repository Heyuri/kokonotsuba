<?php

namespace Kokonotsuba\board;

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\renderers\commentFormatter;
use Kokonotsuba\renderers\threadPageRenderer;
use Kokonotsuba\renderers\threadRenderer;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\post\Post;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\Thread;
use Kokonotsuba\thread\ThreadData;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;

use function Kokonotsuba\libraries\html\buildThreadAreaTemplateValues;
use function Kokonotsuba\libraries\html\drawBoardPager;
use function Kokonotsuba\libraries\html\drawLiveBoardPager;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\getPageForPostPosition;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Puchiko\strings\html_minify;
use function Puchiko\strings\sanitizeStr;
use function Puchiko\strings\truncateText;

class boardRebuilder {
	/**
	 * True only while page HTML is being generated for a static file.
	 *
	 * The request doing the generating is an ordinary live one — usually a moderator's — so
	 * nothing about the session tells a hook whether its output is going to that moderator or
	 * into a file every reader will be served. This does.
	 */
	private static bool $renderingStaticHtml = false;

	private array $config;
	private bool $adminMode, $canViewDeleted;
	private ?boardRendererFactory $rendererFactory = null;
	private ?threadFragmentCache $fragmentCache = null;

	/** Whether the HTML being generated right now is destined for a static file. */
	public static function isRenderingStaticHtml(): bool {
		return self::$renderingStaticHtml;
	}

	public function __construct(
		private board $board, 
		private moduleEngine $moduleEngine, 
		private templateEngine $templateEngine, 
		private readonly actionLoggerService $actionLoggerService, 
		private readonly threadRepository $threadRepository, 
		private readonly threadService $threadService,
		private readonly quoteLinkService $quoteLinkService,
		private postRenderingPolicy $postRenderingPolicy,
		private readonly request $request) {

		$this->config = $board->loadBoardConfig();
		if (empty($this->config)) {
			die("No board config for {$board->getBoardTitle()}:{$board->getBoardUID()}");
		}

		// whether its a mod thats logged in
		// used for admin-specific views
		$this->adminMode = isActiveStaffSession();

		// can view deleted
		$this->canViewDeleted = $this->postRenderingPolicy->viewDeleted();
	}

	public function drawRecentReplies(int $threadNumber, int $amountOfRepliesToRender, bool $showPostForm = true): void {
		// draw the most thread with only OP + the most recent replies
		// in this instance page can be null since we're fetching posts regardless of page
		$this->drawBaseThread(
			$threadNumber, 
			null, 
			$amountOfRepliesToRender, 
			$showPostForm
		);
	}

	public function drawThread(int $threadNumber, ?int $page = null, bool $showPostForm = true): void {
		// draw the thread at the targetted page
		// a null page value will fetch all posts in the thread
		$this->drawBaseThread($threadNumber, $page, null, $showPostForm);
	}

	private function drawBaseThread(
		int $threadNumber, 
		?int $page = null, 
		?int $amountOfRepliesToRender = null, 
		bool $showPostForm = true
	): void {
		$uid = $this->threadRepository->resolveThreadUidFromResno($this->board, $threadNumber);
		$previewCount = $this->board->getConfigValue('RE_DEF', 5);
		$repliesPerPage = $this->board->getConfigValue('REPLIES_PER_PAGE', 200);
		$includeDeleted = $this->postRenderingPolicy->viewDeleted();

		// the last-N view is the reader's own choice of N, so it is never cached
		$cache = is_null($amountOfRepliesToRender) ? $this->fragmentCache(!$this->adminMode && !$this->canViewDeleted) : null;
		$variant = threadFragmentCache::threadVariant($page);

		// The row is read once: it is the stamp the cache is checked with, and it is handed on to
		// whichever fetch follows so neither reads it again. A cached thread then needs only its
		// OP, for the title, pager and hooks.
		$thread = $uid ? $this->threadRepository->getThreadByUid((string)$uid, $includeDeleted) : false;
		if ($thread === false) {
			throw new BoardException(_T('thread_not_found'), 404);
		}

		$cachedBlock = $cache?->get($uid, $variant, threadFragmentCache::stampFor($thread));

		$threadData = $cachedBlock !== null
			? $this->threadService->getThreadWithOpeningPost($uid, $includeDeleted, $thread)
			: $this->getThreadForRendering($uid, $previewCount, $repliesPerPage, $page, $amountOfRepliesToRender, $includeDeleted, $thread);

		if ($threadData === false) {
			throw new BoardException(_T('thread_not_found'), 404);
		}

		$thread = $threadData->getThread();
		$totalPosts = $thread->getPostCount();
		$hardDeleted = $thread->isThreadDeleted() && !$thread->isAttachmentDeleted();

		if ($hardDeleted && !$includeDeleted) {
			throw new BoardException(_T('thread_not_found'), 404);
		}

		$threadUrl = $this->board->getBoardThreadURL($threadNumber);
		$pte_vals = $this->buildPteVals(true);

		if($showPostForm) {
			$pte_vals['{$FORMDAT}'] = $this->buildFormHtml($threadNumber, $pte_vals, $this->adminMode);
		}

		// only dispatched for threads being viewed through drawThread
		$this->moduleEngine->dispatch('ViewedThread', [&$pte_vals, &$threadData]);

		// totalPosts includes OP; last reply position = totalPosts - 1
		$totalThreadPages = getPageForPostPosition($totalPosts - 1, $repliesPerPage);
		$currentPage = !is_null($amountOfRepliesToRender)
			? $totalThreadPages
			: ($page ?? 1);

		if ($cachedBlock !== null) {
			$threadRenderer = $this->getThreadRenderer();
			$threadRenderer->notifyCachedThread($thread, true);
			$block = $cachedBlock;
		} else {
			$posts = $threadData->getPosts();
			$quoteLinks = $this->quoteLinkService->getQuoteLinksByPostUids($threadData->getPostUids(), $this->canViewDeleted);
			$threadRenderer = $this->getThreadRenderer($quoteLinks);
			$this->moduleEngine->dispatch('PostsPrefetch', [&$posts]);

			$block = $threadRenderer->renderThreadBlock(true, $thread, $posts, 0, false, $this->adminMode,
				'', '', $pte_vals, $currentPage, $totalThreadPages, $amountOfRepliesToRender, '');
			$cache?->put($uid, $variant, threadFragmentCache::stampFor($thread), $block);
		}
		$pte_vals['{$THREADS}'] .= $block . $threadRenderer->renderThreadSeparator(0);

		// if a non-null page value is set - then draw the pager
		if(!is_null($page)) {
			$enableTopPager = $this->board->getConfigValue('TOP_THREAD_PAGER', false);

			// reply count excludes OP (which is always shown separately)
			$replyCount = max(0, $totalPosts - 1);

			if($enableTopPager) {
				$pte_vals['{$TOP_PAGENAV}'] = drawPager($repliesPerPage, $replyCount, $threadUrl, $this->request);
			}

			$pte_vals['{$BOTTOM_PAGENAV}'] = drawPager($repliesPerPage, $replyCount, $threadUrl, $this->request);
		}

		$opPost = $threadData->getOpeningPost();
		$boardTitle = $this->board->getBoardTitle();
		$pageTitle = $this->getThreadPageTitle($opPost, $boardTitle);

		$pageData = $this->buildFullPage($pte_vals, $pageTitle, $threadNumber, true, $this->adminMode);
		echo $this->finalizePageData($pageData);
	}

	private function getThreadForRendering(
		string $threadUid, 
		int $previewCount, 
		int $repliesPerPage, 
		?int $page, 
		?int $amountOfRepliesToRender,
		bool $includeDeleted = false,
		?Thread $thread = null
	): false|ThreadData {
		// Fetch thread with a limited amount of replies	
		if(!is_null($amountOfRepliesToRender)) {
			// fetch a 'last X replies' thread
			$threadData = $this->threadService->getThreadLastReplies(
				$threadUid, 
				$this->canViewDeleted, 
				$previewCount, 
				$amountOfRepliesToRender,
				$includeDeleted,
				$thread
			);

		}
		// Fetch thread with pages
		else if(!is_null($page)) {
			// fetch a paged thread
			$threadData = $this->threadService->getThreadPaged(
				$threadUid, 
				$this->canViewDeleted, 
				$previewCount, 
				$repliesPerPage, 
				$page, 
				$includeDeleted,
				$thread
			);
		}
		// Fetch unpaged thread (intensive)
		else {
			// get the whole thing
			$threadData = $this->threadService->getThreadAllReplies($threadUid, $this->canViewDeleted, $previewCount, $includeDeleted, $thread);
		}

		// then return the thread data
		return $threadData;
	}

	private function getThreadPageTitle(Post $opPost, string $boardTitle): string {
		$format = $opPost->getTextFormat();

		$subject = commentFormatter::fieldToPlainText($opPost->getSubject(), $format); // thread subject/topic
		$comment = commentFormatter::commentToPlainText($opPost->getComment(), $format); // op post comment
		
		// get the first attachment
		$firstAttachment = $opPost->getFirstAttachment();

		// set first filename if it exists
		$firstAttachment ? $fileName = strip_tags($firstAttachment['fileName'] . '.' . $firstAttachment['fileExtension']) : $fileName = null;

		// Max length before truncating strings
		$maxTitleLength = 20;
		
		// The pieces above are plain text and the title is emitted as markup, so each is escaped
		// as it goes in. The board title is configuration, not post content, and is left alone.
		// first, have it include the subject + board title 
		if(!empty($subject)) {
			// truncate the subject
			$truncateSubject = sanitizeStr(truncateText($subject, $maxTitleLength));

			$threadTitle = "$truncateSubject - $boardTitle";
		} 
		// then try the comment
		else if(!empty($comment) && $comment !== $this->config['DEFAULT_NOCOMMENT']) {
			// truncate the comment
			$truncatedComment = sanitizeStr(truncateText($comment, $maxTitleLength));

			$threadTitle = $truncatedComment . ' - ' . $boardTitle;
		} 
		// then try the file name (useful for dump/flash boards)
		else if(!empty($fileName)) {
			// truncate file name
			$truncateFileName = sanitizeStr(truncateText($fileName, $maxTitleLength));

			$threadTitle = $truncateFileName . ' - ' . $boardTitle;
		}
		// otherwise, just use the board title
		else { 
			$threadTitle = $boardTitle;
		}

		return $threadTitle;

	}

	public function drawPage(int $page = 1): void {
		$boardUrl = $this->board->getBoardURL();
		$threadsPerPage = $this->config['PAGE_DEF'];
		$threadPageOffset = ($page - 1) * $threadsPerPage;

		$threads = $this->threadService->getThreadsFromBoard($this->board, $threadsPerPage, $threadPageOffset, $this->canViewDeleted);
		$totalThreads = $this->threadRepository->threadCountFromBoard($this->board, $this->canViewDeleted);

		$pte_vals = $this->buildPteVals(false);

		$pte_vals['{$FORMDAT}'] = $this->buildFormHtml(0, $pte_vals, $this->adminMode);

		$pte_vals['{$THREADS}'] = $this->renderThreadsToPteVals($threads, $pte_vals, $this->adminMode, $this->canViewDeleted,
			!$this->adminMode && !$this->canViewDeleted);

		$pte_vals['{$BOTTOM_PAGENAV}'] = drawLiveBoardPager($threadsPerPage, $totalThreads, $boardUrl, $this->board->getConfigValue('STATIC_HTML_UNTIL'), $this->board->getConfigValue('LIVE_INDEX_FILE'), $this->request);

		$pageData = $this->buildFullPage($pte_vals, $this->board->getBoardTitle(), 0, false, $this->adminMode);
		echo $this->finalizePageData($pageData);
	}

	/** The store for what every anonymous reader sees; null when this view is not that, or the cache is off. */
	private function fragmentCache(bool $anonymousView): ?threadFragmentCache {
		if (!$anonymousView || empty($this->config['THREAD_FRAGMENT_CACHE'])) {
			return null;
		}

		return $this->fragmentCache ??= threadFragmentCache::forBoard($this->board);
	}

	public function rebuildBoardHtml(bool $logRebuild = false): void {
		$totalThreadCount = $this->threadRepository->threadCountFromBoard($this->board);
		$threadsPerPage = $this->config['PAGE_DEF'];
		$totalPages = ceil($totalThreadCount / $threadsPerPage);

		$totalPagesToRebuild = match (true) {
			$this->config['STATIC_HTML_UNTIL'] === -1 => max(1, $totalPages),
			$this->config['STATIC_HTML_UNTIL'] === 0 => 0,
			default => max(1, min($this->config['STATIC_HTML_UNTIL'], $totalPages))
		};

		$threads = $totalPagesToRebuild > 0
			? $this->threadService->getThreadsFromBoard($this->board, $totalPagesToRebuild * $threadsPerPage)
			: [];

		[$pte_vals, $headerHtml, $formHtml, $footHtml] = $this->prepareStaticPageRenderContext();

		for ($page = 1; $page <= $totalPagesToRebuild; $page++) {
			$threadsInPage = array_slice($threads, ($page - 1) * $threadsPerPage, $threadsPerPage);
			$this->renderStaticPage($page, $threadsInPage, $totalThreadCount, $headerHtml, $formHtml, $footHtml, $pte_vals);
		}

		if ($logRebuild) {
			$this->actionLoggerService->logAction(
				"Rebuilt board: " . $this->board->getBoardTitle() . ' (' . $this->board->getBoardUID() . ')',
				$this->board->getBoardUID(),
				actionType::BOARD_REBUILD
			);
		}
	}

	public function rebuildBoardPages(int $lastPageToRebuild): void {
		if ($lastPageToRebuild < 1) return;

		$totalThreadCountForBoard = $this->threadRepository->threadCountFromBoard($this->board);
		$threadsPerPage = $this->config['PAGE_DEF'];

		$threads = $this->threadService->getThreadsFromBoard($this->board, $threadsPerPage * $lastPageToRebuild);

		[$pte_vals, $headerHtml, $formHtml, $footHtml] = $this->prepareStaticPageRenderContext();

		for ($page = 1; $page <= $lastPageToRebuild; $page++) {
			$threadsInPage = array_slice($threads, ($page - 1) * $threadsPerPage, $threadsPerPage);
			$this->renderStaticPage($page, $threadsInPage, $totalThreadCountForBoard, $headerHtml, $formHtml, $footHtml, $pte_vals);
		}
	}

	public function rebuildBoardPageHtml(int $targetPage, bool $logRebuild): void {
		if ($targetPage < 1) return;

		$totalThreadCountForBoard = $this->threadRepository->threadCountFromBoard($this->board);
		$threadsPerPage = $this->config['PAGE_DEF'];

		if ($targetPage > ceil($totalThreadCountForBoard / $threadsPerPage)) return;

		$threads = $this->threadService->getThreadsFromBoard($this->board, $threadsPerPage, ($targetPage - 1) * $threadsPerPage);

		[$pte_vals, $headerHtml, $formHtml, $footHtml] = $this->prepareStaticPageRenderContext();

		$this->renderStaticPage($targetPage, $threads, $totalThreadCountForBoard, $headerHtml, $formHtml, $footHtml, $pte_vals);

		if ($logRebuild) {
			$this->actionLoggerService->logAction(
				"Rebuilt board: " . $this->board->getBoardTitle() . ' (' . $this->board->getBoardUID() . ')',
				$this->board->getBoardUID(),
				actionType::BOARD_REBUILD
			);
		}
	}

	/** @param Thread[] $threadsInPage */
	private function renderStaticPage(int $page, array $threadsInPage, int $totalThreadCountForBoard, string $headerHtml, string $formHtml, string $footHtml, array $pte_vals): void {
		self::$renderingStaticHtml = true;
		try {
			$this->renderStaticPageHtml($page, $threadsInPage, $totalThreadCountForBoard, $headerHtml, $formHtml, $footHtml, $pte_vals);
		} finally {
			self::$renderingStaticHtml = false;
		}
	}

	private function renderStaticPageHtml(int $page, array $threadsInPage, int $totalThreadCountForBoard, string $headerHtml, string $formHtml, string $footHtml, array $pte_vals): void {
		$threadsPerPage = $this->config['PAGE_DEF'];
		$boardUrl = $this->board->getBoardURL();

		// a static page is what every anonymous reader sees, whoever triggered the rebuild
		$pte_vals['{$THREADS}'] = $this->renderThreadsToPteVals($threadsInPage, $pte_vals, false, false, true);

		$headerHtml = $this->board->getBoardHead($this->board->getBoardTitle());

		$pte_vals['{$BOTTOM_PAGENAV}'] = drawBoardPager(
			$threadsPerPage,
			$totalThreadCountForBoard,
			$boardUrl,
			$page,
			$this->board->getConfigValue('STATIC_HTML_UNTIL'),
			$this->board->getConfigValue('LIVE_INDEX_FILE'),
			$this->board->getConfigValue('STATIC_INDEX_FILE')
		);

    	$pageData = $this->buildStaticPageHtml($pte_vals, $headerHtml, $formHtml, $footHtml);

    	// Determine file name
    	$logfilename = ($page === 1) ? 'index.html' : $page . '.html';
    	$logFilePath = $this->board->getBoardCachedPath() . $logfilename;

    	// Open file for writing
    	if (($fp = fopen($logFilePath, 'w')) === false) {
    	    throw new \RuntimeException("Failed to open file for writing: $logFilePath");
    	}

    	// Disable internal write buffering
    	stream_set_write_buffer($fp, 0);

    	// Write in chunks if $pageData is large
    	$chunkSize = 1024 * 1024; // 1 MB chunks (adjust as needed)
    	$pageDataLen = strlen($pageData);
    	$offset = 0;

    	while ($offset < $pageDataLen) {
    	    fwrite($fp, substr($pageData, $offset, $chunkSize));
    	    $offset += $chunkSize;
    	}

    	// Close the file after writing
    	fclose($fp);

    	// Set file permissions
    	chmod($logFilePath, 0666);

	    // Free memory
	    unset($pageData);
	    unset($pte_vals);
	    unset($threadsInPage);
	    unset($threadRenderer);
	}


	/**
	 * The board's own thread-area values: the delete form lands the poster back on the board, and
	 * the board-scoped hooks run, which a cross-board page (the overboard) leaves out.
	 */
	private function buildPteVals(bool $isThreadView): array {
		return buildThreadAreaTemplateValues($this->moduleEngine, $isThreadView, $this->adminMode, [
			'boardScopedHooks' => true,
			'dispatchPlaceHolderIntercept' => true,
		]);
	}

	private function finalizePageData(string $pageData): string {
		// each pattern stops at its own field's end, so a multi-line comment or another field on
		// the same line is not swallowed
		$pageData = preg_replace('/id="com" class="inputtext">.*?<\/textarea>/s', 'id="com" class="inputtext"></textarea>', $pageData);
		$pageData = preg_replace('/name="email" id="email" value="[^"]*" class="inputtext">/', 'name="email" id="email" value="" class="inputtext">', $pageData);
		$pageData = preg_replace('/replyhl/', '', $pageData);
		if ($this->config['MINIFY_HTML']) {
			$pageData = html_minify($pageData);
		}
		return $pageData;
	}

	private function getThreadRenderer(array $quoteLinksFromBoard = []): threadRenderer {
		$rendererFactory = $this->rendererFactory();

		if ($quoteLinksFromBoard !== []) {
			$rendererFactory->setQuoteLinks($quoteLinksFromBoard);
		}

		return $rendererFactory->threadRendererFor($this->board);
	}

	/** One factory per request, so every view of the board shares its renderers. */
	private function rendererFactory(): boardRendererFactory {
		return $this->rendererFactory ??= new boardRendererFactory(
			$this->templateEngine,
			$this->moduleEngine,
			$this->request,
			$this->board
		);
	}

	private function buildFormHtml(int $resno, array &$pte_vals, bool $isStaff = false): string {
		$moduleInfoHook = $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);

		$postFormHtml = $this->board->getBoardPostForm($resno, $moduleInfoHook, '', '', '', '', '', $isStaff);
		
		return $postFormHtml;
	}

	private function buildFullPage(array $pte_vals, string $pageTitle, int $resno = 0, bool $isThreadView = false, bool $isStaff = false): string {
		$pageData = '';
		
		$pageData .= $this->board->getBoardHead($pageTitle, $resno, $isStaff);

		$pageData .= $this->templateEngine->ParseBlock('MAIN', $pte_vals);
		
		$pageData .= $this->board->getBoardFooter($isThreadView);
		
		return $pageData;
	}

	private function prepareStaticPageRenderContext(): array {
		// Everything built here is written to a file and served to everyone who asks for it,
		// rather than to the person whose request built it. Hooks that render something for the
		// reader — staff chrome, anything naming an account — have to know the difference, and
		// this is the only place the difference is known.
		self::$renderingStaticHtml = true;

		try {
			$pte_vals = $this->buildPteVals(false);

			$headerHtml = $this->board->getBoardHead($this->board->getBoardTitle());

			$formHtml = $this->buildFormHtml(0, $pte_vals);

			$footHtml = $this->board->getBoardFooter();

			return [$pte_vals, $headerHtml, $formHtml, $footHtml];
		} finally {
			self::$renderingStaticHtml = false;
		}
	}

	/**
	 * Draw a page of thread previews, reusing cached blocks and storing the ones drawn.
	 *
	 * @param Thread[] $threads
	 */
	private function renderThreadsToPteVals(array $threads, array $pte_vals, bool $adminMode, bool $includeDeleted, bool $cacheFragments): string {
		$pageRenderer = new threadPageRenderer(
			$this->rendererFactory(),
			$this->threadService,
			$this->quoteLinkService,
			$this->board,
			$adminMode,
			$includeDeleted,
			$cacheFragments
		);

		return $pageRenderer->renderThreads(
			$threads,
			[$this->board->getBoardUID() => $this->board],
			$this->config['RE_DEF'],
			threadFragmentCache::indexVariant($this->config['RE_DEF']),
			$pte_vals
		);
	}

	private function buildStaticPageHtml(array $pte_vals, string $headerHtml, string $formHtml, string $footHtml): string {
		$pageData = $headerHtml;
		$pageData .= $this->templateEngine->ParseBlock('MAIN', array_merge($pte_vals, ['{$FORMDAT}' => $formHtml]));
		$pageData .= $footHtml;
		return $this->finalizePageData($pageData);
	}

}