<?php

class boardRebuilder {
	private board $board;
	private array $config;
	private globalHTML $globalHTML;
	private moduleEngine $moduleEngine;
	private mixed $PIO;
	private mixed $threadSingleton;
	private mixed $actionLogger;
	private staffAccountFromSession $staffSession;
	private templateEngine $templateEngine;


	public function __construct(board $board, templateEngine $templateEngine) {
		$this->board = $board;

		$this->globalHTML = new globalHTML($board);
		$this->moduleEngine = new moduleEngine($board);
		$this->staffSession = new staffAccountFromSession;
		$this->threadSingleton = threadSingleton::getInstance();
		$this->PIO = PIOPDO::getInstance();
		$this->actionLogger = actionLogger::getInstance();


		$this->templateEngine = $templateEngine;
		$this->templateEngine->setFunctionCallbacks([
			[
				'callback' => function (&$ary_val) {
					$this->moduleEngine->useModuleMethods('BlotterPreview', [&$ary_val['{$BLOTTER}']]);
				},
			],
			[
				'callback' => function (&$ary_val) {
					$this->moduleEngine->useModuleMethods('GlobalMessage', [&$ary_val['{$GLOBAL_MESSAGE}']]);
				},
			],
		]);


		$this->config = $board->loadBoardConfig();
		if (empty($this->config)) {
			die("No board config for {$board->getBoardTitle()}:{$board->getBoardUID()}");
		}
	}

