<?php
// Handle account sessions for koko
class adminPageHandler {
	private $config, $board;
	
	public function __construct($board) { 
		$this->config = $board->loadBoardConfig();
		$this->board = $board;
	}
	
	public function handleAdminPageSelection($functionName, &$dat) {
		$this->$functionName($dat);
	}

		/* Manage article(threads) mode */
	private function admindel(&$dat){		
		$filterAction = $_POST['filterformsubmit'] ?? null;
		
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filter') {
			$filterRoleFromPOST = $_POST['filterrole'] ?? '';
			$filterBoardFromPOST = $_POST['filterboard'] ?? '';
			
			$filterIP = htmlspecialchars($_POST['manage_filterip'] ?? '');
			$filterComment = htmlspecialchars($_POST['manage_filtercomment'] ?? '');
			$filterName = htmlspecialchars($_POST['manage_filtername'] ?? '');
			$filterSubject = htmlspecialchars($_POST['manage_filtersubject'] ?? '');
			$filterBoard = (is_array($filterBoardFromPOST) ? array_map('htmlspecialchars', $filterBoardFromPOST) : [htmlspecialchars($filterBoardFromPOST)]);
			
			setcookie('manage_filterip', $filterIP, time() + (86400 * 30), "/");
			setcookie('manage_filtercomment', $filterComment, time() + (86400 * 30), "/");
			setcookie('manage_filtername', $filterName, time() + (86400 * 30), "/");
			setcookie('manage_filtersubject', $filterSubject, time() + (86400 * 30), "/");
			setcookie('filterboard', serialize($filterBoard), time() + (86400 * 30), "/");

			redirect($this->config['PHP_SELF'].'?mode=admin&admin=del');
			exit;
		} else if($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filterclear') {
			setcookie('manage_filterip', "", time() - 3600, "/");
			setcookie('manage_filtercomment', "", time() - 3600, "/");
			setcookie('manage_filtername', "", time() - 3600, "/");
			setcookie('manage_filtersubject', "", time() - 3600, "/");
			setcookie('filterboard', "", time() - 3600, "/");

			redirect($this->config['PHP_SELF'].'?mode=admin&admin=del');
			exit;
		}
		$filtersBoards = (isset($_COOKIE['filterboard'])) ? unserialize($_COOKIE['filterboard']) : [$this->board->getBoardUID()];
		
		//filter list for the database
		$filters = [
			'ip_address' => $_COOKIE['manage_filterip'] ?? null,
			'name' => $_COOKIE['manage_filtername'] ?? null,
			'comment' => $_COOKIE['manage_filtercomment'] ?? null,
			'subject' => $_COOKIE['manage_filtersubject'] ?? null,
			'board' => $filtersBoards ?? '',
		];

		$board = $this->board;
		$PIO = PIOPDO::getInstance();
		
		$globalHTML = new globalHTML($this->board);
		$ActionLogger = ActionLogger::getInstance();
		$AccountIO = AccountIO::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$boardIO = boardIO::getInstance();
		$PTE = PTELibrary::getInstance();
		$PMS = PMS::getInstance();
		$staffSession = new staffAccountFromSession;
		$softErrorHandler = new softErrorHandler($this->board);
		
		 $roleLevel = $staffSession->getRoleLevel();
		 
		$postsPerPage = $this->config['ADMIN_PAGE_DEF'];
		$numberOfFilteredPosts = $PIO->postCount($filters);
		$page = $_REQUEST['page'] ?? 0;

		if (!filter_var($page, FILTER_VALIDATE_INT) && $page != 0) $globalHTML->error("Page number was not a valid int.");

