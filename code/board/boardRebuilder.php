<?php

class boardRebuilder {
	private array $config;

	public function __construct(
		private board $board, 
		private moduleEngine $moduleEngine, 
		private ?templateEngine $templateEngine, 
		private readonly postService $postService, 
		private readonly actionLoggerService $actionLoggerService, 
		private readonly threadRepository $threadRepository, 
		private readonly threadService $threadService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly quoteLinkService $quoteLinkService) {

		$this->config = $board->loadBoardConfig();
		if (empty($this->config)) {
			die("No board config for {$board->getBoardTitle()}:{$board->getBoardUID()}");
		}
	}

	public function drawThread(int $resno): void {

		$adminMode = isActiveStaffSession();

		$uid = $this->threadRepository->resolveThreadUidFromResno($this->board, $resno);
		$threadData = $this->threadService->getThreadByUID($uid);


		// Throw a 404 error if the thread isn't found
		if (!$threadData) {
			$this->softErrorHandler->errorAndExit("Thread not found!", 404);
			return;
		}

		// get the thread row
		$thread = $threadData['thread'];

		// get the posts from the thread
		$posts = $threadData['posts'];
		
		// get the post uids from the thread posts
		$postUids = array_column($posts, 'post_uid');

		// init hidden reply var
		$hiddenReply = 0;

		// get quote links for thread
		$quoteLinksFromBoard = $this->quoteLinkService->getQuoteLinksByPostUids($postUids);

		// init thread and post renderer
		$threadRenderer = $this->getThreadRenderer($quoteLinksFromBoard);

		// init template placeholders
		$pte_vals = $this->buildPteVals(true);
		
		// get form html
		$pte_vals['{$FORMDAT}'] = $this->buildFormHtml($resno, $pte_vals);

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
			$adminMode,
			0,
			'',
			'',
			$pte_vals
		);
		
		$pte_vals['{$PAGENAV}'] = '';

		$opPost = $posts[0];
		$boardTitle = $this->board->getBoardTitle();

		$pageTitle = $this->getThreadPageTitle($opPost, $boardTitle);

