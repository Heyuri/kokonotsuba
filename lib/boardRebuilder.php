<?php

class boardRebuilder {
    private board $board;
    private array $config;
    private globalHTML $globalHTML;
    private mixed $FileIO;
    private mixed $postRedirectIO;
    private moduleEngine $moduleEngine;
    private mixed $PIO;
	private mixed $actionLogger;
    private staffAccountFromSession $staffSession;
	private templateEngine $templateEngine;


	public function __construct(board $board, templateEngine $templateEngine) {
		$this->board = $board;

        $this->globalHTML = new globalHTML($board);
		$this->moduleEngine = new moduleEngine($board);
		$this->staffSession = new staffAccountFromSession;
        $this->PIO = PIOPDO::getInstance();
		$this->actionLogger = actionLogger::getInstance();
		$this->FileIO = PMCLibrary::getFileIOInstance();
		$this->postRedirectIO = postRedirectIO::getInstance();



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


        $this->config = $board->loadBoardConfig();
		if(empty($this->config)) die("No board config for {$board->getBoardTitle()}:{$board->getBoardUID()}");

	}

    public function rebuildBoardHtml(int $resno = 0, mixed $pagenum = -1, bool $single_page = false, int $last = -1, bool $logRebuild = false): void {
		$roleLevel = $this->staffSession->getRoleLevel();
		$adminMode = $roleLevel >=$this->config['roles']['LEV_JANITOR'] && $pagenum != -1 && !$single_page; // Front-end management mode
		$thread_uid = $this->PIO->resolveThreadUidFromResno($this->board, $resno);
		
		
		$page_start = $page_end = 0; // Static page number
		$inner_for_count = 1; // The number of inner loop executions
		$RES_start = $RES_amount = $hiddenReply = $tree_count = 0;
		$kill_sensor = false; // Predictive system start flag
		$arr_kill = array(); // Obsolete numbered array
		$pte_vals = array('{$THREADFRONT}'=>'','{$THREADREAR}'=>'','{$SELF}'=>$this->config['PHP_SELF'],
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$DEL_PASS_TEXT}' => ($adminMode ? '<input type="hidden" name="func" value="delete">' : '')._T('del_pass'),
			'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
			'{$IS_THREAD}' => !!$resno);
		if($resno) $pte_vals['{$RESTO}'] = $resno;
		
