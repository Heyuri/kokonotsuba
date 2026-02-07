<?php

class boardRebuilder {
	private array $config;
	private bool $adminMode, $canViewDeleted;

	public function __construct(
		private board $board, 
		private moduleEngine $moduleEngine, 
		private templateEngine $templateEngine, 
		private readonly actionLoggerService $actionLoggerService, 
		private readonly threadRepository $threadRepository, 
		private readonly threadService $threadService,
		private readonly quoteLinkService $quoteLinkService,
		private postRenderingPolicy $postRenderingPolicy) {

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
		// resolve the thread uid from the thread number
		$uid = $this->threadRepository->resolveThreadUidFromResno($this->board, $threadNumber);
		
		// get preview count
		$previewCount = $this->board->getConfigValue('RE_DEF', 5);

		// get replies per thread page
		$repliesPerPage = $this->board->getConfigValue('REPLIES_PER_PAGE', 200);
		
		// get the thread and decide how to fetch its data based on the provided parameters
		$threadData = $this->getThreadForRendering($uid, $previewCount, $repliesPerPage, $page, $amountOfRepliesToRender);
		
		// throw 404 error if no thread data is found
		// otherwise it'll just dump errors to error log - its data-related and not code-related
		if ($threadData === false) {
			throw new BoardException(_T('thread_not_found'), 404);
			return;
		}

		// get the thread row
		$thread = $threadData['thread'];

		// get the total amount of posts in the thread
		$totalPosts = $thread['number_of_posts'];

		// whether the thread has been deleted
		$threadDeleted = $thread['thread_deleted'] ?? null;

		// whether it was a file-only deletion
		$fileOnly = $thread['thread_attachment_deleted'] ?? null;

		// hard deleted (a la, thread itself was deleted and the file isn't what was deleted)
		$hardDeleted = $threadDeleted && !$fileOnly;

		// Throw a 404 error if the thread isn't found
		// Also throw a 404 if the thread was deleted
		if (!$threadData || ($hardDeleted)) {
			throw new BoardException(_T('thread_not_found'), 404);
			return;
		}

		// get the posts from the thread
		$posts = $threadData['posts'];
		
		// get the post uids from the thread posts
		$postUids = $threadData['post_uids'];

		// init hidden reply var
		$hiddenReply = 0;

		// generate thread url
		$threadUrl = $this->board->getBoardThreadURL($threadNumber);

		// get quote links for thread
		$quoteLinksFromBoard = $this->quoteLinkService->getQuoteLinksByPostUids($postUids, $this->canViewDeleted);

		// init thread and post renderer
		$threadRenderer = $this->getThreadRenderer($quoteLinksFromBoard);

		// init template placeholders
		$pte_vals = $this->buildPteVals(true);
		
		// if we want to render the post form then build form html and bind to template parameter
		if($showPostForm) {
			// get form html
			$pte_vals['{$FORMDAT}'] = $this->buildFormHtml($threadNumber, $pte_vals, $this->adminMode);
		}

		// Dispatch viewed thread hook
		// This hook is one that only gets dispatched for threads that are being viewed through drawThread
		$this->moduleEngine->dispatch('ViewedThread', [&$pte_vals, &$threadData]);

		// Render threads
		$pte_vals['{$THREADS}'] .= $threadRenderer->render([],
			true,
			$thread,
			$posts,
			$hiddenReply,
			false,
			$this->adminMode,
			0,
			'',
			'',
			$pte_vals
		);
		
		// if a non-null page value is set - then draw the pager
		if(!is_null($page)) {
			// get 'top pager for threads' config value
			$enableTopPager = $this->board->getConfigValue('TOP_THREAD_PAGER', false);

			// if the top pager for threads is enabled, render it
			if($enableTopPager) {
				$pte_vals['{$TOP_PAGENAV}'] = drawPager($repliesPerPage, $totalPosts, $threadUrl);
			}

			// always draw bottom pager
			$pte_vals['{$BOTTOM_PAGENAV}'] = drawPager($repliesPerPage, $totalPosts, $threadUrl);
		}

		$opPost = $posts[0];
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
		?int $amountOfRepliesToRender
	): false|array {
		// Fetch thread with a limited amount of replies	
		if(!is_null($amountOfRepliesToRender)) {
			// fetch a 'last X replies' thread
			$threadData = $this->threadService->getThreadLastReplies(
				$threadUid, 
				$this->canViewDeleted, 
				$previewCount, 
				$amountOfRepliesToRender
			);

		}
		// Fetch thread with pages
		else if(!is_null($page)) {
			// fetch a paged thread
			$threadData = $this->threadService->getThreadPaged($threadUid, $this->canViewDeleted, $previewCount, $repliesPerPage, $page);
		}
		// Fetch unpaged thread (intensive)
		else {
			// get the while thing
			$threadData = $this->threadService->getThreadAllReplies($threadUid, $this->canViewDeleted, $previewCount);
		}

		// then return the thread data
		return $threadData;
	}