		$pageData = $this->buildFullPage($pte_vals, $pageTitle, $resno, true);
		echo $this->finalizePageData($pageData);
	}

	private function getThreadPageTitle(array $opPost, string $boardTitle): string {
		$subject = strip_tags($opPost['sub']); // thread subject/topic
		$comment = strip_tags($opPost['com']); // op post comment
		$fileName = strip_tags($opPost['fname'] . $opPost['ext']); // op post file name as unix timestamp

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
		// whether the user is logged in
		$adminMode = isActiveStaffSession();

		// url of the board
		$boardUrl = $this->board->getBoardURL();

		// threads to show per page
		$threadsPerPage = $this->config['PAGE_DEF'];

		// thread offset for pagination query
		$threadPageOffset = $page * $threadsPerPage;
		
		// max amounts of reply previews to show for each thread
		$previewCount = $this->config['RE_DEF'];

		// get threads + previews for this page
		$threadsInPage = $this->postService->getThreadPreviewsFromBoard($this->board, $previewCount, $threadsPerPage, $threadPageOffset);
		
		// post uids of posts that are rendered
		$postUidsInPage = getPostUidsFromThreadArrays($threadsInPage);

		// get all associated quote links for the page
		$quoteLinksFromPage = $this->quoteLinkService->getQuoteLinksByPostUids($postUidsInPage);

		// init thread renderer + post renderer
		$threadRenderer = $this->getThreadRenderer($quoteLinksFromPage);

		// total thread count
		$totalThreads = count($this->threadService->getThreadListFromBoard($this->board));

		// init placeholder template values
		$pte_vals = $this->buildPteVals(false);

		// build form html
		$pte_vals['{$FORMDAT}'] = $this->buildFormHtml(0, $pte_vals);

		// render thread html
		$pte_vals['{$THREADS}'] = $this->renderThreadsToPteVals($threadsInPage, $threadRenderer, $threadsInPage, $pte_vals, $adminMode);

		// thread pager
		$pte_vals['{$PAGENAV}'] = drawLiveBoardPager($threadsPerPage, $totalThreads, $boardUrl, $this->board->getConfigValue('STATIC_HTML_UNTIL'), $this->board->getConfigValue('LIVE_INDEX_FILE'), [$this->softErrorHandler, 'errorAndExit']);

		// generate the whole page's html
		$pageData = $this->buildFullPage($pte_vals, $this->board->getBoardTitle());
		
		// now output the page's html
		echo $this->finalizePageData($pageData);
	}

	
	public function rebuildBoardHtml(bool $logRebuild = false): void {
		$threads = $this->postService->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF']);
		$totalThreads = count($threads);
		$threadsPerPage = $this->config['PAGE_DEF'];
		$totalPages = ceil($totalThreads / $threadsPerPage);

		$totalPagesToRebuild = match (true) {
			$this->config['STATIC_HTML_UNTIL'] === -1 => max(1, $totalPages),
			$this->config['STATIC_HTML_UNTIL'] === 0 => 0,
			default => max(1, min($this->config['STATIC_HTML_UNTIL'], $totalPages))
		};

		// get all associated quote links for the page
		$quoteLinksFromBoard = $this->quoteLinkService->getQuoteLinksByBoardUid($this->board->getBoardUID());

		[$pte_vals, $headerHtml, $formHtml, $footHtml] = $this->prepareStaticPageRenderContext();

		for ($page = 0; $page < $totalPagesToRebuild; $page++) {
			$this->renderStaticPage($page, $threads, $totalThreads, $headerHtml, $formHtml, $footHtml, $pte_vals, false, $quoteLinksFromBoard);
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

		$threads = $this->postService->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF'], $amountOfThreads, 0);

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

		$threads = $this->postService->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF'], $limit, $offset);
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

		$threadsInPage = $threadsAreSliced
			? $threads
			: array_slice($threads, $page * $threadsPerPage, $threadsPerPage);

		$pte_vals['{$THREADS}'] = $this->renderThreadsToPteVals($threadsInPage, $threadRenderer, $threads, $pte_vals);

		$pte_vals['{$PAGENAV}'] = drawBoardPager($threadsPerPage, $totalThreadCountForBoard, $boardUrl, $page,  $this->board->getConfigValue('STATIC_HTML_UNTIL'), $this->board->getConfigValue('LIVE_INDEX_FILE'), $this->board->getConfigValue('STATIC_INDEX_FILE'), [$this->softErrorHandler, 'errorAndExit']);

		$pageData = $this->buildStaticPageHtml($pte_vals, $headerHtml, $formHtml, $footHtml);

		$logfilename = ($page === 0) ? 'index.html' : $page . '.html';
		$logFilePath = $this->board->getBoardCachedPath() . $logfilename;

		if (($fp = fopen($logFilePath, 'w')) === false) {
			throw new \RuntimeException("Failed to open file for writing: $logFilePath");
		}

		stream_set_write_buffer($fp, 0);
		fwrite($fp, $pageData);
		fclose($fp);
		chmod($logFilePath, 0666);
	}

	private function buildPteVals(bool $isThreadView): array {
		$adminMode = isActiveStaffSession();
	
		$pte_vals = [
			'{$THREADS}' => '',
			'{$THREADFRONT}' => '',
			'{$THREADREAR}' => '',
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">' . _T('del_head'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$DEL_PASS_TEXT}' => ($adminMode ? '<input type="hidden" name="func" value="delete">' : '') . _T('del_pass'),
			'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
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
			$postRenderer
		);
		return $threadRenderer;
	}

	private function buildFormHtml(int $resno, array &$pte_vals): string {
		$moduleInfoHook = $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);

		$postFormHtml = $this->board->getBoardPostForm($resno, $moduleInfoHook, '', '', '', '', '', );
		
		return $postFormHtml;
	}

	private function runThreadModuleHooks(array &$pte_vals, int $resno): void {
		$this->moduleEngine->dispatch('AboveThreadArea', array(&$pte_vals['{$THREADFRONT}'], empty($resno)));
		$this->moduleEngine->dispatch('BelowThreadArea', array(&$pte_vals['{$THREADREAR}'], empty($resno)));
		$this->moduleEngine->dispatch('PlaceHolderIntercept', [&$pte_vals]);
	}

	private function buildFullPage(array $pte_vals, string $pageTitle, int $resno = 0, bool $isThreadView = false): string {
		$pageData = '';
		
		$pageData .= $this->board->getBoardHead($pageTitle, $resno);

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

	private function renderThreadsToPteVals(array $threadsInPage, threadRenderer $threadRenderer, array $allThreads, array $pte_vals, bool $adminMode = false): string {
		$output = '';
		foreach ($threadsInPage as $i => $data) {
			$output .= $threadRenderer->render($allThreads,
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