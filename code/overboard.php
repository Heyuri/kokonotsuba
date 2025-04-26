<?php
class overboard {
	private $config, $moduleEngine, $templateEngine, $threadRenderer;
	
	public function __construct(array $config, moduleEngine $moduleEngine, templateEngine $templateEngine, threadRenderer $threadRenderer) {
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

		$this->threadRenderer = $threadRenderer;

	}
	
	public function drawOverboardHead(&$dat, $resno = 0) {
		$PIO = PIOPDO::getInstance();
		$html = '';
		
		$pte_vals = array('{$RESTO}'=>$resno?$resno:'', '{$IS_THREAD}'=>boolval($resno));
		if ($resno) {
			$post = $PIO->fetchPostsFromThread($resno);
			if (mb_strlen($post[0]['com']) <= 10){
				$CommentTitle = $post[0]['com'];
			} else {
				$CommentTitle = mb_substr($post[0]['com'],0,10,'UTF-8') . "...";
			}
			$pte_vals['{$PAGE_TITLE}'] = ($post[0]['sub'] ? $post[0]['sub'] : strip_tags($CommentTitle)).' - '.$this->config['TITLE'];
		}
		$html .= $this->templateEngine->ParseBlock('HEADER',$pte_vals);
		$this->moduleEngine->useModuleMethods('Head', array(&$html, $resno)); // "Head" Hook Point
		$html .= '</head>';
		$pte_vals += array('{$HOME}' => '[<a href="'.$this->config['HOME'].'" target="_top">'._T('head_home').'</a>]',
			'{$STATUS}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=status">'._T('head_info').'</a>]',
			'{$ADMIN}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=admin">'._T('head_admin').'</a>]',
			'{$REFRESH}' => '[<a href="'.$this->config['PHP_SELF2'].'?">'._T('head_refresh').'</a>]',
			'{$HOOKLINKS}' => '', '{$TITLE}' => htmlspecialchars($this->config['OVERBOARD_TITLE']), '{$TITLESUB}' => htmlspecialchars($this->config['OVERBOARD_SUBTITLE']),
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

		$pagenum = $_REQUEST['page'] ?? 0;
		if (!filter_var($pagenum, FILTER_VALIDATE_INT) && $pagenum != 0) $globalHTML->error("Page number was not a valid int.");
		$pagenum = ($pagenum >= 0) ? $pagenum : 1;
		
		$threadsHTML = '';
		$limit = $this->config['OVERBOARD_THREADS_PER_PAGE'];
		$offset = $pagenum * $limit;
		
		$templateValues = $this->buildOverboardTemplateValues();
		
		$single_page = false;
		
		$threads = $threadSingleton->getFilteredThreads($limit, $offset, $filters);
		$threadList = array_column($threads, 'thread_uid');
		$numberThreadsFiltered = $threadSingleton->getFilteredThreadCount($filters);
		
		if (!$threads) return '<div class="bbls"> <b class="error"> - No threads - </b> </div>';
		
		$boardMap = $this->loadBoardsForThreads($threads);
		$postsByBoardAndThread = $this->loadPostsForThreads($threads);

		foreach ($threads as $iterator => $thread) {
			$threadHTML = $this->renderOverboardThread(
				$thread,
				$iterator,
				$pagenum,
				$single_page,
				$boardMap,
				$postsByBoardAndThread,
				$threadList,
				$templateValues
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
			'{$DEL_PASS_TEXT}' => '',
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$FORMDAT}' => '',
			'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
			'{$THREADS}' => '',
			'{$TITLE}' => 'Overboard',
			'{$TITLESUB}' => 'Posts from all kokonotsuba boards',
			'{$BOARD_URL}' => '',
			'{$IS_THREAD}' => false,
			'{$SELF}' => ''
		);
	}

	private function loadBoardsForThreads($threads) {
		$boardIO = boardIO::getInstance();
		$boardUIDs = array_column($threads, 'boardUID');
		$boards = $boardIO->getBoardsFromUIDs($boardUIDs);
		
		$boardMap = array();
		foreach ($boards as $board) {
			$boardMap[$board->getBoardUID()] = $board;
		}
		return $boardMap;
	}

	private function loadPostsForThreads($threads) {
		$PIO = PIOPDO::getInstance();
		$tIDsByBoard = array();
		
		foreach ($threads as $thread) {
			$tIDsByBoard[$thread['boardUID']][] = $thread['thread_uid'];
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

	private function renderOverboardThread(array $thread, 
	 int $iterator, 
	 mixed $pagenum, 
	 bool $single_page, 
	 array $boardMap, 
	 array $postsByBoardAndThread, 
	 array $threadList, 
	 array $templateValues): string {
		$boardUID = $thread['boardUID'];
		$threadID = $thread['thread_uid'];
	
		if (!isset($boardMap[$boardUID]) || !isset($postsByBoardAndThread[$boardUID][$threadID])) {
			return '';
		}
	
		$board = $boardMap[$boardUID];
		$config = $board->loadBoardConfig();
		$posts = $postsByBoardAndThread[$boardUID][$threadID];
	
		// Re-key posts by post_uid
		$posts = array_column($posts, null, 'post_uid');

		// Setup basic variables
		$boardTitle = $board->getBoardTitle();
		$boardURL = $board->getBoardURL();
		$overboardThreadTitle = '<span class="overboardThreadBoardTitle"><a href="'.$boardURL.'">'.$boardTitle.'</a></span>';
		$crossLink = $boardURL;
	
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();
		$adminMode = $roleLevel >= $config['roles']['LEV_JANITOR'] && $pagenum != -1 && !$single_page;
	
		$templateValues['{$DEL_PASS_TEXT}'] = ($adminMode ? '<input type="hidden" name="func" value="delete">' : '')._T('del_pass');
		$templateValues['{$SELF}'] = $config['PHP_SELF'];
		$templateValues['{$BOARD_UID}'] = $board->getBoardUID();
	
		$kill_sensor = false;
		$arr_kill = array();
	
		// Build tree and visible posts
		$postUIDs = array_column($posts, 'post_uid');
		$treeCount = count($postUIDs) - 1;
	
		$RES_start = $treeCount - $config['RE_DEF'];
		if ($RES_start < 1) {
			$RES_start = 1;
		}
	
		$RES_amount = $config['RE_DEF'];
		$hiddenReply = $RES_start - 1;
	
		$visiblePostUids = array_slice($postUIDs, $RES_start, $RES_amount);
		array_unshift($visiblePostUids, $postUIDs[0]); // Always include OP
	
		// Build visible posts array
		$visiblePosts = array();
		foreach ($visiblePostUids as $post_uid) {
			if (isset($posts[$post_uid])) {
				$visiblePosts[$post_uid] = $posts[$post_uid];
			}
		}
		$visiblePosts = array_values($visiblePosts);

		return $this->threadRenderer->render(
			$threadList,
			$threadList,
			$visiblePostUids,
			$visiblePosts,
			$hiddenReply,
			0,
			$arr_kill,
			$kill_sensor,
			true,
			$adminMode,
			$iterator,
			$overboardThreadTitle,
			$crossLink
		);
	}
	
	
}