	private function getThreadPageTitle(array $opPost, string $boardTitle): string {
		$subject = strip_tags($opPost['sub']); // thread subject/topic
		$comment = strip_tags($opPost['com']); // op post comment
		
		// first array key
		$firstAttachmentArrKey = array_key_first($opPost['attachments']);

		// get the first attachment
		$firstAttachment = $opPost['attachments'][$firstAttachmentArrKey] ?? null; 

		// set first filename if it exists
		$firstAttachment ? $fileName = strip_tags($firstAttachment['fileName'] . '.' . $firstAttachment['fileExtension']) : $fileName = null;

		// Max length before truncating strings
		$maxTitleLength = 20;
		
		// no sanitization is done here because kokonotsuba stores them sanitized
		// first, have it include the subject + board title 
		if(!empty($subject)) {
			// truncate the subject
			$truncateSubject = truncateText($subject, $maxTitleLength);

			$threadTitle = "$truncateSubject - $boardTitle";
		} 
		// then try the comment
		else if(!empty($comment) && $comment !== $this->config['DEFAULT_NOCOMMENT']) {
			// truncate the comment
			$truncatedComment = truncateText($comment, $maxTitleLength);

			$threadTitle = $truncatedComment . ' - ' . $boardTitle;
		} 
		// then try the file name (useful for dump/flash boards)
		else if(!empty($fileName)) {
			// truncate file name
			$truncateFileName = truncateText($fileName, $maxTitleLength);

			$threadTitle = $truncateFileName . ' - ' . $boardTitle;
		}
		// otherwise, just use the board title
		else { 
			$threadTitle = $boardTitle;
		}

		return $threadTitle;

	}

	public function drawPage(int $page = 0): void {
		// url of the board
		$boardUrl = $this->board->getBoardURL();

		// threads to show per page
		$threadsPerPage = $this->config['PAGE_DEF'];

		// thread offset for pagination query
		$threadPageOffset = $page * $threadsPerPage;
		
		// max amounts of reply previews to show for each thread
		$previewCount = $this->config['RE_DEF'];

		// get threads + previews for this page
		$threadsInPage = $this->threadService->getThreadPreviewsFromBoard($this->board, $previewCount, $threadsPerPage, $threadPageOffset, $this->canViewDeleted);
		
		// post uids of posts that are rendered
		$postUidsInPage = getPostUidsFromThreadArrays($threadsInPage);

		// get all associated quote links for the page
		$quoteLinksFromPage = $this->quoteLinkService->getQuoteLinksByPostUids($postUidsInPage, $this->canViewDeleted);

		// init thread renderer + post renderer
		$threadRenderer = $this->getThreadRenderer($quoteLinksFromPage);

		// total thread count
		$totalThreads = $this->threadRepository->threadCountFromBoard($this->board, $this->canViewDeleted);

		// init placeholder template values
		$pte_vals = $this->buildPteVals(false);

		// build form html
		$pte_vals['{$FORMDAT}'] = $this->buildFormHtml(0, $pte_vals, $this->adminMode);

		// render thread html
		$pte_vals['{$THREADS}'] = $this->renderThreadsToPteVals($threadsInPage, $threadRenderer, $pte_vals, $this->adminMode);

		// thread pager
		$pte_vals['{$BOTTOM_PAGENAV}'] = drawLiveBoardPager($threadsPerPage, $totalThreads, $boardUrl, $this->board->getConfigValue('STATIC_HTML_UNTIL'), $this->board->getConfigValue('LIVE_INDEX_FILE'));

		// generate the whole page's html
		$pageData = $this->buildFullPage($pte_vals, $this->board->getBoardTitle(), 0, false, $this->adminMode);
		
		// now output the page's html
		echo $this->finalizePageData($pageData);
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

		// database offset
		$threadPreviewAmount = $totalPagesToRebuild * $threadsPerPage;

		// get paginated thread previews
		$threads = $this->threadService->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF'], $threadPreviewAmount);

		// all post uids from the threads
		$postUidsFromThreads = getPostUidsFromThreadArrays($threads);

		// get all associated quote links for the page
		$quoteLinksFromBoard = $this->quoteLinkService->getQuoteLinksByPostUids($postUidsFromThreads);

		[$pte_vals, $headerHtml, $formHtml, $footHtml] = $this->prepareStaticPageRenderContext();

		for ($page = 0; $page < $totalPagesToRebuild; $page++) {
			$this->renderStaticPage($page, $threads, $totalThreadCount, $headerHtml, $formHtml, $footHtml, $pte_vals, false, $quoteLinksFromBoard);
		}

		if ($logRebuild) {
			$this->actionLoggerService->logAction(
				"Rebuilt board: " . $this->board->getBoardTitle() . ' (' . $this->board->getBoardUID() . ')',
				$this->board->getBoardUID()
			);
		}
	}