		$page = ($page >= 0) ? $page : 1;
		$offset = $page * $postsPerPage;
		
		
		$pass = $_POST['pass']??''; // Admin password
		$onlyimgdel = $_POST['onlyimgdel']??''; // Only delete the image
		$modFunc = '';
		$delno = $thsno = array();
		$message = ''; // Display message after deletion
		$host = $_GET['host'] ?? 0;
		$posts = array(); //posts to display in the manage posts table
		$noticeHost = "";
		$searchHost = filter_var($host, FILTER_VALIDATE_IP) ?: filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
		if ($searchHost) {
			$softErrorHandler->handleAuthError($this->config['roles']['LEV_JANITOR']);
			$noticeHost = '<h2>Viewing all posts from: '.$searchHost.'. Click submit to cancel.</h2><br>';
		}
		
		// Delete the article(thread) block
		$delno = array_merge($delno, $_POST['clist']??array());
		if($delno) {
			$delnoActionLogStr = is_array($delno) ? implode(', No. ',$delno) : $delno;
			$ActionLogger->logAction("Delete post posts: $delnoActionLogStr".($onlyimgdel?' (file only)':''), $this->board->getBoardUID());
		}
		if($onlyimgdel != 'on') $PMS->useModuleMethods('PostOnDeletion', array($delno, 'backend')); // "PostOnDeletion" Hook Point
		$files = ($onlyimgdel != 'on') ? $PIO->removePosts($delno) : $PIO->removeAttachments($delno);

		if($searchHost) $posts = $PIO->getPostsFromIP($searchHost);
		else $posts = $PIO->getFilteredPosts($postsPerPage, $page * $this->config['ADMIN_PAGE_DEF'], $filters) ?? array();
		$posts_count = count($posts); // Number of cycles
		
		
		$globalHTML->drawManagePostsFilterForm($dat, $board);
		$dat .= "<div id=\"reloadTable\" class=\"centerText\">[<a href=\"{$this->config['PHP_SELF']}?mode=admin&admin=del\">Reload table</a>]</div>";
		
		$dat .= '<form action="'.$this->config['PHP_SELF'].'" method="POST">';
		$dat .= '<input type="hidden" name="mode" value="admin">
						<input type="hidden" name="admin" value="del">'.$message.$noticeHost.'
						<div id="tableManagePostsContainer">
							<table id="tableManagePosts" class="postlists">
								<thead>
									<tr>'._T('admin_list_header').'</tr>
								</thead>
								<tbody>';
		
