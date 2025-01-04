<?php
//board class to encapsulate data gotten directly from board table
class board {
	private $databaseConnection, $postNumberTable;
	private $config;
	public $board_uid, $board_identifier, $board_title, $board_sub_title, $config_name, $date_added, $board_file_url, $listed;

	// getters
	public function getBoardUID() { return intval($this->board_uid); }
	public function getBoardTitle() { return htmlspecialchars($this->board_title); }
	public function getBoardSubTitle() { return htmlspecialchars($this->board_sub_title); }
	public function getFullConfigPath() { return getBoardConfigDir().$this->config_name; }
	public function getConfigFileName() { return $this->config_name; }
	public function getDateAdded() { return $this->date_added; }
	public function getBoardIdentifier() { return $this->board_identifier; }
	public function getBoardListed(){ return $this->listed; }

	public function updateBoardPathCache() {
		$board = $this;
		$boardPathCachingIO = boardPathCachingIO::getInstance();

		$currentDirectory = getcwd().DIRECTORY_SEPARATOR;

		//update board path cache
		if($boardPathCachingIO->getRowByBoardUID($board->getBoardUID())) $boardPathCachingIO->updateBoardPathCacheByBoardUID($board->getBoardUID(), $currentDirectory);
		else $boardPathCachingIO->addNewCachedBoardPath($board->getBoardUID(), $currentDirectory);
	}

	public function getBoardCdnDir() { 
		$config = $this->loadBoardConfig();
		if(!$config) return;
		return $config['CDN_DIR'].$this->board_identifier.DIRECTORY_SEPARATOR;
	}

	public function getBoardCdnUrl() { 
		$config = $this->loadBoardConfig();
		return $config['CDN_URL'].$this->getBoardUID().'-'.$this->getBoardIdentifier().'/';
	}

	public function getBoardLocalUploadDir() {
		$board = $this;
		$boardPathCachingIO = boardPathCachingIO::getInstance();
		$boardPathCache = $boardPathCachingIO->getRowByBoardUID($board->getBoardUID());
		if(!$boardPathCache) return;

		return $boardPathCache->getBoardPath();
	}

	public function getBoardLocalUploadURL() {
		$config = $this->loadBoardConfig();
		return $config['WEBSITE_URL'].$this->getBoardIdentifier().'/';
	}

	public function getBoardUploadedFilesDirectory() {
		$config = $this->loadBoardConfig();
		if($config['USE_CDN']) {
			return $this->getBoardCdnDir();
		} else {
			return $this->getBoardLocalUploadDir();
		}
	}

	public function getBoardUploadedFilesURL() {
		$config = $this->loadBoardConfig();
		if($config['USE_CDN']) {
			return $this->getBoardCdnDir();
		} else {
			return $this->getBoardLocalUploadURL();
		}
	}

	public function getBoardURL() { 
		$config = $this->loadBoardConfig();
		return $config['WEBSITE_URL'].$this->getBoardIdentifier().'/';
	}

	public function getBoardRootURL() { 
		$config = $this->loadBoardConfig();
		return $config['WEBSITE_URL'];
	}
	
	private function __construct() {
		$dbSettings = getDatabaseSettings();
	
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
		$this->postNumberTable = $dbSettings['POST_NUMBER_TABLE'];
	}

	public function loadBoardConfig() {
		$fullConfigPath = $this->getFullConfigPath();
		if(!file_exists($fullConfigPath) || empty($fullConfigPath)) return;
		if($this->config) return $this->config;

		//only require when the config hasn't been set yet so it doesn't read from disk every time.
		require $fullConfigPath;
		$this->config = $config;
		return $config; 
	}