	public function rebuildBoardPages(int $lastPageToRebuild): void {
		if ($lastPageToRebuild < 0) return;

		$totalThreadCountForBoard = $this->threadRepository->threadCountFromBoard($this->board);
		$threadsPerPage = $this->config['PAGE_DEF'];
		$amountOfThreads = $threadsPerPage * ($lastPageToRebuild + 1);

		$threads = $this->threadService->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF'], $amountOfThreads, 0);

		// post uids of posts that are rendered
		$postUidsInPage = getPostUidsFromThreadArrays($threads);

		// get all associated quote links for the page
		$quoteLinksFromPage = $this->quoteLinkService->getQuoteLinksByPostUids($postUidsInPage);

		[$pte_vals, $headerHtml, $formHtml, $footHtml] = $this->prepareStaticPageRenderContext();

		for ($page = 0; $page <= $lastPageToRebuild; $page++) {
			$this->renderStaticPage($page, $threads, $totalThreadCountForBoard, $headerHtml, $formHtml, $footHtml, $pte_vals, false, $quoteLinksFromPage);
		}
	}

	public function rebuildBoardPageHtml(int $targetPage, bool $logRebuild): void {
		if ($targetPage < 0) return;

		$totalThreadCountForBoard = $this->threadRepository->threadCountFromBoard($this->board);

		$offset = $targetPage * $this->config['PAGE_DEF'];
		$limit = $this->config['PAGE_DEF'];

		$threads = $this->threadService->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF'], $limit, $offset);
		$totalPages = ceil($totalThreadCountForBoard / $this->config['PAGE_DEF']);

		if ($targetPage >= $totalPages) return;

		// post uids of posts that are rendered
		$postUidsInPage = getPostUidsFromThreadArrays($threads);

		// get all associated quote links for the page
		$quoteLinksFromPage = $this->quoteLinkService->getQuoteLinksByPostUids($postUidsInPage);

		[$pte_vals, $headerHtml, $formHtml, $footHtml] = $this->prepareStaticPageRenderContext();
		
		$this->renderStaticPage($targetPage, $threads, $totalThreadCountForBoard, $headerHtml, $formHtml, $footHtml, $pte_vals, true, $quoteLinksFromPage);

		if ($logRebuild) {
			$this->actionLoggerService->logAction(
				"Rebuilt board: " . $this->board->getBoardTitle() . ' (' . $this->board->getBoardUID() . ')',
				$this->board->getBoardUID()
			);
		}
	}


