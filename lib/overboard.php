<?php
class overboard {
	private $config;
	
	public function __construct($config) {
		$this->config = $config;
	}
	
	public function drawOverboardHead(&$dat, $resno = 0) {
		$PTE = PTELibrary::getInstance();
		$PMS = PMS::getInstance();
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
		$html .= $PTE->ParseBlock('HEADER',$pte_vals);
		$PMS->useModuleMethods('Head', array(&$html, $resno)); // "Head" Hook Point
		$html .= '</head>';
		$pte_vals += array('{$HOME}' => '[<a href="'.$this->config['HOME'].'" target="_top">'._T('head_home').'</a>]',
			'{$STATUS}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=status">'._T('head_info').'</a>]',
			'{$ADMIN}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=admin">'._T('head_admin').'</a>]',
			'{$REFRESH}' => '[<a href="'.$this->config['PHP_SELF2'].'?">'._T('head_refresh').'</a>]',
			'{$HOOKLINKS}' => '', '{$TITLE}' => htmlspecialchars($this->config['OVERBOARD_TITLE']), '{$TITLESUB}' => htmlspecialchars($this->config['OVERBOARD_SUBTITLE'])
			);
			
		$PMS->useModuleMethods('Toplink', array(&$pte_vals['{$HOOKLINKS}'],$resno)); // "Toplink" Hook Point
		$PMS->useModuleMethods('AboveTitle', array(&$pte_vals['{$BANNER}'])); //"AboveTitle" Hook Point
		$html .= $PTE->ParseBlock('BODYHEAD',$pte_vals);
		
		$PMS->useModuleMethods('GlobalMessage', array(&$pte_vals['{$GLOBAL_MESSAGE}'])); // "GlobalMessage" Hook Point
		$PMS->useModuleMethods('BlotterPreview', array(&$pte_vals['{$BLOTTER}'])); // "Blotter Preview" Hook Point
		$html .= $PTE->ParseBlock('MODULE_INFO_HOOK',$pte_vals);
		$html .= $this->config['OVERBOARD_SUB_HEADER_HTML'];

		$dat .= $html;
		return $html;
	}

	public function drawOverboardThreads($filters, $globalHTML) {
		$PIO = PIOPDO::getInstance();
		$PTE = PTELibrary::getInstance();
		$boardIO = boardIO::getInstance();
		
		$pagenum = 0;
		
		$threadsHTML = '';
		
		$templateValues = array('{$THREADFRONT}'=>'','{$THREADREAR}'=>'',
				'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
				'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
				'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
				'{$FORMDAT}' => '',
				'{$DEL_PASS_FIELD}' => '<input type="password" name="pwd" size="8" value="">',
				'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
				'{$THREADS}' => '',
				'{$TITLE}' => 'Overboard',
				'{$TITLESUB}' => 'Posts from all kokonotsuba boards',
				'{$BOARD_URL}' => '',
				'{$IS_THREAD}' => false);
				
		$single_page = false;
		
		$limit = $this->config['OVERBOARD_THREADS_PER_PAGE'];
		$pagenum = $_REQUEST['page'] ?? 0;
		$pagenum = ($pagenum >= 0) ? $pagenum : 1;
		$offset = $pagenum * $limit;

		$threads = $PIO->getFilteredThreads($limit, $offset, $filters);
		$threadList = $PIO->getFilteredThreadUIDs($limit, $offset, $filters);
		$numberThreadsFiltered = $PIO->getFilteredThreadCount($filters);
		
		if(!$threads) return '<div class="bbls"> <b class="error"> - No threads - </b> </div>';
		
		// Output the thread content
		foreach($threads as $iterator => $thread) {
			$board = $boardIO->getBoardByUID($thread['boardUID']);
			$config = $board->loadBoardConfig();
		
			$boardTitle = $board->getBoardTitle();
			$boardURL = $board->getBoardURL();
			

			$staffSession = new staffAccountFromSession;
			$roleLevel = $staffSession->getRoleLevel();
			
			$thread_uid = $thread['thread_uid'];
			$page_start = $page_end = 0; // Static page number
			$inner_for_count = 1; // The number of inner loop executions
			$RES_start = $RES_amount = $hiddenReply = $tree_count = 0;
			$kill_sensor = $old_sensor = false; // Predictive system start flag
			$arr_kill = $arr_old = array(); // Obsolete numbered array
			
			$adminMode = $roleLevel >=$config['roles']['LEV_JANITOR'] && $pagenum != -1 && !$single_page; // Front-end management mode
			$templateValues['{$DEL_PASS_TEXT}'] = ($adminMode ? '<input type="hidden" name="func" value="delete">' : '')._T('del_pass');
			$templateValues['{$SELF}'] = $config['PHP_SELF'];
			$templateValues['{$BOARD_UID}'] = $board->getBoardUID();
			
			
			$posts = array();
			$tree = array();
			$tree_cut = array();
			$tree_count = 0;
			
		
			if ($pagenum == -1 && ($page * $config['PAGE_DEF'] + $iterator) >= $threads_count) {
				break;
			}

			$tID = ($page_start == $page_end)
				? $threadList[$iterator]
				: $threadList[$page * $config['PAGE_DEF'] + $iterator];
	
			$tree_count = $PIO->postCountFromBoard($board, $tID) - 1;
		
			$RES_start = $tree_count - $config['RE_DEF'];
			if ($RES_start < 1) $RES_start = 1;
			
			$RES_amount = $config['RE_DEF'];
			$hiddenReply = $RES_start - 1;
		
			$tree = $PIO->fetchPostListFromBoard($board, $tID);
			$tree_cut = array_slice($tree, $RES_start, $RES_amount);
			array_unshift($tree_cut, $tree[0]);
			$posts = $PIO->fetchPosts($tree_cut);
			
			$overboardThreadTitle = '<span class="overboard-thread-title"><a href="'.$boardURL.'">'.$boardTitle.'</a></span>';
			$crossLink = $boardURL;

			$templateValues['{$THREADS}'] .= $globalHTML->arrangeThread(
				$board,
				$config,
				$PTE,
				$globalHTML,
				$PIO,
				$threadList,
				$tree,
				$tree_cut,
				$posts,
				$hiddenReply,
				0,
				$arr_kill,
				$arr_old,
				$kill_sensor,
				$old_sensor,
				true,
				$adminMode,
				0,
				$iterator,
				$overboardThreadTitle,
				$crossLink
				);
		}
		$templateValues['{$PAGENAV}'] = $globalHTML->drawPager($limit, $numberThreadsFiltered, $globalHTML->fullURL().$this->config['PHP_SELF'].'?mode=overboard');
		$threadsHTML .= $PTE->ParseBlock('MAIN', $templateValues);
		return $threadsHTML;
	}
	
}