	/* Rebuild board HTML or output page HTML to a live PHP page */ 
	public function rebuildBoard($resno = 0,$pagenum = -1,$single_page = false, $last = -1){
		$config = $this->loadBoardConfig();
		if(empty($config)) die("No board config for {$this->board_title}:{$this->board_uid}");
		$dbSettings = getDatabaseSettings();
		
		$PIO = PIOPDO::getInstance();
		
		$globalHTML = new globalHTML($this);
		$AccountIO = AccountIO::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$PTE = PTELibrary::getInstance();
		$PMS = PMS::getInstance();
		$postRedirectIO = postRedirectIO::getInstance();
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();
		
		
		$pagenum = intval($pagenum);

		$adminMode = $roleLevel >=$config['roles']['LEV_JANITOR'] && $pagenum != -1 && !$single_page; // Front-end management mode

		$resno = intval($resno); // ensure thread url number is numeric
		$thread_uid = $PIO->resolveThreadUidFromResno($this, $resno);
		
		
		$page_start = $page_end = 0; // Static page number
		$inner_for_count = 1; // The number of inner loop executions
		$RES_start = $RES_amount = $hiddenReply = $tree_count = 0;
		$kill_sensor = $old_sensor = false; // Predictive system start flag
		$arr_kill = $arr_old = array(); // Obsolete numbered array
		$pte_vals = array('{$THREADFRONT}'=>'','{$THREADREAR}'=>'','{$SELF}'=>$config['PHP_SELF'],
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$DEL_PASS_TEXT}' => ($adminMode ? '<input type="hidden" name="func" value="delete">' : '')._T('del_pass'),
			'{$DEL_PASS_FIELD}' => '<input type="password" name="pwd" size="8" value="">',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
			'{$IS_THREAD}' => !!$resno);
		if($resno) $pte_vals['{$RESTO}'] = $resno;

		$PMS->useModuleMethods('GlobalMessage', array(&$pte_vals['{$GLOBAL_MESSAGE}'])); // "GlobalMessage" Hook Point
		$PMS->useModuleMethods('BlotterPreview', array(&$pte_vals['{$BLOTTER}'])); // "Blotter Preview" Hook Point
		
		if(!$resno){
			if($pagenum==-1){ // Rebuild mode (PHP dynamic output of multiple pages)
				$threads = $PIO->getThreadListFromBoard($this); // Get a full list of discussion threads
				$PMS->useModuleMethods('ThreadOrder', array($resno, $pagenum, $single_page, &$threads)); // "ThreadOrder" Hook Point
				$threads_count = count($threads);
				$inner_for_count = $threads_count > $config['PAGE_DEF'] ? $config['PAGE_DEF'] : $threads_count;
				$page_end = ($last == -1 ? ceil($threads_count / $config['PAGE_DEF']) : $last);
			}else{ // Discussion of the clue label pattern (PHP dynamic output one page)
				$threads_count = $PIO->threadCountFromBoard($this); // Discuss the number of strings
				if($pagenum < 0 || ($pagenum * $config['PAGE_DEF']) >= $threads_count) $globalHTML->error( _T('page_not_found')); // $Pagenum is out of range
				$page_start = $page_end = $pagenum; // Set a static page number
				$threads = $PIO->getThreadListFromBoard($this); // Get a full list of discussion threads
				$PMS->useModuleMethods('ThreadOrder', array($resno, $pagenum,$single_page,&$threads)); // "ThreadOrder" Hook Point
				$threads = array_splice($threads, $pagenum * $config['PAGE_DEF'], $config['PAGE_DEF']); // Remove the list of discussion threads after the tag
				$inner_for_count = count($threads); // The number of discussion strings is the number of cycles
			}
		}else{
			//check for redirect
			$movedThreadRedirect = $postRedirectIO->resolveRedirectedThreadLinkFromPostOpNumber($this, $resno);
			if($movedThreadRedirect) redirect($movedThreadRedirect);

			if(!$PIO->isThread($thread_uid)){ // Try to find the thread by child post no. instead
				$post_uid = $PIO->resolvePostUidFromPostNumber($this, $resno);
				$thread_uid_new = $PIO->fetchPostsFromThread($post_uid)[0]['thread_uid'] ?? false;
				
				if (!$PIO->isThread($thread_uid_new)) $globalHTML->error("Thread not found!");
				
				$resnoNew = $PIO->resolveThreadNumberFromUID($thread_uid_new); 
				$redirectString = $config['PHP_SELF']."?res=".$resnoNew."#p".$resno;
				redirect($redirectString); // Found, redirect
			}
			$AllRes = isset($pagenum) && ($_GET['pagenum']??'')=='all'; // Whether to use ALL for output

			// Calculate the response label range
			$tree_count = $PIO->postCountFromBoard($this, $resno) - 1; // Number of discussion thread responses
			if($tree_count && $config['RE_PAGE_DEF']){ // There is a response and RE_PAGE_DEF > 0 to do the pagination action
				if($pagenum==='all'){ // show all
					$pagenum = 0;
					$RES_start = 1; $RES_amount = $tree_count;
				}else{
					if($pagenum==='RE_PAGE_MAX') $pagenum = ceil($tree_count / $config['RE_PAGE_DEF']) - 1; // Special value: Last page
					if($pagenum < 0) $pagenum = 0; // negative number
					if($pagenum * $config['RE_PAGE_DEF'] >= $tree_count) $globalHTML->error(_T('page_not_found'));
					$RES_start = $pagenum * $config['RE_PAGE_DEF'] + 1; // Begin
					$RES_amount = $config['RE_PAGE_DEF']; // Take several
				}
			}elseif($pagenum > 0) $globalHTML->error( _T('page_not_found')); // In the case of no response, only pagenum = 0 or negative numbers are allowed
			else{ $RES_start = 1; $RES_amount = $tree_count; $pagenum = 0; } // Output All Responses
		}