	private function renderStaticPage(int $page, array $threads, int $totalThreadCountForBoard, string $headerHtml, string $formHtml, string $footHtml, array $pte_vals, bool $threadsAreSliced, array $quoteLinksFromBoard): void {
    	$threadRenderer = $this->getThreadRenderer($quoteLinksFromBoard);

    	$threadsPerPage = $this->config['PAGE_DEF'];
    	$boardUrl = $this->board->getBoardURL();

    	// Slice threads or use all of them depending on the flag
    	$threadsInPage = $threadsAreSliced
    	    ? $threads
    	    : array_slice($threads, $page * $threadsPerPage, $threadsPerPage);

    	// Render thread data to PTE values
    	$pte_vals['{$THREADS}'] = $this->renderThreadsToPteVals($threadsInPage, $threadRenderer, $pte_vals);

    	// Render page navigation
    	$pte_vals['{$BOTTOM_PAGENAV}'] = drawBoardPager(
    	    $threadsPerPage,
    	    $totalThreadCountForBoard,
    	    $boardUrl,
    	    $page,
    	    $this->board->getConfigValue('STATIC_HTML_UNTIL'),
    	    $this->board->getConfigValue('LIVE_INDEX_FILE'),
    	    $this->board->getConfigValue('STATIC_INDEX_FILE')
    	);

    	// Build static page HTML
    	$pageData = $this->buildStaticPageHtml($pte_vals, $headerHtml, $formHtml, $footHtml);

    	// Determine file name
    	$logfilename = ($page === 0) ? 'index.html' : $page . '.html';
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


	private function buildPteVals(bool $isThreadView): array {
		$pte_vals = [
			'{$THREADS}' => '',
			'{$THREADFRONT}' => '',
			'{$THREADREAR}' => '',
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">' . _T('del_head'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$DEL_PASS_TEXT}' => ($this->adminMode ? '<input type="hidden" name="func" value="delete">' : '') . _T('del_pass'),
			'<input type="hidden" name="func" value="delete"> <input type="password" class="inputtext" name="pwd" id="pwd2" value="">' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="' . _T('del_btn') . '">',
			'{$IS_THREAD}' => $isThreadView,
		];

		$this->runThreadModuleHooks($pte_vals, $isThreadView);
	
		return $pte_vals;
	}
	
	private function finalizePageData(string $pageData): string {
		$pageData = preg_replace('/id="com" class="inputtext">(.*)<\/textarea>/', 'id="com" class="inputtext"></textarea>', $pageData);
		$pageData = preg_replace('/name="email" id="email" value="(.*)" class="inputtext">/', 'name="email" id="email" value="" class="inputtext">', $pageData);
		$pageData = preg_replace('/replyhl/', '', $pageData);
		if ($this->config['MINIFY_HTML']) {
			$pageData = html_minify($pageData);
		}
		return $pageData;
	}

	private function getThreadRenderer(array $quoteLinksFromBoard = []): threadRenderer {
		$postRenderer = new postRenderer(
			$this->board,
			$this->config,
			$this->moduleEngine,
			$this->templateEngine,
			$quoteLinksFromBoard
		);
		$threadRenderer = new threadRenderer(
			$this->config,
			$this->templateEngine,
			$postRenderer,
			$this->moduleEngine
		);
		return $threadRenderer;
	}

	private function buildFormHtml(int $resno, array &$pte_vals, bool $isStaff = false): string {
		$moduleInfoHook = $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);

		$postFormHtml = $this->board->getBoardPostForm($resno, $moduleInfoHook, '', '', '', '', '', $isStaff);
		
		return $postFormHtml;
	}

	private function runThreadModuleHooks(array &$pte_vals, int $resno): void {
		$this->moduleEngine->dispatch('AboveThreadArea', array(&$pte_vals['{$THREADFRONT}'], empty($resno)));
		$this->moduleEngine->dispatch('BelowThreadArea', array(&$pte_vals['{$THREADREAR}'], empty($resno)));
		$this->moduleEngine->dispatch('PlaceHolderIntercept', [&$pte_vals]);
	}

	private function buildFullPage(array $pte_vals, string $pageTitle, int $resno = 0, bool $isThreadView = false, bool $isStaff = false): string {
		$pageData = '';
		
		$pageData .= $this->board->getBoardHead($pageTitle, $resno, $isStaff);

		$pageData .= $this->templateEngine->ParseBlock('MAIN', $pte_vals);
		
		$pageData .= $this->board->getBoardFooter($isThreadView);
		
		return $pageData;
	}

	private function prepareStaticPageRenderContext(): array {
		$pte_vals = $this->buildPteVals(false);

		$headerHtml = $this->board->getBoardHead($this->board->getBoardTitle());

		$formHtml = $this->buildFormHtml(0, $pte_vals);

		$footHtml = $this->board->getBoardFooter();

		return [$pte_vals, $headerHtml, $formHtml, $footHtml];
	}

	private function renderThreadsToPteVals(array $threadsInPage, threadRenderer $threadRenderer, array $pte_vals, bool $adminMode = false): string {
		$output = '';
		foreach ($threadsInPage as $i => $data) {
			$output .= $threadRenderer->render($threadsInPage,
				false,
				$data['thread'],
				$data['posts'],
				$data['hidden_reply_count'],
				false,
				$adminMode,
				$i,
				'',
				'',
				$pte_vals
			);
		}
		return $output;
	}

	private function buildStaticPageHtml(array $pte_vals, string $headerHtml, string $formHtml, string $footHtml): string {
		$pageData = $headerHtml;
		$pageData .= $this->templateEngine->ParseBlock('MAIN', array_merge($pte_vals, ['{$FORMDAT}' => $formHtml]));
		$pageData .= $footHtml;
		return $this->finalizePageData($pageData);
	}

}