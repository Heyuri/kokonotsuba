<?php
class overboard {
	private bool $adminMode, $canViewDeleted;
	
	public function __construct(
		private board $board,
		private readonly array $config, 
		private readonly softErrorHandler $softErrorHandler,
		private readonly threadRepository $threadRepository,
		private readonly boardService $boardService,
		private readonly postRepository $postRepository,
		private readonly postService $postService,
		private readonly quoteLinkService $quoteLinkService,
		private readonly threadService $threadService,
		private readonly postSearchService $postSearchService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly postRedirectService $postRedirectService,
		private readonly deletedPostsService $deletedPostsService,
		private readonly fileService $fileService,
		private capcodeService $capcodeService,
		private array $userCapcodes,
		private transactionManager $transactionManager,
		private moduleEngine $moduleEngine, 
		private templateEngine $templateEngine
	) {
		// whether staff is logged in or not
		$this->adminMode = isActiveStaffSession();
		
		// can view deleted posts
		$this->canViewDeleted = getRoleLevelFromSession()->isAtLeast($this->board->getConfigValue('AuthLevels.CAN_DELETE_ALL'));
	}
	
	public function drawOverboardHead(&$dat, $resno = 0) {
		$html = '';
		
		$pte_vals = array('{$RESTO}'=>$resno?$resno:'', '{$IS_THREAD}'=>boolval($resno), '{$IS_STAFF}' => $this->adminMode);

		$pte_vals['{$PAGE_TITLE}'] = strip_tags($this->config['OVERBOARD_TITLE']);

		$pte_vals['{$MODULE_HEADER_HTML}'] = '';

		// dispatch module header hook point for (staff) live frontend
		if($this->adminMode) {
			$this->moduleEngine->dispatch('ModuleAdminHeader', array(&$pte_vals['{$MODULE_HEADER_HTML}']));
		}
		// dispatch module header hook point for static html
		$this->moduleEngine->dispatch('ModuleHeader', array(&$pte_vals['{$MODULE_HEADER_HTML}']));

		$html .= $this->templateEngine->ParseBlock('HEADER',$pte_vals);
		$this->moduleEngine->dispatch('Head', array(&$html, $resno)); // "Head" Hook Point
		$html .= '</head>';
		$pte_vals += array('{$HOME}' => '[<a href="'.$this->config['HOME'].'" target="_top">'._T('head_home').'</a>]',
			'{$STATUS}' => '[<a href="'.$this->config['LIVE_INDEX_FILE'].'?mode=status">'._T('head_info').'</a>]',
			'{$ADMIN}' => '[<a href="'.$this->config['LIVE_INDEX_FILE'].'?mode=admin">'._T('head_admin').'</a>]',
			'{$REFRESH}' => '[<a href="'.$this->config['STATIC_INDEX_FILE'].'?">'._T('head_refresh').'</a>]',
			'{$HOOKLINKS}' => '', '{$TITLE}' => $this->config['OVERBOARD_TITLE'], '{$TITLESUB}' => $this->config['OVERBOARD_SUBTITLE'],
			 '{$LIVE_INDEX_FILE}' => $this->config['LIVE_INDEX_FILE'], '{$BANNER}' => '',
			);
		
		$this->moduleEngine->dispatch('PlaceHolderIntercept', [&$pte_vals]);
		$this->moduleEngine->dispatch('TopLinks', array(&$pte_vals['{$HOOKLINKS}'],$resno)); // "Toplink" Hook Point
		$this->moduleEngine->dispatch('PageTop', array(&$pte_vals['{$BANNER}'])); //"AboveTitle" Hook Point
		
		$html .= $this->templateEngine->ParseBlock('BODYHEAD',$pte_vals);
		
		$pte_vals['{$MODULE_INFO_HOOK}'] = $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);
		
		$html .= $this->templateEngine->ParseBlock('POST_AREA', $pte_vals);

		$html .= $this->config['OVERBOARD_SUB_HEADER_HTML'];

