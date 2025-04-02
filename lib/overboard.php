<?php
class overboard {
	private $config, $templateEngine;
	
	public function __construct(board $board) {
		$this->config = $board->loadBoardConfig();
		$this->templateEngine = $board->getBoardTemplateEngine();
	}
	
	public function drawOverboardHead(&$dat, $resno = 0) {
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
		$html .= $this->templateEngine->ParseBlock('HEADER',$pte_vals);
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
		$html .= $this->templateEngine->ParseBlock('BODYHEAD',$pte_vals);
		$html .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK',$pte_vals);
		$html .= $this->config['OVERBOARD_SUB_HEADER_HTML'];

		$dat .= $html;
		return $html;
	}

	public function drawOverboardThreads($filters, $globalHTML) {
		$PIO = PIOPDO::getInstance();
		$boardIO = boardIO::getInstance();
		
		$pagenum = 0;
		
		$threadsHTML = '';
		
		$templateValues = array('{$THREADFRONT}'=>'','{$THREADREAR}'=>'',
				'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
				'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
				'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
				'{$FORMDAT}' => '',
				'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
				'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
				'{$THREADS}' => '',
				'{$TITLE}' => 'Overboard',
				'{$TITLESUB}' => 'Posts from all kokonotsuba boards',
				'{$BOARD_URL}' => '',
				'{$IS_THREAD}' => false);
				
		$single_page = false;
		
		$limit = $this->config['OVERBOARD_THREADS_PER_PAGE'];
		

		$pagenum = $_REQUEST['page'] ?? 0;
		if (!filter_var($pagenum, FILTER_VALIDATE_INT) && $pagenum != 0) $globalHTML->error("Page number was not a valid int.");

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
			

			$page_start = $page_end = 0; // Static page number
			$RES_start = $RES_amount = $hiddenReply = $tree_count = 0;
			$kill_sensor = false; // Predictive system start flag
			$arr_kill = array(); // Obsolete numbered array
			
			$adminMode = $roleLevel >=$config['roles']['LEV_JANITOR'] && $pagenum != -1 && !$single_page; // Front-end management mode
			$templateValues['{$DEL_PASS_TEXT}'] = ($adminMode ? '<input type="hidden" name="func" value="delete">' : '')._T('del_pass');
			$templateValues['{$SELF}'] = $config['PHP_SELF'];
			$templateValues['{$BOARD_UID}'] = $board->getBoardUID();
			
			
			$posts = array();
			$tree = array();
			$tree_cut = array();
			$tree_count = 0;
			
		
			if ($pagenum == -1 && ($pagenum * $config['PAGE_DEF'] + $iterator) >= $numberThreadsFiltered) {
				break;
			}

			$tID = ($page_start == $page_end)
				? $threadList[$iterator]
				: $threadList[$pagenum * $config['PAGE_DEF'] + $iterator];
	
			$tree_count = $PIO->postCountFromBoard($board, $tID) - 1;
		
			$RES_start = $tree_count - $config['RE_DEF'];
			if ($RES_start < 1) $RES_start = 1;
			
			$RES_amount = $config['RE_DEF'];
			$hiddenReply = $RES_start - 1;
		
			$tree = $PIO->fetchPostListFromBoard($board, $tID);
			$tree_cut = array_slice($tree, $RES_start, $RES_amount);
			array_unshift($tree_cut, $tree[0]);
			$posts = $PIO->fetchPosts($tree_cut);
			
			$overboardThreadTitle = '<span class="overboardThreadBoardTitle"><a href="'.$boardURL.'">'.$boardTitle.'</a></span>';
			$crossLink = $boardURL;

			$templateValues['{$THREADS}'] .= $globalHTML->arrangeThread(
				$board,
				$config,
				$PIO,
				$threadList,
				$tree,
				$tree_cut,
				$posts,
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
		$templateValues['{$PAGENAV}'] = $globalHTML->drawPager($limit, $numberThreadsFiltered, $globalHTML->fullURL().$this->config['PHP_SELF'].'?mode=overboard');
		$threadsHTML .= $this->templateEngine->ParseBlock('MAIN', $templateValues);
		return $threadsHTML;
	}
	
}
