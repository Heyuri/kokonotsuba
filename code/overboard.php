<?php
class overboard {
	private $config, $moduleEngine, $templateEngine;
	
	public function __construct(array $config, moduleEngine $moduleEngine, templateEngine $templateEngine) {
		$this->config = $config;

		$this->moduleEngine = $moduleEngine;
		$this->templateEngine = $templateEngine;

		$this->templateEngine->setFunctionCallbacks([
			[
				'callback' => function (&$ary_val) {
					$this->moduleEngine->useModuleMethods('BlotterPreview', [ &$ary_val['{$BLOTTER}'] ]);
				},
			],
			[
				'callback' => function (&$ary_val) {
					$this->moduleEngine->useModuleMethods('GlobalMessage', [ &$ary_val['{$GLOBAL_MESSAGE}'] ]);
				},
			],
		]);

	}
	
	public function drawOverboardHead(&$dat, $resno = 0) {
		$html = '';
		
		$pte_vals = array('{$RESTO}'=>$resno?$resno:'', '{$IS_THREAD}'=>boolval($resno), '{$IS_STAFF}' => isActiveStaffSession());

		$pte_vals['{$PAGE_TITLE}'] = strip_tags($this->config['OVERBOARD_TITLE']);

		$html .= $this->templateEngine->ParseBlock('HEADER',$pte_vals);
		$this->moduleEngine->useModuleMethods('Head', array(&$html, $resno)); // "Head" Hook Point
		$html .= '</head>';
		$pte_vals += array('{$HOME}' => '[<a href="'.$this->config['HOME'].'" target="_top">'._T('head_home').'</a>]',
			'{$STATUS}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=status">'._T('head_info').'</a>]',
			'{$ADMIN}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=admin">'._T('head_admin').'</a>]',
			'{$REFRESH}' => '[<a href="'.$this->config['PHP_SELF2'].'?">'._T('head_refresh').'</a>]',
			'{$HOOKLINKS}' => '', '{$TITLE}' => $this->config['OVERBOARD_TITLE'], '{$TITLESUB}' => $this->config['OVERBOARD_SUBTITLE'],
			 '{$SELF}' => $this->config['PHP_SELF']
			);
			
		$this->moduleEngine->useModuleMethods('Toplink', array(&$pte_vals['{$HOOKLINKS}'],$resno)); // "Toplink" Hook Point
		$this->moduleEngine->useModuleMethods('AboveTitle', array(&$pte_vals['{$BANNER}'])); //"AboveTitle" Hook Point
		$html .= $this->templateEngine->ParseBlock('BODYHEAD',$pte_vals);
		$html .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK',$pte_vals);
		$html .= $this->config['OVERBOARD_SUB_HEADER_HTML'];

		$dat .= $html;
		return $html;
	}

	public function drawOverboardThreads(array $filters, globalHTML $globalHTML) {
		$threadSingleton = threadSingleton::getInstance();

		$page = $_REQUEST['page'] ?? 0;
		if (!filter_var($page, FILTER_VALIDATE_INT) && $page != 0) $globalHTML->error("Page number was not a valid int.");
		$page = ($page >= 0) ? $page : 1;
		
		$threadsHTML = '';
		$limit = $this->config['OVERBOARD_THREADS_PER_PAGE'];
		$offset = $page * $limit;
		
		$templateValues = $this->buildOverboardTemplateValues();
		
		
		$previewCount = $this->config['RE_DEF'];

		$threads = $threadSingleton->getFilteredThreads($previewCount, $limit, $offset, $filters);
		
		
		$numberThreadsFiltered = $threadSingleton->getFilteredThreadCount($filters);
		
		if (!$threads) return '<div class="bbls"> <b class="error"> - No threads - </b> </div>';
		
		$boardMap = $this->loadBoardsForThreads($threads);
		$quoteLinksByBoardUID = $this->loadQuoteLinksForThreads($boardMap);
		$postsByBoardAndThread = $this->loadPostsForThreads($threads);

		foreach ($threads as $iterator => $thread) {
			$threadHTML = $this->renderOverboardThread(
				$thread,
				$iterator,
				$boardMap,
				$quoteLinksByBoardUID,
				$postsByBoardAndThread,
				$threads
			);
		
			if (!empty($threadHTML)) {
				$templateValues['{$THREADS}'] .= $threadHTML;
			}
		}
		
		$templateValues['{$PAGENAV}'] = $globalHTML->drawPager($limit, $numberThreadsFiltered, $globalHTML->fullURL().$this->config['PHP_SELF'].'?mode=overboard');
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
			'{$DEL_PASS_FIELD}' => '<input type="hidden" name="func" value="delete"> <input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
			'{$THREADS}' => '',
			'{$TITLE}' => 'Overboard',
			'{$TITLESUB}' => 'Posts from all kokonotsuba boards',
			'{$BOARD_URL}' => '',
			'{$IS_THREAD}' => false,
			'{$SELF}' => $this->config['PHP_SELF']
		);
	}

	private function loadBoardsForThreads(array $threads): array {
		$boardIO = boardIO::getInstance();
	
		// Extract thread.boardUID safely
		$boardUIDs = array_map(fn($t) => $t['thread']['boardUID'] ?? null, $threads);

		// Remove nulls and duplicates
		$boardUIDs = array_unique(array_filter($boardUIDs));

		// Fetch boards
		$boards = $boardIO->getBoardsFromUIDs($boardUIDs);
	
		// Map boards by UID
		$boardMap = [];
		foreach ($boards as $board) {
			$boardMap[$board->getBoardUID()] = $board;
		}
	
		return $boardMap;
	}

	private function loadQuoteLinksForThreads(array $boardMap): array {
		$quoteLinksByBoardUID = [];
		foreach ($boardMap as $boardUID => $board) {
			$quoteLinksByBoardUID[$boardUID] = getQuoteLinksFromBoard($board);
		}

		return $quoteLinksByBoardUID;
	}
	
	private function loadPostsForThreads($threads) {
		$PIO = PIOPDO::getInstance();
		$tIDsByBoard = array();
		
		foreach ($threads as $thread) {
			$tIDsByBoard[$thread['thread']['boardUID']][] = $thread['thread_uid'];
		}
		
		$allPosts = $PIO->fetchPostsFromBoardsAndThreads($tIDsByBoard);
		
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
		array $quoteLinksByBoardUID,
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
	
		$threadRenderer = $this->createThreadRenderer($board, $config, $this->templateEngine, $quoteLinksByBoardUID);
	
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
	
	private function createThreadRenderer(board $board, array $config, templateEngine $templateEngine, array $quoteLinksByBoardUID): threadRenderer {
		$globalHTML = new globalHTML($board);
		$moduleEngine = new moduleEngine($board);
		
		$boardUID = $board->getBoardUID();
		$quoteLinksForBoard = $quoteLinksByBoardUID[$boardUID];

		$postRenderer = new postRenderer($board,
		 $config, 
		 $globalHTML, 
		 $moduleEngine, 
		 $templateEngine, 
		 $quoteLinksForBoard
		);

		return new threadRenderer($config, $globalHTML, $templateEngine, $postRenderer);
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