		$dat .= $html;
		return $html;
	}

	public function drawOverboardThreads(array $filters) {
		$page = $_REQUEST['page'] ?? 0;
		if (!filter_var($page, FILTER_VALIDATE_INT) && $page != 0) $this->softErrorHandler->errorAndExit("Page number was not a valid int.");
		$page = ($page >= 0) ? $page : 1;
		
		$threadsHTML = '';
		$limit = $this->config['OVERBOARD_THREADS_PER_PAGE'];
		$offset = $page * $limit;
		
		$templateValues = $this->buildOverboardTemplateValues();
		
		// If no boards are selected, return prematurely
		if (!$filters['board']) {
			return '<div class="bbls"> <b class="error"> - No threads - </b> </div>';
		}

		$previewCount = $this->config['RE_DEF'];

		$threads = $this->threadService->getFilteredThreads($previewCount, $limit, $offset, $filters, $this->canViewDeleted);
		
		$numberThreadsFiltered = $this->threadRepository->getFilteredThreadCount($filters, $this->canViewDeleted);
		
		$postUidsInPage =  getPostUidsFromThreadArrays($threads);

		$quoteLinksFromPage = $this->quoteLinkService->getQuoteLinksByPostUids($postUidsInPage, $this->canViewDeleted);
		
		$boardMap = $this->loadBoardsForThreads($threads);
		$postsByBoardAndThread = $this->loadPostsForThreads($threads);

		foreach ($threads as $iterator => $thread) {
			$threadHTML = $this->renderOverboardThread(
				$thread,
				$iterator,
				$boardMap,
				$quoteLinksFromPage,
				$postsByBoardAndThread,
				$threads
			);
		
			if (!empty($threadHTML)) {
				$templateValues['{$THREADS}'] .= $threadHTML;
			}
		}
		
		$templateValues['{$PAGENAV}'] = drawPager($limit, $numberThreadsFiltered, $this->board->getBoardURL(true) . '?mode=overboard');
		$threadsHTML .= $this->templateEngine->ParseBlock('MAIN', $templateValues);
		return $threadsHTML;
	}

	private function buildOverboardTemplateValues() {
		return array(
			'{$THREADFRONT}' => '',
			'{$THREADREAR}' => '',
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
			'{$DEL_PASS_TEXT}' => _T('del_pass'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$FORMDAT}' => '',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
			'{$THREADS}' => '',
			'{$TITLE}' => 'Overboard',
			'{$TITLESUB}' => 'Posts from all kokonotsuba boards',
			'{$BOARD_URL}' => '',
			'{$IS_THREAD}' => false,
			'{$LIVE_INDEX_FILE}' => $this->config['LIVE_INDEX_FILE']
		);
	}

	private function loadBoardsForThreads(array $threads): array {
		// Extract thread.boardUID safely
		$boardUIDs = array_map(fn($t) => $t['thread']['boardUID'] ?? null, $threads);

		// Remove nulls and duplicates
		$boardUIDs = array_unique(array_filter($boardUIDs));

		// Fetch boards
		$boards = $this->boardService->getBoardsFromUIDs($boardUIDs);
	
		// Map boards by UID
		$boardMap = [];
		foreach ($boards as $board) {
			$boardMap[$board->getBoardUID()] = $board;
		}
	
		return $boardMap;
	}
	
	private function loadPostsForThreads($threads) {
		$tIDsByBoard = array();
		
		foreach ($threads as $thread) {
			$tIDsByBoard[$thread['thread']['boardUID']][] = $thread['thread_uid'];
		}
		
		$allPosts = $this->postRepository->fetchPostsFromBoardsAndThreads($tIDsByBoard);
		
		$postsByBoardAndThread = array();
		foreach ($allPosts as $post) {
			$boardUID = $post['boardUID'];
			$threadID = ($post['thread_uid'] == 0) ? $post['no'] : $post['thread_uid'];
			$postsByBoardAndThread[$boardUID][$threadID][] = $post;
		}
		return $postsByBoardAndThread;
	}

	private function renderOverboardThread(
		array $thread, 
		int $iterator, 
		array $boardMap, 
		array $quoteLinksFromPage,
		array $postsByBoardAndThread, 
		array $threads
	): string {
		$boardUID = $thread['thread']['boardUID'];
		$threadID = $thread['thread_uid'];
	
		if (!isset($boardMap[$boardUID]) || !isset($postsByBoardAndThread[$boardUID][$threadID])) {
			return '';
		}
	
		$board = $boardMap[$boardUID];
		$config = $board->loadBoardConfig();
		$posts = $thread['posts'];
		$threadToRender = $thread['thread'];
	
		$threadRenderer = $this->createThreadRenderer($board, $config, $this->templateEngine, $quoteLinksFromPage);
	
		[$overboardThreadTitle, $crossLink] = $this->buildThreadTitleAndLink($board);
	
		$adminMode = isActiveStaffSession();
		$templateValues = $this->buildTemplateValues($board);
	
		$killSensor = false;
	
		$hiddenReply = $thread['hidden_reply_count'];
	
		return $threadRenderer->render($threads,
			false,
			$threadToRender,
			$posts,
			$hiddenReply,
			$killSensor,
			$adminMode,
			$iterator,
			$overboardThreadTitle,
			$crossLink,
			$templateValues
		);
	}
	
	private function createThreadRenderer(board $board, array $config, templateEngine $templateEngine, array $quoteLinksFromPage): threadRenderer {
		$moduleEngineContext = new moduleEngineContext($config, 
			$board->getConfigValue('LIVE_INDEX_FILE'), 
			$board->getConfigValue('ModuleList'), 
			$this->postRepository, 
			$this->postService, 
			$this->threadRepository, 
			$this->threadService, 
			$this->postSearchService,
			$this->quoteLinkService,
			$this->boardService,
			$this->actionLoggerService,
			$this->postRedirectService,
			$this->deletedPostsService,
			$this->fileService,
			$this->capcodeService,
			$this->userCapcodes,
			$this->transactionManager,
			$templateEngine, 
			$board);

		$moduleEngine = new moduleEngine($moduleEngineContext);
		
		$postRenderer = new postRenderer($board,
		 $config, 
		 $moduleEngine, 
		 $templateEngine, 
		 $quoteLinksFromPage
		);

		return new threadRenderer($config, $templateEngine, $postRenderer, $moduleEngine);
	}
	
	private function buildThreadTitleAndLink(board $board): array {
		$boardTitle = $board->getBoardTitle();
		$boardURL = $board->getBoardURL();
		$titleHTML = '<span class="overboardThreadBoardTitle"><a href="'.$boardURL.'">'.$boardTitle.'</a></span>';
		return [$titleHTML, $boardURL];
	}
	
	
	private function buildTemplateValues(board $board): array {
		return [
			'{$BOARD_UID}' => $board->getBoardUID(),
		];
	}
	
	
}