		// Predict that old articles will be deleted and archives
		$tmp_total_size = $FileIO->getCurrentStorageSize($this); // The current usage of additional image files
		$tmp_STORAGE_MAX = $config['STORAGE_MAX'] * (($tmp_total_size >= $config['STORAGE_MAX']) ? 1 : 0.95); // Estimated upper limit
		if($config['STORAGE_LIMIT'] && $config['STORAGE_MAX'] > 0 && ($tmp_total_size >= $tmp_STORAGE_MAX)){
			$kill_sensor = true; // tag opens
			$arr_kill = $PIO->delOldAttachments($tmp_total_size, $tmp_STORAGE_MAX); // Outdated attachment array
		}

		$PMS->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], $resno)); // "ThreadFront" Hook Point
		$PMS->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], $resno)); // "ThreadRear" Hook Point

		// Generate static pages one page at a time
		for($page = $page_start; $page <= $page_end; $page++){
			$dat = ''; $pte_vals['{$THREADS}'] = '';
			$globalHTML->head($dat, $thread_uid);
			// form
			$qu = '';
			if ($config['USE_QUOTESYSTEM'] && $resno && isset($_GET['q'])) {
				$qq = explode(',', $_GET['q']);
				foreach ($qq as $q) {
					$q = intval($q);
					if ($q<1) continue;
					$qu.= '&gt;&gt;'.intval($q)."\r\n";
				}
			}
			$form_dat = '';
			$globalHTML->form($form_dat, $resno, '', '', '', $qu);

			$form_dat .= $PTE->ParseBlock('MODULE_INFO_HOOK',$pte_vals);
			$pte_vals['{$FORMDAT}'] = $form_dat;
			

			
			unset($qu);
			// Output the thread content
			for ($i = 0; $i < $inner_for_count; $i++) {
				$posts = array();
				$tree = array();
				$tree_cut = array();
				$tree_count = 0;
	
				if ($resno) {
					$posts = $PIO->fetchPostsFromThread($thread_uid);
					
					$tree = array_map(function ($post) {
						return $post['post_uid'];
					}, $posts);
					
					$tree_count = count($posts);
					$tree_cut = $tree_count;
	
				} else {
					if ($pagenum == -1 && ($page * $config['PAGE_DEF'] + $i) >= $threads_count) {
						break;
					}
	
					$tID = ($page_start == $page_end)
						? $threads[$i]
						: $threads[$page * $config['PAGE_DEF'] + $i];
	
					$tree_count = $PIO->postCountFromBoard($this, $tID) - 1;
		
					$RES_start = $tree_count - $config['RE_DEF'];
					if ($RES_start < 1) $RES_start = 1;
			
					$RES_amount = $config['RE_DEF'];
					$hiddenReply = $RES_start - 1;
		
					$tree = $PIO->fetchPostListFromBoard($this, $tID);
					$tree_cut = array_slice($tree, $RES_start, $RES_amount);
					array_unshift($tree_cut, $tree[0]);
					$posts = $PIO->fetchPosts($tree_cut);
				}
				$threads = $PIO->getThreadListFromBoard($this);
				$pte_vals['{$THREADS}'] .= $globalHTML->arrangeThread(
					$this,
					$config,
					$PTE,
					$globalHTML,
					$PIO,
					$threads,
					$tree,
					$tree_cut,
					$posts,
					$hiddenReply,
					$thread_uid,
					$arr_kill,
					$arr_old,
					$kill_sensor,
					$old_sensor,
					true,
					$adminMode,
					$inner_for_count,
					$i
				);
			}

			$pte_vals['{$PAGENAV}'] = '';

			// Page change judgment
			$prev = ($resno ? $pagenum : $page) - 1;
			$next = ($resno ? $pagenum : $page) + 1;
			if($resno){ // Response labels
				if($config['RE_PAGE_DEF'] > 0){ // The Responses tab is on
					$pte_vals['{$PAGENAV}'] .= '<table border="1" id="pager"><tbody><tr><td nowrap="nowrap">';
					$pte_vals['{$PAGENAV}'] .= ($prev >= 0) ? '<a rel="prev" href="'.$config['PHP_SELF'].'?res='.$resno.'&pagenum='.$prev.'">'._T('prev_page').'</a>' : _T('first_page');
					$pte_vals['{$PAGENAV}'] .= "</td><td>";
					if($tree_count==0) $pte_vals['{$PAGENAV}'] .= '[<b>0</b>] '; // No response
					else{
						for($i = 0, $len = $tree_count / $config['RE_PAGE_DEF']; $i <= $len; $i++){
							if(!$AllRes && $pagenum==$i) $pte_vals['{$PAGENAV}'] .= '[<b>'.$i.'</b>] ';
							else $pte_vals['{$PAGENAV}'] .= '[<a href="'.$config['PHP_SELF'].'?res='.$resno.'&pagenum='.$i.'">'.$i.'</a>] ';
						}
						$pte_vals['{$PAGENAV}'] .= $AllRes ? '[<b>'._T('all_pages').'</b>] ' : ($tree_count > $config['RE_PAGE_DEF'] ? '[<a href="'.$config['PHP_SELF'].'?res='.$resno.'">'._T('all_pages').'</a>] ' : '');
					}
					$pte_vals['{$PAGENAV}'] .= '</td><td nowrap="nowrap">';
					$pte_vals['{$PAGENAV}'] .= (!$AllRes && $tree_count > $next * $config['RE_PAGE_DEF']) ? '<a href="'.$config['PHP_SELF'].'?res='.$resno.'&pagenum='.$next.'">'._T('next_page').'</a>' : _T('last_page');
					$pte_vals['{$PAGENAV}'] .= '</td></tr></tbody></table>';
				}
			}else{ // General labels
				$pte_vals['{$PAGENAV}'] .= '<table border="1" id="pager"><tbody><tr>';
				if($prev >= 0){
					if(!$adminMode && $prev==0) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$config['PHP_SELF2'].'" method="get">';
					else{
						if($adminMode || ($config['STATIC_HTML_UNTIL'] != -1) && ($prev > $config['STATIC_HTML_UNTIL'])) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$config['PHP_SELF'].'?pagenum='.$prev.'" method="post">';
						else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$prev.$config['PHP_EXT'].'" method="get">';
					}
					$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('prev_page').'"></div></form></td>';
				}else $pte_vals['{$PAGENAV}'] .= '<td nowrap="nowrap">'._T('first_page').'</td>';
				$pte_vals['{$PAGENAV}'] .= '<td>';
				for($i = 0, $len = $threads_count / $config['PAGE_DEF']; $i <= $len; $i++){
					if($page==$i) $pte_vals['{$PAGENAV}'] .= "[<b>".$i."</b>] ";
					else{
						$pageNext = ($i==$next) ? ' rel="next"' : '';
						if(!$adminMode && $i==0) $pte_vals['{$PAGENAV}'] .= '[<a href="'.$config['PHP_SELF2'].'?">0</a>] ';
						elseif($adminMode || ($config['STATIC_HTML_UNTIL'] != -1 && $i > $config['STATIC_HTML_UNTIL'])) $pte_vals['{$PAGENAV}'] .= '[<a href="'.$config['PHP_SELF'].'?pagenum='.$i.'"'.$pageNext.'>'.$i.'</a>] ';
						else $pte_vals['{$PAGENAV}'] .= '[<a href="'.$i.$config['PHP_EXT'].'?"'.$pageNext.'>'.$i.'</a>] ';
					}
				}
				$pte_vals['{$PAGENAV}'] .= '</td>';
				if($threads_count > $next * $config['PAGE_DEF']){
					if($adminMode || ($config['STATIC_HTML_UNTIL'] != -1) && ($next > $config['STATIC_HTML_UNTIL'])) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$config['PHP_SELF'].'?pagenum='.$next.'" method="post">';
					else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$next.$config['PHP_EXT'].'" method="get">';
					$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('next_page').'"></div></form></td>';
				}else $pte_vals['{$PAGENAV}'] .= '<td nowrap="nowrap">'._T('last_page').'</td>';
				$pte_vals['{$PAGENAV}'] .= '</tr></tbody></table>';
			}
			$dat .= $PTE->ParseBlock('MAIN', $pte_vals);
			$globalHTML->foot($dat, $thread_uid);
			// Remove any preset form values (DO NOT CACHE PRIVATE DETAILS!!!)
			$dat = preg_replace('/id="com" cols="48" rows="4" class="inputtext">(.*)<\/textarea>/','id="com" cols="48" rows="4" class="inputtext"></textarea>',$dat);
			$dat = preg_replace('/name="email" id="email" size="28" value="(.*)" class="inputtext">/','name="email" id="email" size="28" value="" class="inputtext">',$dat);
			$dat = preg_replace('/replyhl/','',$dat);
			// Minify
			if($config['MINIFY_HTML']){
				$dat = html_minify($dat);
			}
			// Archive / Output
			if($single_page || ($pagenum == -1 && !$resno)){ // Static cache page generation
				if($page==0) $logfilename = $config['PHP_SELF2'];
				else $logfilename = $page.$config['PHP_EXT'];
				$fp = fopen($logfilename, 'w');
				stream_set_write_buffer($fp, 0);
				fwrite($fp, $dat);
				fclose($fp);
				chmod($logfilename, 0666);
				if($config['STATIC_HTML_UNTIL'] != -1 && $config['STATIC_HTML_UNTIL']==$page) break; // Page Limit
			}else{ // PHP output (responsive mode/regular dynamic output)
				echo $dat;
				break;
			}
		}
	}
	
	/* Get the last post number */
	public function getLastPostNoFromBoard() {
		$query = "SELECT COUNT(post_number) FROM {$this->postNumberTable} WHERE board_uid = :board_uid";
		$params = [':board_uid' => $this->getBoardUID()];
		
		$result = $this->databaseConnection->fetchColumn($query, $params);
		return $result;
	}
	
	public function incrementBoardPostNumber() {
		$query = "INSERT INTO {$this->postNumberTable} (board_uid) VALUES(:board_uid)";
		$params = [':board_uid' => $this->getBoardUID()];
		
		$this->databaseConnection->execute($query, $params);	
	}
}