		if(!$resno){
			if($pagenum==-1){ // Rebuild mode (PHP dynamic output of multiple pages)
				$threads = $this->PIO->getThreadListFromBoard($this->board); // Get a full list of discussion threads
				$this->moduleEngine->useModuleMethods('ThreadOrder', array($resno, $pagenum, $single_page, &$threads)); // "ThreadOrder" Hook Point
				$threads_count = count($threads);
				$inner_for_count = $threads_count > $this->config['PAGE_DEF'] ? $this->config['PAGE_DEF'] : $threads_count;
				$page_end = ($last == -1 ? ceil($threads_count / $this->config['PAGE_DEF']) : $last);
			}else{ // Discussion of the clue label pattern (PHP dynamic output one page)
				$threads_count = $this->PIO->threadCountFromBoard($this->board); // Discuss the number of strings
				if($pagenum < 0 || ($pagenum * $this->config['PAGE_DEF']) >= $threads_count) $this->globalHTML->error( _T('page_not_found')); // $Pagenum is out of range
				$page_start = $page_end = $pagenum; // Set a static page number
				$threads = $this->PIO->getThreadListFromBoard($this->board); // Get a full list of discussion threads
				$this->moduleEngine->useModuleMethods('ThreadOrder', array($resno, $pagenum,$single_page,&$threads)); // "ThreadOrder" Hook Point
				$threads = array_splice($threads, $pagenum * $this->config['PAGE_DEF'], $this->config['PAGE_DEF']); // Remove the list of discussion threads after the tag
				$inner_for_count = count($threads); // The number of discussion strings is the number of cycles
			}
		}else{
			//check for redirect
			$movedThreadRedirect = $this->postRedirectIO->resolveRedirectedThreadLinkFromPostOpNumber($this->board, $resno);
			if($movedThreadRedirect) redirect($movedThreadRedirect);

			if(!$this->PIO->isThread($thread_uid)){ // Try to find the thread by child post no. instead
				$post_uid = $this->PIO->resolvePostUidFromPostNumber($this->board, $resno);
				$thread_uid_new = $this->PIO->fetchPostsFromThread($post_uid)[0]['thread_uid'] ?? false;
				
				if (!$this->PIO->isThread($thread_uid_new)) $this->globalHTML->error("Thread not found!");
				
				$resnoNew = $this->PIO->resolveThreadNumberFromUID($thread_uid_new); 
				$redirectString = $this->config['PHP_SELF']."?res=".$resnoNew."#p".$this->board->getBoardUID()."_$resno";
				redirect($redirectString); // Found, redirect
			}
			$AllRes = isset($pagenum) && ($_GET['pagenum']??'')=='all'; // Whether to use ALL for output

			// Calculate the response label range
			$tree_count = $this->PIO->postCountFromBoard($this->board, $resno) - 1; // Number of discussion thread responses
			if($tree_count && $this->config['RE_PAGE_DEF']){ // There is a response and RE_PAGE_DEF > 0 to do the pagination action
				if($pagenum==='all'){ // show all
					$pagenum = 0;
					$RES_start = 1; $RES_amount = $tree_count;
				}else{
					if($pagenum==='RE_PAGE_MAX') $pagenum = ceil($tree_count / $this->config['RE_PAGE_DEF']) - 1; // Special value: Last page
					if($pagenum < 0) $pagenum = 0; // negative number
					if($pagenum * $this->config['RE_PAGE_DEF'] >= $tree_count) $this->globalHTML->error(_T('page_not_found'));
					$RES_start = $pagenum * $this->config['RE_PAGE_DEF'] + 1; // Begin
					$RES_amount = $this->config['RE_PAGE_DEF']; // Take several
				}
			}elseif($pagenum > 0) $this->globalHTML->error( _T('page_not_found')); // In the case of no response, only pagenum = 0 or negative numbers are allowed
			else{ $RES_start = 1; $RES_amount = $tree_count; $pagenum = 0; } // Output All Responses
		}

		// Predict that old articles will be deleted and archives
		$tmp_total_size = $this->FileIO->getCurrentStorageSize($this->board); // The current usage of additional image files
		$tmp_STORAGE_MAX = $this->config['STORAGE_MAX'] * (($tmp_total_size >= $this->config['STORAGE_MAX']) ? 1 : 0.95); // Estimated upper limit
		if($this->config['STORAGE_LIMIT'] && $this->config['STORAGE_MAX'] > 0 && ($tmp_total_size >= $tmp_STORAGE_MAX)){
			$kill_sensor = true; // tag opens
			$arr_kill = $this->PIO->delOldAttachments($tmp_total_size, $tmp_STORAGE_MAX); // Outdated attachment array
		}

