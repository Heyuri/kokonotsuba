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


	public function __construct(board $board, templateEngine $templateEngine)
	{
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
		$threadRenderer = new threadRenderer($this->board, $this->config, $this->globalHTML, $this->moduleEngine, $this->templateEngine);
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
		$pte_vals['{$THREADS}'] .= $threadRenderer->render(true,
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
		$threadRenderer = new threadRenderer($this->board, $this->config, $this->globalHTML, $this->moduleEngine, $this->templateEngine);
		$roleLevel = $this->staffSession->getRoleLevel();
		$adminMode = $roleLevel >= $this->config['roles']['LEV_JANITOR'];

		$threadsPerPage = $this->config['PAGE_DEF'];
		$threadPageOffset = $page * $threadsPerPage;
		$previewCount = $this->config['RE_DEF'];

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

			$pte_vals['{$THREADS}'] .= $threadRenderer->render(false,
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

		$prev = $page - 1;
		$next = $page + 1;

		$pte_vals['{$PAGENAV}'] = $this->buildDynamicPageNav(
			$page,
			$totalThreads,
			$threadsPerPage,
			$next,
			$prev,
			$adminMode
		);

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

	private function buildNav($page, $totalPages): string {
		$navData = '<table id="pager"><tbody><tr>';
	
		$prev = $page - 1;
		$next = $page + 1;
		$totalPageCount = $totalPages;
	
		// Prev button
		if ($prev >= 0) {
			if ($prev <= $this->config['STATIC_HTML_UNTIL']) {
				// Use .html for pages <= STATIC_HTML_UNTIL
				$prevFile = ($prev === 0) ? 'index.html' : $prev . '.html';
			} else {
				// Use query parameter for pages > STATIC_HTML_UNTIL
				$prevFile = $this->config['PHP_SELF'] . 'pagenum=' . $prev;
			}
			$navData .= '<td><a href="' . $prevFile . '">&laquo; Prev</a></td>';
		} else {
			$navData .= '<td><span class="disabled">&laquo; Prev</span></td>';
		}
	
		// Page numbers
		$navData .= '<td>';
		for ($i = 0; $i < $totalPageCount; $i++) {
			if ($i <= $this->config['STATIC_HTML_UNTIL']) {
				// Use .html for pages <= STATIC_HTML_UNTIL
				$file = ($i === 0) ? 'index.html' : $i . '.html';
			} else {
				// Use query parameter for pages > STATIC_HTML_UNTIL
				$file = $this->config['PHP_SELF'] . '?pagenum=' . $i;
			}
	
			if ($i === $page) {
				// Show current page as bold
				$navData .= '[<b>' . $i . '</b>] ';  // Change to $i directly, so it starts at 0
			} else {
				$navData .= '[<a href="' . $file . '">' . $i . '</a>] ';  // Use $i for the page number
			}
		}
		$navData .= '</td>';
	
		// Next button
		if ($next < $totalPageCount) {
			if ($next <= $this->config['STATIC_HTML_UNTIL']) {
				// Use .html for pages <= STATIC_HTML_UNTIL
				$nextFile = $next . '.html';
			} else {
				// Use query parameter for pages > STATIC_HTML_UNTIL
				$nextFile = $this->config['PHP_SELF'] . 'pagenum=' . $next;
			}
			$navData .= '<td><a href="' . $nextFile . '">Next &raquo;</a></td>';
		} else {
			$navData .= '<td><span class="disabled">Next &raquo;</span></td>';
		}
	
		$navData .= '</tr></tbody></table>';
		return $navData;
	}
	
	
	private function buildDynamicPageNav(int $page, int $threadsCount, int $threadsPerPage, int $next, int $prev, bool $adminMode): string {
		$totalPages = ceil($threadsCount / $threadsPerPage);
		$nav = '<table id="pager"><tbody><tr>';

		// Prev button
		if ($prev >= 0) {
			if (!$adminMode && $prev == 0) {
				$nav .= '<td><form action="' . $this->config['PHP_SELF2'] . '" method="get">';
			} else {
				if ($adminMode || ($this->config['STATIC_HTML_UNTIL'] != -1 && $prev > $this->config['STATIC_HTML_UNTIL'])) {
					$nav .= '<td><form action="' . $this->config['PHP_SELF'] . '?pagenum=' . $prev . '" method="post">';
				} else {
					$nav .= '<td><form action="' . $prev . $this->config['PHP_EXT'] . '" method="get">';
				}
			}
			$nav .= '<div><input type="submit" value="' . _T('prev_page') . '"></div></form></td>';
		} else {
			$nav .= '<td>' . _T('first_page') . '</td>';
		}

		// Page numbers
		$nav .= '<td>';
		for ($i = 0; $i < $totalPages; $i++) {
			$pageNext = ($i == $next) ? ' rel="next"' : '';
			if ($page == $i) {
				$nav .= '[<b>' . $i . '</b>] ';
			} else {
				if (!$adminMode && $i == 0) {
					$nav .= '[<a href="' . $this->config['PHP_SELF2'] . '?">0</a>] ';
				} elseif ($adminMode || ($this->config['STATIC_HTML_UNTIL'] != -1 && $i > $this->config['STATIC_HTML_UNTIL'])) {
					$nav .= '[<a href="' . $this->config['PHP_SELF'] . '?pagenum=' . $i . '"' . $pageNext . '>' . $i . '</a>] ';
				} else {
					$nav .= '[<a href="' . $i . $this->config['PHP_EXT'] . '?"' . $pageNext . '>' . $i . '</a>] ';
				}
			}
		}
		$nav .= '</td>';

		// Next button
		if ($threadsCount > $next * $threadsPerPage) {
			if ($adminMode || ($this->config['STATIC_HTML_UNTIL'] != -1 && $next > $this->config['STATIC_HTML_UNTIL'])) {
				$nav .= '<td><form action="' . $this->config['PHP_SELF'] . '?pagenum=' . $next . '" method="post">';
			} else {
				$nav .= '<td><form action="' . $next . $this->config['PHP_EXT'] . '" method="get">';
			}
			$nav .= '<div><input type="submit" value="' . _T('next_page') . '"></div></form></td>';
		} else {
			$nav .= '<td>' . _T('last_page') . '</td>';
		}

		$nav .= '</tr></tbody></table>';
		return $nav;
	}

	public function rebuildBoardHtml(bool $logRebuild = false): void {
		// we are not using passes in shit. we are just gonna rebuild all board, one way,
		// if you want a special way of building a board. make it its own function.

		$threadRenderer = new threadRenderer($this->board, $this->config, $this->globalHTML, $this->moduleEngine, $this->templateEngine);
		$roleLevel = $this->staffSession->getRoleLevel();
		$adminMode = $roleLevel >= $this->config['roles']['LEV_JANITOR'];

		$previewCount = $this->config['RE_DEF'];
		$threads = $this->PIO->getThreadPreviewsFromBoard($this->board, $previewCount);


		$totalThreads = count($threads);
		$threadsPerPage = $this->config['PAGE_DEF'];
		$totalPagesFromThreadCount = ceil($totalThreads / $this->config['PAGE_DEF']); 
		$totalPagesToRebuild = 0;

		if($this->config['STATIC_HTML_UNTIL'] === -1) {
			$totalPagesToRebuild = $totalPagesFromThreadCount;
		} else if ($this->config['STATIC_HTML_UNTIL'] === 0) {
			$totalPagesToRebuild = 0;
		} else {
			$totalPagesToRebuild = min($this->config['STATIC_HTML_UNTIL'], $totalPagesFromThreadCount);
		}

		//hell idk what this is but keeping it for now...
		$pte_vals = array(
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
		);

		$this->moduleEngine->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], 0)); // "ThreadFront" Hook Point
		$this->moduleEngine->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], 0)); // "ThreadRear" Hook Point


		// Generate static pages one page at a time
		for ($page = 0; $page < $totalPagesToRebuild; $page++) {

			//load in next N threads for X page
			$threadsInPage = array_slice($threads, $page * $threadsPerPage, $threadsPerPage);

			$pageData = '';

			$this->globalHTML->head($pageData);
			
			// form
			$form_dat = '';
			$this->globalHTML->form($form_dat, 0);

			$form_dat .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);


			$pte_vals['{$FORMDAT}'] = $form_dat;

			$pte_vals['{$THREADS}'] = '';
			foreach ($threadsInPage as $i => $data) {
				$thread = $data['thread'];
				$posts = $data['posts'];
				$hiddenReply = $data['hidden_reply_count'];
				$threadUID = $data['thread_uid'];

				$pte_vals['{$THREADS}'] .= $threadRenderer->render(false,
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


			$pte_vals['{$PAGENAV}'] = $this->buildNav($page, $totalPagesFromThreadCount);

			$pageData .= $this->templateEngine->ParseBlock('MAIN', $pte_vals);
			$this->globalHTML->foot($pageData);
			// Remove any preset form values (DO NOT CACHE PRIVATE DETAILS!!!)
			$pageData = preg_replace('/id="com" class="inputtext">(.*)<\/textarea>/', 'id="com" class="inputtext"></textarea>', $pageData);
			$pageData = preg_replace('/name="email" id="email" value="(.*)" class="inputtext">/', 'name="email" id="email" value="" class="inputtext">', $pageData);
			$pageData = preg_replace('/replyhl/', '', $pageData);
			// Minify
			if ($this->config['MINIFY_HTML']) {
				$pageData = html_minify($pageData);
			}

			//save to file
			$logfilename = ($page === 0) ? 'index.html' : $page . '.html';
			$prefix = $this->board->getBoardCachedPath();
			$logFilePath = $prefix . $logfilename;

			$fp = fopen($logFilePath, 'w');
			if ($fp === false) {
				throw new \RuntimeException("Failed to open file for writing: $logFilePath");
			}

			stream_set_write_buffer($fp, 0);
			fwrite($fp, $pageData);
			fclose($fp);
			chmod($logFilePath, 0666);
		}

		if ($logRebuild) {
			$this->actionLogger->logAction("Rebuilt board: " . $this->board->getBoardTitle() . ' (' . $this->board->getBoardUID() . ')', $this->board->getBoardUID());
		}
	}

}