	public function drawThread(int $resno): void {
		$quoteLinksFromBoard = getQuoteLinksFromBoard($this->board);

		$threadRenderer = new threadRenderer($this->board, $this->config, $this->globalHTML, $this->moduleEngine, $this->templateEngine, $quoteLinksFromBoard);
		$roleLevel = $this->staffSession->getRoleLevel();
		$adminMode = $roleLevel >= $this->config['roles']['LEV_JANITOR'];

		$uid = $this->threadSingleton->resolveThreadUidFromResno($this->board, $resno);


		$threadData = $this->threadSingleton->getThreadByUID($uid);

		if (!$threadData) {
			$this->globalHTML->error("Thread not found!");
			return;
		}

		$thread = $threadData['thread'];
		$posts = $threadData['posts'];
		$hiddenReply = 0;

		$pte_vals = [
			'{$THREADS}' => '',
			'{$THREADFRONT}' => '',
			'{$THREADREAR}' => '',
			'{$SELF}' => $this->config['PHP_SELF'],
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">' . _T('del_head'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$DEL_PASS_TEXT}' => ($adminMode ? '<input type="hidden" name="func" value="delete">' : '') . _T('del_pass'),
			'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="' . _T('del_btn') . '">',
			'{$IS_THREAD}' => true,
		];

		$this->moduleEngine->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], 0)); // "ThreadFront" Hook Point
		$this->moduleEngine->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], 0)); // "ThreadRear" Hook Point

		$pageData = '';
		$this->globalHTML->head($pageData);

		$form_dat = '';
		$this->globalHTML->form($form_dat, $resno);
		$form_dat .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);
		$pte_vals['{$FORMDAT}'] = $form_dat;

		// Render the thread
		$pte_vals['{$THREADS}'] .= $threadRenderer->render([],
			true,
			$thread,
			$posts,
			$hiddenReply,
			$uid,
			[],
			false,
			true,
			$adminMode,
			0
		);
		// No pagination for single thread view
		$pte_vals['{$PAGENAV}'] = '';

		$pageData .= $this->templateEngine->ParseBlock('MAIN', $pte_vals);
		$this->globalHTML->foot($pageData);

		$pageData = preg_replace('/id="com" class="inputtext">(.*)<\/textarea>/', 'id="com" class="inputtext"></textarea>', $pageData);
		$pageData = preg_replace('/name="email" id="email" value="(.*)" class="inputtext">/', 'name="email" id="email" value="" class="inputtext">', $pageData);
		$pageData = preg_replace('/replyhl/', '', $pageData);

		if ($this->config['MINIFY_HTML']) {
			$pageData = html_minify($pageData);
		}

		// Output directly
		echo $pageData;
	}

	public function drawPage(int $page = 0): void {
		$quoteLinksFromBoard = getQuoteLinksFromBoard($this->board);

		$threadRenderer = new threadRenderer($this->board, $this->config, $this->globalHTML, $this->moduleEngine, $this->templateEngine, $quoteLinksFromBoard);
		$roleLevel = $this->staffSession->getRoleLevel();
		$adminMode = $roleLevel >= $this->config['roles']['LEV_JANITOR'];

		$boardUrl = $this->board->getBoardURL();

		$threadsPerPage = $this->config['PAGE_DEF'];
		$threadPageOffset = $page * $threadsPerPage;
		$previewCount = $this->config['RE_DEF'];


		// whether to use the live frontend when viewing the page
		$isLiveFrontend = isset($_GET['page']);

		$threadsInPage = $this->PIO->getThreadPreviewsFromBoard($this->board, $previewCount, $threadsPerPage, $threadPageOffset);
		
		$totalThreads = count($this->threadSingleton->fetchThreadListFromBoard($this->board));

		$pte_vals = [
			'{$THREADS}' => '',
			'{$THREADFRONT}' => '',
			'{$THREADREAR}' => '',
			'{$SELF}' => $this->config['PHP_SELF'],
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">' . _T('del_head'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$DEL_PASS_TEXT}' => ($adminMode ? '<input type="hidden" name="func" value="delete">' : '') . _T('del_pass'),
			'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="' . _T('del_btn') . '">',
			'{$IS_THREAD}' => false,
		];

		$this->moduleEngine->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], 0)); // "ThreadFront" Hook Point
		$this->moduleEngine->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], 0)); // "ThreadRear" Hook Point

		$pageData = '';
		$this->globalHTML->head($pageData);

		$form_dat = '';
		$this->globalHTML->form($form_dat, 0);
		$form_dat .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);
		$pte_vals['{$FORMDAT}'] = $form_dat;

		foreach ($threadsInPage as $i => $data) {
			$thread = $data['thread'];
			$posts = $data['posts'];
			$hiddenReply = $data['hidden_reply_count'];
			$threadUID = $data['thread_uid'];

			$pte_vals['{$THREADS}'] .= $threadRenderer->render($threadsInPage,
				false,
				$thread,
				$posts,
				$hiddenReply,
				$threadUID,
				[],
				false,
				true,
				$adminMode,
				$i
			);
		}

		$pte_vals['{$PAGENAV}'] = $this->globalHTML->drawLiveBoardPager($threadsPerPage, $totalThreads, $boardUrl);

		$pageData .= $this->templateEngine->ParseBlock('MAIN', $pte_vals);
		$this->globalHTML->foot($pageData);

		$pageData = preg_replace('/id="com" class="inputtext">(.*)<\/textarea>/', 'id="com" class="inputtext"></textarea>', $pageData);
		$pageData = preg_replace('/name="email" id="email" value="(.*)" class="inputtext">/', 'name="email" id="email" value="" class="inputtext">', $pageData);
		$pageData = preg_replace('/replyhl/', '', $pageData);

		if ($this->config['MINIFY_HTML']) {
			$pageData = html_minify($pageData);
		}

		// Directly echo output (dynamic)
		echo $pageData;
	}
	
	public function rebuildBoardHtml(bool $logRebuild = false): void {
		$threads = $this->PIO->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF']);
		$totalThreads = count($threads);
		$threadsPerPage = $this->config['PAGE_DEF'];
		$totalPages = ceil($totalThreads / $threadsPerPage);
	
		$totalPagesToRebuild = match (true) {
			$this->config['STATIC_HTML_UNTIL'] === -1 => $totalPages,
			$this->config['STATIC_HTML_UNTIL'] === 0 => 0,
			default => min($this->config['STATIC_HTML_UNTIL'], $totalPages)
		};
	
		$pte_vals = $this->buildPteVals();
	
		$headerHtml = '';
		$this->globalHTML->head($headerHtml);
	
		$formHtml = '';
		$this->globalHTML->form($formHtml, 0);
		$formHtml .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);
	
		$footHtml = '';
		$this->globalHTML->foot($footHtml);
	
		for ($page = 0; $page < $totalPagesToRebuild; $page++) {
			$this->renderStaticPage($page, $threads, $headerHtml, $formHtml, $footHtml, $pte_vals);
		}
	
		if ($logRebuild) {
			$this->actionLogger->logAction(
				"Rebuilt board: " . $this->board->getBoardTitle() . ' (' . $this->board->getBoardUID() . ')',
				$this->board->getBoardUID()
			);
		}
	}
	

	private function renderStaticPage(int $page, array $threads, string $headerHtml, string $formHtml, string $footHtml, array $pte_vals): void {
		$quoteLinksFromBoard = getQuoteLinksFromBoard($this->board);

		$threadRenderer = new threadRenderer($this->board, $this->config, $this->globalHTML, $this->moduleEngine, $this->templateEngine, $quoteLinksFromBoard);
		$threadsPerPage = $this->config['PAGE_DEF'];
	
		$boardUrl = $this->board->getBoardURL();

		$totalThreads = count($threads);

		$threadsInPage = array_slice($threads, $page * $threadsPerPage, $threadsPerPage);
	
		$pte_vals['{$THREADS}'] = '';
		foreach ($threadsInPage as $i => $data) {
			$thread = $data['thread'];
			$posts = $data['posts'];
			$hiddenReply = $data['hidden_reply_count'];
			$threadUID = $data['thread_uid'];
	
			$pte_vals['{$THREADS}'] .= $threadRenderer->render(
				$threads,
				false,
				$thread,
				$posts,
				$hiddenReply,
				$threadUID,
				[],
				false,
				true,
				false,
				$i
			);
		}
	
		$pte_vals['{$PAGENAV}'] = $this->globalHTML->drawBoardPager($threadsPerPage, $totalThreads, $boardUrl, $page);
	
		$pageData = $headerHtml;
		$pageData .= $this->templateEngine->ParseBlock('MAIN', array_merge($pte_vals, ['{$FORMDAT}' => $formHtml]));
		$pageData .= $footHtml;
	
		// Strip any user-entered form data
		$pageData = preg_replace('/id="com" class="inputtext">(.*)<\/textarea>/', 'id="com" class="inputtext"></textarea>', $pageData);
		$pageData = preg_replace('/name="email" id="email" value="(.*)" class="inputtext">/', 'name="email" id="email" value="" class="inputtext">', $pageData);
		$pageData = preg_replace('/replyhl/', '', $pageData);
	
		if ($this->config['MINIFY_HTML']) {
			$pageData = html_minify($pageData);
		}
	
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

	public function rebuildBoardPageHtml(int $targetPage, bool $logRebuild): void {
		if ($targetPage < 0) return;
	
		$threads = $this->PIO->getThreadPreviewsFromBoard($this->board, $this->config['RE_DEF']);
		$totalPages = ceil(count($threads) / $this->config['PAGE_DEF']);
	
		if ($targetPage >= $totalPages) return; // Out of bounds
	
		$pte_vals = $this->buildPteVals();
	
	
		$headerHtml = '';
		$this->globalHTML->head($headerHtml);
	
		$formHtml = '';
		$this->globalHTML->form($formHtml, 0);
		$formHtml .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);
	
		$footHtml = '';
		$this->globalHTML->foot($footHtml);
	
		$this->renderStaticPage($targetPage, $threads, $headerHtml, $formHtml, $footHtml, $pte_vals);

		if ($logRebuild) {
			$this->actionLogger->logAction("Rebuilt board: " . $this->board->getBoardTitle() . ' (' . $this->board->getBoardUID() . ')', $this->board->getBoardUID());
		}
	}

	private function buildPteVals(): array {
		$adminMode = $this->staffSession->getRoleLevel() >= $this->config['roles']['LEV_JANITOR'];
	
		$pte_vals = [
			'{$THREADS}' => '',
			'{$THREADFRONT}' => '',
			'{$THREADREAR}' => '',
			'{$SELF}' => $this->config['PHP_SELF'],
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">' . _T('del_head'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$DEL_PASS_TEXT}' => ($adminMode ? '<input type="hidden" name="func" value="delete">' : '') . _T('del_pass'),
			'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="' . _T('del_btn') . '">',
			'{$IS_THREAD}' => false,
		];
	
		$this->moduleEngine->useModuleMethods('ThreadFront', [&$pte_vals['{$THREADFRONT}'], 0]);
		$this->moduleEngine->useModuleMethods('ThreadRear', [&$pte_vals['{$THREADREAR}'], 0]);
	
		return $pte_vals;
	}
	

}