		$this->moduleEngine->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], $resno)); // "ThreadFront" Hook Point
		$this->moduleEngine->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], $resno)); // "ThreadRear" Hook Point

		// Generate static pages one page at a time
		for($page = $page_start; $page <= $page_end; $page++){
			$dat = ''; $pte_vals['{$THREADS}'] = '';
			$this->globalHTML->head($dat, $thread_uid);
			// form
			$form_dat = '';
			$this->globalHTML->form($form_dat, $resno, '', '', '', '');

			$form_dat .= $this->templateEngine->ParseBlock('MODULE_INFO_HOOK',$pte_vals);
			$pte_vals['{$FORMDAT}'] = $form_dat;
			// Output the thread content
			for ($i = 0; $i < $inner_for_count; $i++) {
				$posts = array();
				$tree = array();
				$tree_cut = array();
				$tree_count = 0;
	
				if ($resno) {
					$posts = $this->PIO->fetchPostsFromThread($thread_uid);
					
					$tree = array_map(function ($post) {
						return $post['post_uid'];
					}, $posts);
					
					$tree_count = count($posts);
					$tree_cut = $tree_count;
	
				} else {
					if ($pagenum == -1 && ($page * $this->config['PAGE_DEF'] + $i) >= $threads_count) {
						break;
					}
	
					$tID = ($page_start == $page_end)
						? $threads[$i]
						: $threads[$page * $this->config['PAGE_DEF'] + $i];
	
					$tree_count = $this->PIO->postCountFromBoard($this->board, $tID) - 1;
		
					$RES_start = $tree_count - $this->config['RE_DEF'];
					if ($RES_start < 1) $RES_start = 1;
			
					$RES_amount = $this->config['RE_DEF'];
					$hiddenReply = $RES_start - 1;
		
					$tree = $this->PIO->fetchPostListFromBoard($this->board, $tID);
					$tree_cut = array_slice($tree, $RES_start, $RES_amount);
					array_unshift($tree_cut, $tree[0]);
					$posts = $this->PIO->fetchPosts($tree_cut);
				}
				$threads = $this->PIO->getThreadListFromBoard($this->board);
				$pte_vals['{$THREADS}'] .= $this->globalHTML->arrangeThread(
					$this->board,
					$this->config,
					$this->PIO,
					$threads,
					$tree,
					$tree_cut,
					$posts,
					$hiddenReply,
					$thread_uid,
					$arr_kill,
					$kill_sensor,
					true,
					$adminMode,
					$i
				);
			}

			$pte_vals['{$PAGENAV}'] = '';

			// Page change judgment
			$prev = ($resno ? $pagenum : $page) - 1;
			$next = ($resno ? $pagenum : $page) + 1;
			if($resno){ // Response labels
				if($this->config['RE_PAGE_DEF'] > 0){ // The Responses tab is on
					$pte_vals['{$PAGENAV}'] .= '<table id="pager"><tbody><tr><td>';
					$pte_vals['{$PAGENAV}'] .= ($prev >= 0) ? '<a rel="prev" href="'.$this->config['PHP_SELF'].'?res='.$resno.'&pagenum='.$prev.'">'._T('prev_page').'</a>' : _T('first_page');
					$pte_vals['{$PAGENAV}'] .= "</td><td>";
					if($tree_count==0) $pte_vals['{$PAGENAV}'] .= '[<b>0</b>] '; // No response
					else{
						for($i = 0, $len = $tree_count / $this->config['RE_PAGE_DEF']; $i <= $len; $i++){
							if(!$AllRes && $pagenum==$i) $pte_vals['{$PAGENAV}'] .= '[<b>'.$i.'</b>] ';
							else $pte_vals['{$PAGENAV}'] .= '[<a href="'.$this->config['PHP_SELF'].'?res='.$resno.'&pagenum='.$i.'">'.$i.'</a>] ';
						}
						$pte_vals['{$PAGENAV}'] .= $AllRes ? '[<b>'._T('all_pages').'</b>] ' : ($tree_count > $this->config['RE_PAGE_DEF'] ? '[<a href="'.$this->config['PHP_SELF'].'?res='.$resno.'">'._T('all_pages').'</a>] ' : '');
					}
					$pte_vals['{$PAGENAV}'] .= '</td><td>';
					$pte_vals['{$PAGENAV}'] .= (!$AllRes && $tree_count > $next * $this->config['RE_PAGE_DEF']) ? '<a href="'.$this->config['PHP_SELF'].'?res='.$resno.'&pagenum='.$next.'">'._T('next_page').'</a>' : _T('last_page');
					$pte_vals['{$PAGENAV}'] .= '</td></tr></tbody></table>';
				}
			}else{ // General labels
				$pte_vals['{$PAGENAV}'] .= '<table id="pager"><tbody><tr>';
				if($prev >= 0){
					if(!$adminMode && $prev==0) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$this->config['PHP_SELF2'].'" method="get">';
					else{
						if($adminMode || ($this->config['STATIC_HTML_UNTIL'] != -1) && ($prev > $this->config['STATIC_HTML_UNTIL'])) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$this->config['PHP_SELF'].'?pagenum='.$prev.'" method="post">';
						else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$prev.$this->config['PHP_EXT'].'" method="get">';
					}
					$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('prev_page').'"></div></form></td>';
				}else $pte_vals['{$PAGENAV}'] .= '<td>'._T('first_page').'</td>';
				$pte_vals['{$PAGENAV}'] .= '<td>';
				for($i = 0, $len = $threads_count / $this->config['PAGE_DEF']; $i <= $len; $i++){
					if($page==$i) $pte_vals['{$PAGENAV}'] .= "[<b>".$i."</b>] ";
					else{
						$pageNext = ($i==$next) ? ' rel="next"' : '';
						if(!$adminMode && $i==0) $pte_vals['{$PAGENAV}'] .= '[<a href="'.$this->config['PHP_SELF2'].'?">0</a>] ';
						elseif($adminMode || ($this->config['STATIC_HTML_UNTIL'] != -1 && $i > $this->config['STATIC_HTML_UNTIL'])) $pte_vals['{$PAGENAV}'] .= '[<a href="'.$this->config['PHP_SELF'].'?pagenum='.$i.'"'.$pageNext.'>'.$i.'</a>] ';
						else $pte_vals['{$PAGENAV}'] .= '[<a href="'.$i.$this->config['PHP_EXT'].'?"'.$pageNext.'>'.$i.'</a>] ';
					}
				}
				$pte_vals['{$PAGENAV}'] .= '</td>';
				if($threads_count > $next * $this->config['PAGE_DEF']){
					if($adminMode || ($this->config['STATIC_HTML_UNTIL'] != -1) && ($next > $this->config['STATIC_HTML_UNTIL'])) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$this->config['PHP_SELF'].'?pagenum='.$next.'" method="post">';
					else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$next.$this->config['PHP_EXT'].'" method="get">';
					$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('next_page').'"></div></form></td>';
				}else $pte_vals['{$PAGENAV}'] .= '<td>'._T('last_page').'</td>';
				$pte_vals['{$PAGENAV}'] .= '</tr></tbody></table>';
			}
			$dat .= $this->templateEngine->ParseBlock('MAIN', $pte_vals);
			$this->globalHTML->foot($dat, $thread_uid);
			// Remove any preset form values (DO NOT CACHE PRIVATE DETAILS!!!)
			$dat = preg_replace('/id="com" class="inputtext">(.*)<\/textarea>/','id="com" class="inputtext"></textarea>',$dat);
			$dat = preg_replace('/name="email" id="email" value="(.*)" class="inputtext">/','name="email" id="email" value="" class="inputtext">',$dat);
			$dat = preg_replace('/replyhl/','',$dat);
			// Minify
			if($this->config['MINIFY_HTML']){
				$dat = html_minify($dat);
			}
			// Archive / Output
			if($single_page || ($pagenum == -1 && !$resno)){ // Static cache page generation
				if($page==0) $logfilename = $this->config['PHP_SELF2'];
				else $logfilename = $page.$this->config['PHP_EXT'];

				$prefix = $this->board->getBoardCachedPath();
				$logFilePath = $prefix.$logfilename;

				$fp = fopen($logFilePath, 'w');
				stream_set_write_buffer($fp, 0);
				fwrite($fp, $dat);
				fclose($fp);
				chmod($logFilePath, 0666);
				if($this->config['STATIC_HTML_UNTIL'] != -1 && $this->config['STATIC_HTML_UNTIL']==$page) break; // Page Limit
			}else{ // PHP output (responsive mode/regular dynamic output)
				echo $dat;
				break;
			}
		}

		if($logRebuild) $this->actionLogger->logAction("Rebuilt board: ".$this->board->getBoardTitle().' ('.$this->board->getBoardUID().')', $this->board->getBoardUID());
	}
	
}