		for($j = 0; $j < $posts_count; $j++){
			$bg = ($j % 2) ? 'row1' : 'row2'; // Background color
			extract($posts[$j]);
			
			//post board
			$postBoard = $boardIO->getBoardByUID($boardUID);
			$postBoardConfig = $postBoard->loadBoardConfig();
			
			// Modify the field style
			$name =substr($name, 0, 500);
			$sub = substr($sub, 0, 500);
			if($email) $name = "<a href=\"mailto:$email\">$name</a>";
			$com = substr($com, 0, 500);
	
			
			// The first part of the discussion is the stop tick box and module function
			$modFunc = ' ';
			$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$j], !$PIO->isThreadOP($posts[$j]['post_uid']))); // "AdminList" Hook Point
			if($thread_uid==0){ // $resto = 0 (the first part of the discussion string)
				$flgh = $PIO->getPostStatus($status);
			}
	
			// Extract additional archived image files and generate a link
			if($ext && $FileIO->imageExists($tim.$ext, $postBoard)){
				$clip = '<a href="'.$FileIO->getImageURL($tim.$ext, $postBoard).'" target="_blank">'.$tim.$ext.'</a>';
				$size = $FileIO->getImageFilesize($tim.$ext, $postBoard);
				$thumbName = $FileIO->resolveThumbName($tim, $postBoard);
				if($thumbName != false) $size += $FileIO->getImageFilesize($thumbName, $postBoard);
			}else{
				$clip = $md5chksum = '--';
				$size = 0;
			}
	 
			if ($roleLevel <= $this->config['roles']['LEV_JANITOR']) {
				$host = substr(hash('sha256', $host), 0, 10);
			}
			
				// Print out the interface
			$dat .= '
				<tr>
					<td class="colFunc">' . $modFunc . '</td>
					<td class="colDel"><input type="checkbox" name="clist[]" value="' . $post_uid . '"><a target="_blank" href="'.$postBoard->getBoardURL().$postBoardConfig['PHP_SELF'].'?res=' . $no . '">' . $no . '</a></td>
					<td class="colBoard">/' . $postBoard->getBoardIdentifier() . '/ ('.$postBoard->getBoardUID().')</td>
					<td class="colDate"><span class="time">' . $now . '</span></td>
					<td class="colSub"><span class="title">' . $sub . '</span></td>
					<td class="colName"><b class="name">' . $name . '</b></td>
					<td class="colComment">' . $com . '</td>
					<td class="colHost">' . $host . ' <a target="_blank" href="https://otx.alienvault.com/indicator/ip/' . $host . '" title="Resolve hostname"><img height="12" src="' . $this->config['STATIC_URL'] . 'image/glass.png"></a> <a href="?	mode=admin&admin=del&host=' . $host . '" title="See all posts">★</a></td>
					<td class="colImage">' . $clip . ' (' . $size . ')<br>' . $md5chksum . '</td>
				</tr>';
		}
		
		if(!$posts) $dat .= '
				<tr>
					<td colspan="9"><b class="error" id="no-posts-found"> - No posts found! - </b></td>
				</tr>';
		
		$dat.= '
			</tbody>
		</table>
		</div>
		<p class="centerText">
			<button type="button" onclick="selectAll()">Select all</button>
			<input type="submit" value="'._T('admin_submit_btn').'"> <input type="reset" value="'._T('admin_reset_btn').'"> [<label><input type="checkbox" name="onlyimgdel" id="onlyimgdel" 		value="on">'._T('del_img_only').'</label>]
		</p>
	</form>
	<hr>
	<script>
	function selectAll() {
	    var checkboxes = document.querySelectorAll(\'input[name="clist[]"]\');
	        checkboxes.forEach(function(checkbox) {
	        checkbox.checked = true;
	    });
	}
	</script>
	';

		$dat .= $globalHTML->drawPager($postsPerPage, $numberOfFilteredPosts, $globalHTML->fullURL().$this->config['PHP_SELF'].'?mode=admin&admin=del');
	}

	private function actionlog(&$dat) {	
		$globalHTML = new globalHTML($this->board);
		$ActionLogger = ActionLogger::getInstance();
		
		$filterAction = $_POST['filterformsubmit'] ?? null;
		
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filter') {
			$filterRoleFromPOST = $_POST['filterrole'] ?? '';
			$filterBoardFromPOST = $_POST['filterboard'] ?? '';
			
			$filterIP = htmlspecialchars($_POST['filterip'] ?? '');
			$filterDateBefore = htmlspecialchars($_POST['filterdatebefore'] ?? '');
			$filterDateAfter = htmlspecialchars($_POST['filterdateafter'] ?? '');
			$filterName = htmlspecialchars($_POST['filtername'] ?? '');
			$filterBan = isset($_POST['bans']) ? 'checked' : '';
			$filterDelete = isset($_POST['deleted']) ? 'checked' : '';
			$filterRole = (is_array($filterRoleFromPOST) ? array_map('htmlspecialchars', $filterRoleFromPOST) : [htmlspecialchars($filterRoleFromPOST)]);
			$filterBoard = (is_array($filterBoardFromPOST) ? array_map('htmlspecialchars', $filterBoardFromPOST) : [htmlspecialchars($filterBoardFromPOST)]);
			
			setcookie('filterip', $filterIP, time() + (86400 * 30), "/");
			setcookie('filterdatebefore', $filterDateBefore, time() + (86400 * 30), "/");
			setcookie('filterdateafter', $filterDateAfter, time() + (86400 * 30), "/");
			setcookie('filtername', $filterName, time() + (86400 * 30), "/");
			setcookie('filterban', $filterBan, time() + (86400 * 30), "/");
			setcookie('filterdelete', $filterDelete, time() + (86400 * 30), "/");
			setcookie('filterrole', serialize($filterRole), time() + (86400 * 30), "/");
			setcookie('filterboard', serialize($filterBoard), time() + (86400 * 30), "/");

			redirect($this->config['PHP_SELF'].'?admin=action&mode=admin');
			exit;
		} else if($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filterclear') {
			setcookie('filterip', "", time() - 3600, "/");
			setcookie('filterdatebefore', "", time() - 3600, "/");
			setcookie('filterdateafter', "", time() - 3600, "/");
			setcookie('filtername', "", time() - 3600, "/");
			setcookie('filterban', "", time() - 3600, "/");
			setcookie('filterdelete', "", time() - 3600, "/");
			setcookie('filterrole', "", time() - 3600, "/");
			setcookie('filterboard', "", time() - 3600, "/");
			
			redirect($this->config['PHP_SELF'].'?admin=action&mode=admin');
			exit;
		}
		
		$filtersBoards = (isset($_COOKIE['filterboard'])) ? unserialize($_COOKIE['filterboard']) : [$this->board->getBoardUID()];
		$filtersRoles = (isset($_COOKIE['filterrole'])) ? unserialize($_COOKIE['filterrole']) : array_values($this->config['roles']); 
		
		//filter list for the database
		$filters = [
			'ip_address' => $_COOKIE['filterip'] ?? null,
			'name' => $_COOKIE['filtername'] ?? null,
			'ban' => $_COOKIE['filterban'] ?? null,
			'deleted' => $_COOKIE['filterdelete'] ?? null,
			'role' => $filtersRoles ?? '',
			'board' => $filtersBoards ?? '',
			'date_before' => $_COOKIE['filterdatebefore'] ?? '',
			'date_after' => $_COOKIE['filterdateafter'] ?? '',
		];
		$tableEntries = '';
		$limit = $this->config['ACTIONLOG_MAX_PER_PAGE'];
		$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
		$page = ($page >= 0) ? $page : 1;
		$offset = $page * $limit;
		
		$globalHTML->drawModFilterForm($dat, $this->board);
		
		$entriesFromDatabase = $ActionLogger->getSpecifiedLogEntries($limit, $offset, $filters);
		$numberOfActionLogs = $ActionLogger->getAmountOfLogEntries($filters);
	
		if(!$entriesFromDatabase) {
			$tableEntries .= 
				'<tr>
					<td colspan="7">
						<b class="error"> - No entries found in database -</b> 
					</td> 
				</tr>';
		
		} else {
			//generate table entry html
			foreach($entriesFromDatabase as $actionLogEntry) {
				$tableEntries .= "
				<tr>
					<td>{$actionLogEntry->getBoardTitle()}</td>
					<td>{$actionLogEntry->getBoardUID()}</td>
					<td>".htmlspecialchars($actionLogEntry->getName())."</td>
					<td>{$globalHTML->roleNumberToRoleName($actionLogEntry->getRole())}</td>
					<td>{$actionLogEntry->getIpAddress()}</td>
					<td>{$actionLogEntry->getLogAction()}</td>
					<td>{$actionLogEntry->getTimeAdded()}</td>
				 </tr>";
			}
		}
		
		$dat .= "<div id=\"reloadTable\" class=\"centerText\">[<a href=\"{$this->config['PHP_SELF']}?admin=action&mode=admin\">Reload table</a>]</div>
			<table class=\"postlists\" id=\"actionlogtable\">
				<thead>
					<tr>
						<th>Board title</th>
						<th>Board UID</th>
						<th>Name</th>
						<th>Role</th>
						<th>IP</th>
						<th>Action</th>
						<th>Time</th>
					</tr>
				</thead>
				<tbody>
					$tableEntries
				</tbody>
			</table>
		";

		$dat .= $globalHTML->drawPager($limit, $numberOfActionLogs, $globalHTML->fullURL().$this->config['PHP_SELF'].'?admin=action&mode=admin');
	}
	
	private function adminLogout() {
		$loginSessionHandler = new loginSessionHandler();
		$loginSessionHandler->logout();
		redirect($_SERVER['PHP_SELF'].'?mode=admin');
	}
	
}


