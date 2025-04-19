<?php

// Handle misc html output for koko

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class globalHTML {
	private $config, $board;

	private $templateEngine, $moduleEngine;

	public function __construct(IBoard $board) { 
		$this->board = $board;
		$this->moduleEngine = new moduleEngine($board);
		$this->config = $board->loadBoardConfig();

		$this->templateEngine = $board->getBoardTemplateEngine();
	}

	/* 輸出A錯誤畫面 */
	public function error($mes, $dest=''){

		if(is_file($dest)) unlink($dest);
		$pte_vals = array('{$SELF2}'=>$this->config['PHP_SELF2'].'?'.time(), '{$MESG}'=>$mes, '{$RETURN_TEXT}'=>_T('return'), '{$BACK_TEXT}'=>_T('error_back'), '{$BACK_URL}'=>htmlspecialchars($_SERVER['HTTP_REFERER']??''));
		$dat = '';
		$this->head($dat);
		$dat .= $this->templateEngine->ParseBlock('ERROR',$pte_vals);
		$this->foot($dat);
		exit($dat);
	}
	
	public function roleNumberToRoleName($roleNumber) {
		$num = intval($roleNumber);
		$from = '';
	
		switch ($num) {
				case $this->config['roles']['LEV_NONE']: $from = 'None'; break;
				case $this->config['roles']['LEV_USER']: $from = 'User'; break;
				case $this->config['roles']['LEV_JANITOR']: $from = 'Janitor'; break;
				case $this->config['roles']['LEV_MODERATOR']: $from = 'Moderator'; break;
				case $this->config['roles']['LEV_ADMIN']: $from = 'Admin'; break;
				default: $from = '[UNKNOWN ROLE]'; break;
		}
		return $from;
	}

	/* 輸出表頭 | document head */
	public function head(&$dat, $resno=0){
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
			$pte_vals['{$PAGE_TITLE}'] = ($post[0]['sub'] ? $post[0]['sub'] : strip_tags($CommentTitle)).' - '.$this->board->getBoardTitle();
		}
		$html .= $this->templateEngine->ParseBlock('HEADER',$pte_vals);
		$this->moduleEngine->useModuleMethods('Head', array(&$html, $resno)); // "Head" Hook Point
		$html .= '</head>';
		$pte_vals += array('{$HOME}' => '[<a href="'.$this->config['HOME'].'" target="_top">'._T('head_home').'</a>]',
			'{$STATUS}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=status">'._T('head_info').'</a>]',
			'{$ADMIN}' => '[<a href="'.$this->config['PHP_SELF'].'?mode=admin">'._T('head_admin').'</a>]',
			'{$REFRESH}' => '[<a href="'.$this->config['PHP_SELF2'].'?">'._T('head_refresh').'</a>]',
			'{$HOOKLINKS}' => '',
			);
			
		$this->moduleEngine->useModuleMethods('Toplink', array(&$pte_vals['{$HOOKLINKS}'],$resno)); // "Toplink" Hook Point
		$this->moduleEngine->useModuleMethods('AboveTitle', array(&$pte_vals['{$BANNER}'])); //"AboveTitle" Hook Point
		
		$html .= $this->templateEngine->ParseBlock('BODYHEAD',$pte_vals);
		$dat .= $html;
		return $html;
	}

	/* 輸出頁尾文字 | footer credits */
	public function foot(&$dat, $res=false){
		$html = '';
	
		$pte_vals = array('{$FOOTER}'=>'','{$IS_THREAD}'=>$res);
		$this->moduleEngine->useModuleMethods('Foot', array(&$pte_vals['{$FOOTER}'])); // "Foot" Hook Point
		$pte_vals['{$FOOTER}'] .= '- <a rel="nofollow noreferrer license" href="https://web.archive.org/web/20150701123900/http://php.s3.to/" target="_blank">GazouBBS</a> + <a rel="nofollow noreferrer license" href="http://www.2chan.net/" 	target="_blank">futaba</a> + <a rel="nofollow noreferrer license" href="https://pixmicat.github.io/" target="_blank">Pixmicat!</a> + <a rel="nofollow noreferrer license" href="https://kokonotsuba.github.io/" target="_blank">Kokonotsuba</a> -';
		$html .= $this->templateEngine->ParseBlock('FOOTER',$pte_vals);
		$dat .= $html;
		return $html;
	}
	
		/* 發表用表單輸出 | user contribution form */
	function form(&$dat, $resno, $name='', $mail='', $sub='', $com='', $cat=''){
		$FileIO = PMCLibrary::getFileIOInstance();
		
		$hidinput = ($resno ? '<input type="hidden" name="resto" value="'.$resno.'">' : '');
	
		$pte_vals = array(
			'{$RESTO}' => strval($resno),
			'{$GLOBAL_MESSAGE}' => '',
			'{$BLOTTER}' => '',
			'{$IS_THREAD}' => $resno!=0,
			'{$FORM_HIDDEN}' => $hidinput,
			'{$MAX_FILE_SIZE}' => strval($this->config['TEXTBOARD_ONLY'] ? 0 : $this->config['MAX_KB'] * 1024),
			'{$FORM_NAME_FIELD}' => '<input tabindex="1" maxlength="'.$this->config['INPUT_MAX'].'" type="text" name="name" id="name" value="'.$name.'" class="inputtext">',
			'{$FORM_EMAIL_FIELD}' => '<input tabindex="2" maxlength="'.$this->config['INPUT_MAX'].'" type="text" name="email" id="email" value="'.$mail.'" class="inputtext">',
			'{$FORM_TOPIC_FIELD}' => '<input tabindex="3" maxlength="'.$this->config['INPUT_MAX'].'"  type="text" name="sub" id="sub" value="'.$sub.'" class="inputtext">',
			'{$FORM_SUBMIT}' => '<button tabindex="10" type="submit" name="mode" value="regist">'.($resno ? 'Post' : 'New Thread' ).'</button>',
			'{$FORM_COMMENT_FIELD}' => '<textarea tabindex="6" maxlength="'.$this->config['COMM_MAX'].'" name="com" id="com" class="inputtext">'.$com.'</textarea>',
			'{$FORM_DELETE_PASSWORD_FIELD}' => '<input tabindex="6" type="password" name="pwd" id="pwd" maxlength="8" value="" class="inputtext">',
			'{$FORM_EXTRA_COLUMN}' => '',
			'{$FORM_FILE_EXTRA_FIELD}' => '',
			'{$FORM_NOTICE}' => ($this->config['TEXTBOARD_ONLY'] ? '' :_T('form_notice',str_replace('|',', ',$this->config['ALLOW_UPLOAD_EXT']),$this->config['MAX_KB'],($resno ? $this->config['MAX_RW'] : $this->config['MAX_W']),($resno ? $this->config['MAX_RH'] : $this->config['MAX_H']))),
			'{$HOOKPOSTINFO}' => '');
		if(!$this->config['TEXTBOARD_ONLY'] && ($this->config['RESIMG'] || !$resno)){
			$pte_vals += array('{$FORM_ATTECHMENT_FIELD}' => '<input type="file" name="upfile" id="upfile">');
	
			if (!$resno) {
				$pte_vals += array('{$FORM_NOATTECHMENT_FIELD}' => '<input type="checkbox" name="noimg" id="noimg" value="on">');
			}
			$this->moduleEngine->useModuleMethods('PostFormFile', array(&$pte_vals['{$FORM_FILE_EXTRA_FIELD}']));
		}
		$this->moduleEngine->useModuleMethods('PostForm', array(&$pte_vals['{$FORM_EXTRA_COLUMN}'])); // "PostForm" Hook Point
		if($this->config['USE_CATEGORY']) {
			$pte_vals += array('{$FORM_CATEGORY_FIELD}' => '<input tabindex="5" type="text" name="category" id="category" value="'.$cat.'" class="inputtext">');
		}
		if($this->config['STORAGE_LIMIT']) $pte_vals['{$FORM_NOTICE_STORAGE_LIMIT}'] = _T('form_notice_storage_limit',$FileIO->getCurrentStorageSize($this->board),$this->config['STORAGE_MAX']);
		$this->moduleEngine->useModuleMethods('PostInfo', array(&$pte_vals['{$HOOKPOSTINFO}'])); // "PostInfo" Hook Point
		
		$dat .= $this->templateEngine->ParseBlock('POSTFORM',$pte_vals);
	}
	
		/* 網址自動連結 */
	public function auto_link_callback2($matches) {
		$URL = $matches[1].$matches[2]; // https://example.com
	
		// Redirect URL!
		if ($this->config['REF_URL']) {
			$URL_Encode = urlencode($URL);  // https%3A%2F%2Fexample.com (For the address bar)
			return '<a href="'.$this->config['REF_URL'].'?'.$URL_Encode.'" target="_blank" rel="nofollow noreferrer">'.$URL.'</a>';
		}
		// Also works if its blank!
		return '<a href="'.$URL.'" target="_blank" rel="nofollow noreferrer">'.$URL.'</a>';
	}
	
	public function auto_link_callback($matches){
		return (strtolower($matches[3]) == "</a>") ? $matches[0] : preg_replace_callback('/([a-zA-Z]+)(:\/\/[\w\+\$\;\?\.\{\}%,!#~*\/:@&=_-]+)/u', [$this, 'auto_link_callback2'], $matches[0]);
	}
	
	public function auto_link($proto){
		$proto = preg_replace('|<br\s*/?>|',"\n",$proto);
		$proto = preg_replace_callback('/(>|^)([^<]+?)(<.*?>|$)/m',[$this, 'auto_link_callback'],$proto);
		return str_replace("\n",'<br>',$proto);
	}

	/* 引用標註 */
	public function quote_unkfunc($comment){
		$comment = preg_replace('/(^|<br\s*\/?>)((?:&gt;|＞).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc">$2</span>', $comment);
		$comment = preg_replace('/(^|<br\s*\/?>)((?:&lt;).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc2">$2</span>', $comment);
		return $comment;
	}

	/* quote links */
	public function quote_link($board, $PIO, $comment){
		if($this->config['USE_QUOTESYSTEM']){
			if(preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)){
				$matches_unique = array();
				foreach($matches as $val){ if(!in_array($val, $matches_unique)) array_push($matches_unique, $val); }
				foreach($matches_unique as $val){
					$postNum = $PIO->resolvePostUidFromPostNumber($board, $val[2]);
					$post = $PIO->fetchPosts($postNum)[0] ?? false;

					if(!$post) continue;
					
					$postResno = $PIO->resolveThreadNumberFromUID($post['thread_uid']);
					if($post){
						$comment = str_replace($val[0], '<a href="'.$this->config['PHP_SELF'].'?res='.($postResno?$postResno:$post['no']).'#p'.$post['boardUID'].'_'.$post['no'].'" class="quotelink">'.$val[0].'</a>', $comment);
					} else {
						$comment = str_replace($val[0], '<a href="javascript:void(0);" class="quotelink"><del>'.$val[0].'</del></a>', $comment);
					}
				}
			}
		}
		return $comment;
	}
	
	public function buildThreadNavButtons($board, $threads, $threadInnerIterator, $PIO) {		
		$threadNumberList = $PIO->mapThreadUidListToPostNumber($threads);
		$upArrow = '';
		$downArrow = '';
		$postFormButton = '<a title="Go to post form" href="#postform">&#9632;</a>';
		
		if(!$threadNumberList) return;
		
		// Determine if thread is at the 'top'
		if ($threadInnerIterator == 0) {
			$upArrow = ''; // No thread above the current thread
		} else {
			$aboveThreadID = isset($threadNumberList[$threadInnerIterator - 1]) ? $threadNumberList[$threadInnerIterator - 1] : '';
			if ($aboveThreadID) {
				$upArrow = '<a title="Go to above thread" href="#t'.$aboveThreadID['boardUID'].'_'.$aboveThreadID['post_op_number'].'">&#9650;</a>';
			}
		}
		
		// Determine if thread is at the 'bottom'
		if ($threadInnerIterator >= count($threadNumberList) - 1) {
			$downArrow = ''; // No more threads below this one
		} else {
				$belowThreadID = isset($threadNumberList[$threadInnerIterator + 1]) ? $threadNumberList[$threadInnerIterator + 1] : '';
			if ($belowThreadID) {
				$downArrow = '<a title="Go to below thread" href="#t'.$belowThreadID['boardUID'].'_'.$belowThreadID['post_op_number'].'">&#9660;</a>';
			}
		}
		
		$THREADNAV = $postFormButton.$upArrow.$downArrow;
		
		return $THREADNAV;
	}
	
	/* 取得完整的網址 */
	public function fullURL(){
		return '//'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], $this->config['PHP_SELF']));
	}
	
	public function drawAdminLoginForm() {
		return "<table class=\"formtable centerBlock\">
				<tbody>
					<tr>
						<td class='postblock'><label for=\"username\">Username</label></td>
						<td><input tabindex=\"1\" maxlength=\"100\" type=\"text\" name=\"username\" id=\"username\" value=\"\" class=\"inputtext\"></td>
					</tr>
					<tr>
						<td class='postblock'><label for=\"password\">Password</label></td>
						<td><input tabindex=\"1\" maxlength=\"100\" type=\"password\" name=\"password\" id=\"password\" value=\"\" class=\"inputtext\"></td>
					</tr>
				</tbody>
			</table>
			<button type=\"submit\" name=\"mode\" value=\"admin\">Login</button>";
	}
	
	public function drawAdminTheading(&$dat, $staffSession) {
		$username = $staffSession->getUsername();
		$role = $staffSession->getRoleLevel();
		$loggedInInfo = '';
		
		if($role) $loggedInInfo = "<div class=\"username\">Logged in as $username ({$this->roleNumberToRoleName($role)})</div>";
		
		$html = "<h2 class=\"theading3\">Administrator mode$loggedInInfo</h2>";
		$dat .= $html;
		return $html;
	}
	
	//for the board filter form
	public function generateBoardListCheckBoxHTML($currentBoard, $filterBoard, $boards = null, $selectAll = false) {
		$BoardIO = boardIO::getInstance();

		$listHTML = '';
	
		if(!$boards) $boards = $BoardIO->getAllBoards();

		foreach($boards as $board) {
			$boardTitle = htmlspecialchars($board->getBoardTitle());
			$boardUID = htmlspecialchars($board->getBoardUID());
		
			$isChecked = $selectAll || in_array($boardUID, $filterBoard) || ($boardUID == $currentBoard->getBoardUID() && empty($filterBoard));
			
			$listHTML .= '<label class="filterSelectBoardItem"><input name="filterboard[]" type="checkbox" value="' . $boardUID . '" ' . ($isChecked ? 'checked' : '') . '>' . $boardTitle . '</label>  ';
		}
	
		return $listHTML;
	}

	//for the rebuild action form
	public function generateRebuildListCheckboxHTML(array $boards) {
		$listHTML = '<ul class="filterSelectBoardList">';

		foreach($boards as $board) {
			$boardTitle = htmlspecialchars($board->getBoardTitle());
			$boardUID = htmlspecialchars($board->getBoardUID());
			
			$listHTML .= '<li><label class="filterSelectBoardItem"><input name="rebuildBoardUIDs[]" type="checkbox" value="' . $boardUID . '" checked>' . $boardTitle . '</label></li>';
		}
	
		$listHTML .= '</ul>';
	
		return $listHTML;
	}
	
	//for the move_thread form
	public function generateBoardListRadioHTML($currentBoard = null, $boards = null) {
		$BoardIO = boardIO::getInstance();
	
		$listHTML = '';
		
		if(!$boards) $boards = $BoardIO->getAllRegularBoards();
	
		foreach($boards as $board) {
			if($currentBoard && $board->getBoardUID() === $currentBoard->getBoardUID()) continue;
			
			$boardTitle = htmlspecialchars($board->getBoardTitle());
			$boardUID = htmlspecialchars($board->getBoardUID());
			
			$listHTML .= '<label> <input name="radio-board-selection" type="radio" value="' . $boardUID . '">'.$boardTitle.'</label>  ';
		}
		
		return $listHTML;
	}
	

	public function drawPushPostForm(&$dat, $pushPostCharacterLimit,  $url) {
		$PIO = PIOPDO::getInstance();
		$boardIO = boardIO::getInstance();

		$post_uid = $_GET['post_uid'] ?? null;
		if(!$post_uid) $this->error("No post uid selected");

		$postNumber = $PIO->resolvePostNumberFromUID($post_uid);

		
		$dat .= '<form id="push-post-form" method="POST" action="'.$url.'">
					<input type="hidden" name="">
					<input type="hidden" name="push-post-post-uid" value="'.htmlspecialchars($post_uid).'">
					<table>
						<tbody>
							<tr>
								<td class="postblock"><label for="push-post-post-num">Post Number</label></td>
								<td><span id="push-post-post-num">'. $postNumber.'</span></td>
							</tr>
							<tr>
								<td class="postblock"> <label for="push-post-username">Username</label> </td>
								<td> <input id="push-post-username" name="push-post-username" maxlength="'.$pushPostCharacterLimit.'"> </td>
							</tr>
							<tr>
								<td class="postblock"> <label for="push-post-comment">Comment</label> </td>
								<td> <textarea id="push-post-comment" name="push-post-comment" maxlength="'.$pushPostCharacterLimit.'"></textarea> </td>
							</tr>
							<tr>
								<td class="postblock"></td>
								<td><button type="submit" name="push-post-submit" value="push it!">Push post</button></td>
							</tr>
						</tbody>
					</table>
			</form>';
	}

	public function drawModFilterForm(&$dat, $board) {
		$filterIP = $_COOKIE['filterip'] ?? '';
		$filterDateBefore = $_COOKIE['filterdatebefore'] ?? '';
		$filterDateAfter = $_COOKIE['filterdateafter'] ?? '';
		$filterName = $_COOKIE['filtername'] ?? '';
		$filterBan = $_COOKIE['filterban'] ?? '';
		$filterDelete = $_COOKIE['filterdelete']	 ?? '';
		
		//role levels
		$none = $this->config['roles']['LEV_NONE'];
		$user = $this->config['roles']['LEV_USER'];
		$janitor = $this->config['roles']['LEV_JANITOR'];
		$moderator = $this->config['roles']['LEV_MODERATOR'];
		$admin = $this->config['roles']['LEV_ADMIN'];
		
		$filterRole = json_decode($_COOKIE['filterrole'] ?? '', true); if(!is_array($filterRole)) $filterRole = [$none, $user, $janitor, $moderator, $admin];
		$filterBoard = json_decode($_COOKIE['filterboard'] ?? '', true); if(!is_array($filterBoard)) $filterBoard = [$board->getBoardUID(), GLOBAL_BOARD_UID];

		$boardCheckboxHTML = $this->generateBoardListCheckBoxHTML($board, $filterBoard);
		$dat .= '
		<details id="filtercontainer" class="detailsbox">
			<summary class="postblock">Filter action log</summary>
			<form action="' . $this->fullURL() . $this->config['PHP_SELF'].'?admin=action&mode=admin" method="POST">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="ip">IP address</label></td>
							<td><input  id="ip" name="filterip" value="'.$filterIP.'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="filtername">Name</label></td>
							<td><input id="filtername" name="filtername" value="'.$filterName.'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="dateafter">From</label></td>
							<td><input  type="date" id="dateafter" name="filterdateafter" value="'.$filterDateAfter.'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="datebefore">To</label></td>
							<td><input  type="date" id="datebefore" name="filterdatebefore" value="'.$filterDateBefore.'"></td>
						</tr>
						<tr>
							<td class="postblock">Actions</td>
							<td> 
								<label><input type="checkbox" id="bans" name="bans" '.$filterBan.'>Bans</label>  
								<label><input type="checkbox" id="deletions" name="deleted" '.$filterDelete.'>Deletions</label>
							</td>
						</tr>
						<tr id="rolerow">
							<td class="postblock">Roles <br> <div class="selectlinktextjs" id="roleselectall">[<a>Select all</a>]</div></td>
							<td>
									<ul class="littlelist">
 										<li> <label>	<input name="filterrole[]" type="checkbox" value="' . $none . '" '.(in_array($none, $filterRole) ? 'checked' : '').'>None</label> </li>
 										<li> <label> 	<input name="filterrole[]"  type="checkbox" value="' . $user . '" '.(in_array($user, $filterRole) ? 'checked' : '').'>User</label> </li>
										<li> <label>  <input name="filterrole[]"  type="checkbox" value="' . $janitor . '" '.(in_array($janitor, $filterRole) ? 'checked' : '').'>Janitor</label> </li>
										<li> <label>	<input name="filterrole[]"  type="checkbox" value="' . $moderator . '" '.(in_array($moderator, $filterRole) ? 'checked' : '').'>Moderator</label> </li>
										<li> <label>	<input name="filterrole[]"  type="checkbox" value="' . $admin . '" '.(in_array($admin, $filterRole) ? 'checked' : '').'>Admin</label> </li>
									</ul>
							</td>
						</tr>
						<tr id="boardrow">
							<td class="postblock"><label for="filterboard">Boards</label><div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div></td>
							<td>
								'.$boardCheckboxHTML.'
							</td>
						</tr>
					</tbody>
				</table>
				<button type="submit" name="filterformsubmit" value="filter">Filter</button> <button type="submit" name="filterformsubmit" value="filterclear">Clear filter</button> <input type="reset" value="Reset">
			</form>
		</details>
		';
	}
	
	public function drawManagePostsFilterForm(&$dat, $board) {
		$filterIP = $_COOKIE['manage_filterip'] ?? '';
		$filterComment = $_COOKIE['manage_filtercomment'] ?? '';
		$filterName = $_COOKIE['manage_filtername'] ?? '';
		$filterSubject = $_COOKIE['manage_filtersubject'] ?? '';
		$filterBoard = json_decode($_COOKIE['filterboard'] ?? '', true); if(!is_array($filterBoard)) $filterBoard = [$board->getBoardUID()];
		
		$boardCheckboxHTML = $this->generateBoardListCheckBoxHTML($board, $filterBoard);
		$dat .= '
		<details id="filtercontainer" class="detailsbox centerText">
			<summary>Filter posts</summary>
			<form action="' . $this->fullURL() . $this->config['PHP_SELF'].'?mode=admin&admin=del" method="POST">
				<table id="adminPostFilterTable" class="centerBlock">
					<tbody>
						<tr>
							<td class="postblock"><label for="manage_filterip">IP address</label></td>
							<td><input  id="manage_filterip" name="manage_filterip" value="'.$filterIP.'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="manage_filtername">Name</label></td>
							<td><input id="manage_filtername" name="manage_filtername" value="'.$filterName.'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="manage_filtersubject">Subject</label></td>
							<td><input  id="manage_filtersubject" name="manage_filtersubject" value="'.$filterSubject.'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="manage_filtercomment">Comment</label></td>
							<td><input id="manage_filtercomment" name="manage_filtercomment" value="'.$filterComment.'"></td>
						</tr>
						<tr id="boardrow">
							<td class="postblock">
								<label for="filterboard">Boards</label>
								<div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div>
							</td>
							<td>'.$boardCheckboxHTML.'</td>
						</tr>
					</tbody>
				</table>
				<button type="submit" name="filterformsubmit" value="filter">Filter</button> <button type="submit" name="filterformsubmit" value="filterclear">Clear filter</button> <input type="reset" value="Reset">
			</form>
		</details>
		';
	}
	
	public function drawOverboardFilterForm(&$dat, $board) {
		$boardIO = boardIO::getInstance();
		
		$allListedBoards = $boardIO->getAllListedBoardUIDs();
		$filterBoard = json_decode($_COOKIE['overboard_filterboards'] ?? ''); if(!is_array($filterBoard)) $filterBoard = $allListedBoards;
		$boardCheckboxHTML = $this->generateBoardListCheckBoxHTML($board, $filterBoard, $boardIO->getBoardsFromUIDs($allListedBoards));
		$dat .= '
		<div class="overboardFilterFormContainer">
			<details id="overboard-filter-form" class="detailsbox"> <summary>Filter boards</summary>
				<form action="' . $this->fullURL() . $this->config['PHP_SELF'].'?mode=overboard" method="POST">
					<div class="postblock">
							<div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div>
									'.$boardCheckboxHTML.'
					</div>
					<button type="submit" name="filterformsubmit" value="filter">Filter</button> <input type="reset" value="Reset">
				</form>
			</details>
		</div>
		';
	}
	
	public function drawAccountTable() {
		$AccountIO = AccountIO::getInstance();

		$dat = '';
		$accountsHTML = '';
		$accounts = $AccountIO->getAllAccounts();
		foreach ($accounts as $account) {
				$accountID = $account->getId();
				$accountUsername = $account->getUsername();
				$accountRoleLevel = $account->getRoleLevel();
				$accountNumberOfActions = $account->getNumberOfActions();
				$accountLastLogin = $account->getLastLogin() ?? '<i>Never</i>';
		
				$actionHTML = '[<a title="Delete account" href="' . $this->config['PHP_SELF'] . '?mode=handleAccountAction&del=' . $accountID . '">D</a>] ';
				if ($accountRoleLevel + 1 <= $this->config['roles']['LEV_ADMIN']) $actionHTML .= '[<a title="Promote account" href="' . $this->config['PHP_SELF'] . '?mode=handleAccountAction&up=' . $accountID. '">▲</a>]';
				if ($accountRoleLevel- 1 > $this->config['roles']['LEV_NONE']) $actionHTML .= '[<a title="Demote account" href="' . $this->config['PHP_SELF'] . '?mode=handleAccountAction&dem=' . $accountID . '">▼</a>]';
				
				$accountsHTML .= '<tr> 
						<td class="colAccountID">' . $accountID . '</td>
						<td class="colUsername">' . $accountUsername . ' </td>
						<td class="colRoleLevel">' . $this->roleNumberToRoleName($accountRoleLevel) . '</td>
						<td class="colNumberofActions">' . $accountNumberOfActions . '</td>
						<td class="colLastLogin">' . $accountLastLogin . '</td>
						<td class="colActions">' . $actionHTML . '</td>
					</tr>';
		}
		$dat .= '
				<table id="tableStaffList" class="postlists">
					<thead>
						<tr>
							<th class="colAccountID">ID</th>
							<th class="colUsername">Username</th>
							<th class="colRoleLevel">Role</th>
							<th class="colNumberofActions">Total actions</th>
							<th class="colLastLogin">Last logged in</th>
							<th class="colActions">Actions</th>
						</tr>
					</thead>
					<tbody>
						' . $accountsHTML . '
					</tbody>
				</table>';
		return $dat;
	}
	
	public function drawBoardTable() {
		$boardIO = boardIO::getInstance();

		$dat = '';
		$boardsHTML = '';
		$boards = $boardIO->getAllRegularBoards();
		foreach ($boards as $board) {
				$boardUID = $board->getBoardUID();
				$boardIdentifier = $board->getBoardIdentifier();
				$boardTitle = $board->getBoardTitle();
				$boardDateAdded = $board->getDateAdded();
		
				$actionHTML = '[<a title="View board" href="' . $this->config['PHP_SELF'] . '?mode=boards&view='.$boardUID.'">View</a>] ';
				$boardsHTML .= '
					<tr> 
						<td>' . $boardUID . '</td>
						<td>' . $boardIdentifier . '</td>
						<td>' . $boardTitle . '</td>
						<td>' . $boardDateAdded . '</td>
						<td>' . $actionHTML . '</td>
					</tr>';
		}
		$dat .= '
				<table class="postlists">
					<tbody>
					<tr>
						<th>Board UID</th>
						<th>Board identifier</th>
						<th>Board title</th>
						<th>Date added</th>
						<th>View</th>
					</tr>
					' . $boardsHTML . '
					</tbody>
				</table>';
		return $dat;
	}
	
	public function generateAdminLinkButtons() {
		$staffSession = new staffAccountFromSession;
		$authRoleLevel = $staffSession->getRoleLevel();
		
		$linksAboveBar =  '
			<ul id="adminNavBar">
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=account">Account</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=boards">Boards</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?pagenum=0">Live frontend</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=rebuild">Rebuild board</a></li>
				';
		$this->moduleEngine->useModuleMethods('LinksAboveBar', array(&$linksAboveBar,'admin',$authRoleLevel));
		$linksAboveBar .= "</ul>";
		return $linksAboveBar;
	}
	
	public function drawPager($entriesPerPage, $totalEntries, $url) {

		if ((filter_var($totalEntries, FILTER_VALIDATE_INT) === false || $totalEntries < 0) 
		|| (filter_var($entriesPerPage, FILTER_VALIDATE_INT) === false || $entriesPerPage < 0)) {
			$this->error("Total entries must be a valid non-negative integer.");
		}		
	
		$totalPages = (int) ceil($totalEntries / $entriesPerPage);
		$currentPage = $_REQUEST['page'] ?? 0;

		if (filter_var($currentPage, FILTER_VALIDATE_INT) === false) {
			$this->error("Invalid page number");
		}

		if ($currentPage < 0) $currentPage = 0;
		if ($currentPage >= $totalPages) $currentPage = $totalPages - 1;
	
		$pageHTML = '<table id="pager"><tbody><tr>';
	
		// First/Prev buttons
		if ($currentPage <= 0) {
			$pageHTML .= '<td>First</td>';
			$pageHTML .= '<td>Prev</td>';
		} else {
			$pageHTML .= '<td><a href="'.$url.'&page=0">First</a></td>';
			$pageHTML .= '<td><a href="'.$url.'&page='.($currentPage - 1).'">Prev</a></td>';
		}
	
		// Page number links
		$pageHTML .= '<td>';
	
		for ($pageIterator = 0; $pageIterator < $totalPages; $pageIterator++) {
			if ($pageIterator == $currentPage) {
				$pageHTML .= "<b> [$pageIterator] </b>";
			} else {
				$pageHTML .= ' [<a href="'.$url.'&page='.$pageIterator.'">'.$pageIterator.'</a>] ';
			}
		}
	
		$pageHTML .= '</td>';
	
		// Next/Last buttons
		if ($currentPage >= $totalPages - 1) {
			$pageHTML .= '<td>Next</td>';
			$pageHTML .= '<td>Last</td>';
		} else {
			$pageHTML .= '<td><a href="'.$url.'&page='.($currentPage + 1).'">Next</a></td>';
			$pageHTML .= '<td><a href="'.$url.'&page='.($totalPages - 1).'">Last</a></td>';
		}
	
		$pageHTML .= '</tr></tbody></table>';
		return $pageHTML;
	}
	
	
	/* Output thread schema */
	public function arrangeThread(board $board, array $config, LoggerInjector $PIO, array $threads, array $tree, mixed $tree_cut, array $posts, 
									int $hiddenReply, string $resno, mixed $arr_kill,  bool $kill_sensor, bool $showquotelink=true, 
									bool $adminMode=false, int $threadIterator = 0, string $overboardBoardTitleHTML = '', string $crossLink = '') {
		$resno = isset($resno) && $resno ? $resno : 0;
		$FileIO = PMCLibrary::getFileIOInstance();
	
		$thdat = ''; // Discuss serial output codes
		$posts_count = count($posts); // Number of cycles
		$thread_uid = $posts[0]['thread_uid'];
		$postOPNumber = $posts[0]['no'];
		$replyCount = $PIO->getPostCountFromThread($thread_uid);
		$imageURL = '';
		
		if(gettype($tree_cut) == 'array') $tree_cut = array_flip($tree_cut); // array_flip + isset Search Law
		if(gettype($tree) == 'array') $tree_clone = array_flip($tree);
		// $i = 0 (first article), $i = 1~n (response)
		for($i = 0; $i < $posts_count; $i++){
			$imgsrc = $img_thumb = $imgwh_bar = '';
			$IMG_BAR = $REPLYBTN = $QUOTEBTN = $BACKLINKS = $POSTFORM_EXTRA = $WARN_OLD = $WARN_BEKILL = $WARN_ENDREPLY = $WARN_HIDEPOST = $THREADNAV = '';
			extract($posts[$i]); // Take out the thread content setting variable
			$isReply = $i === 0 ? false : true;
		
			// Set the field value
			if($config['CLEAR_SAGE']) $email = preg_replace('/^sage( *)/i', '', trim($email)); // Clear the "sage" keyword from the e-mail
			if($config['ALLOW_NONAME']==2){ // Forced beheading
				if($email) $now = "<a href=\"mailto:$email\">$now</a>";
			}else{
				if($email) $name = "<a href=\"mailto:$email\">$name</a>";
			}
	
			$com = $this->quote_link($board, $PIO, $com);
			$com = $this->quote_unkfunc($com);
			
		// Mark threads that hit age limit (this replaces the old system for marking old threads)
			if (!$i && $config['MAX_AGE_TIME'] && $_SERVER['REQUEST_TIME'] - $time > ($config['MAX_AGE_TIME'] * 60 * 60)) $com .= "<p class='markedDeletion'><span class='warning'>"._T('warn_oldthread')."</span></p>";
			
			// Configure attachment display
			if ($ext) {
				if(!$fname) $fname = $tim;
				$truncated = (strlen($fname)>40 ? substr($fname,0,40).'(&hellip;)' : $fname);
				if ($fname=='SPOILERS') {
					$truncated=$fname;
				} else {
					$truncated.=$ext;
					$fname.=$ext;
				}
	
	 			$fnameJS = str_replace('&#039;', '\&#039;', $fname);
	 			$truncatedJS = str_replace('&#039;', '\&#039;', $truncated);
				$imageURL = $FileIO->getImageURL($tim.$ext, $board); // image URL
				$thumbName = $FileIO->resolveThumbName($tim, $board); // thumb Name

				$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$config['STATIC_URL'].'image/nothumb.gif" class="postimg" alt="'.$imgsize.'"></a>'; // Default display style (when no preview image)
				if($tw && $th){
					if ($thumbName != false){ // There is a preview image
						$thumbURL = $FileIO->getImageURL($thumbName, $board); // thumb URL
						$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$thumbURL.'" width="'.$tw.'" height="'.$th.'" class="postimg" alt="'.$imgsize.'" title="Click to show full image"></a>';
					}
				} else if ($ext === "swf") {
					$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$config['SWF_THUMB'].'" class="postimg" alt="SWF Embed"></a>'; // Default display style (when no preview 	image)
				} else $imgsrc = '';
				if($config['SHOW_IMGWH'] && ($imgw || $imgh)) $imgwh_bar = ', '.$imgw.'x'.$imgh; // Displays the original length and width dimensions of the attached image file
				$IMG_BAR = _T('img_filename').'<a href="'.$imageURL.'" target="_blank" rel="nofollow" onmouseover="this.textContent=\''.$fnameJS.'\';" onmouseout="this.textContent=\''.$truncatedJS.'\'"> '.$truncated.'</a> <a href="'.$imageURL.'" alt="'.$fname.'" download="'.$fname.'"><div class="download"></div></a> <span class="fileProperties">('.$imgsize.$imgwh_bar.')</span> '.$img_thumb;
			}
	
	        // Set the response/reference link
	        if($config['USE_QUOTESYSTEM']) {
	            if($resno){ // Response mode
	                if($showquotelink) $QUOTEBTN = '<a href="'.$crossLink.$config['PHP_SELF'].'?res='.$postOPNumber.'#q'.$boardUID.'_'.$no.'" class="qu" title="Quote">'.strval($no).'</a>';
	                else $QUOTEBTN = '<a href="'.$crossLink.$config['PHP_SELF'].'?res='.$postOPNumber.'#q'.$no.'" title="Quote">'.strval($no).'</a>';
	            }else{
	                if(!$i)    $REPLYBTN = '[<a href="'.$crossLink.$config['PHP_SELF'].'?res='.$no.'">'._T('reply_btn').'</a>]'; // First article
	                $QUOTEBTN = '<a href="'.$crossLink.$config['PHP_SELF'].'?res='.$postOPNumber.'#q'.$no.'" title="Quote">'.$no.'</a>';
	            }
				
			} else {
				if($resno&&!$i)	$REPLYBTN = '[<a href="'.$crossLink.$config['PHP_SELF'].'?res='.$no.'">'._T('reply_btn').'</a>]';
				$QUOTEBTN = $no;
			}
	
			if($adminMode){ // Front-end management mode
				$modFunc = '';
				$this->moduleEngine->useModuleMethods('AdminList', array(&$modFunc, $posts[$i], $isReply)); // "AdminList" Hook Point
				$POSTFORM_EXTRA .= $modFunc;
			}
	
			// Set thread properties
			if($config['STORAGE_LIMIT'] && $kill_sensor) if(isset($arr_kill[$no])) $WARN_BEKILL = '<div class="warning">'._T('warn_sizelimit').'</div>'; // Predict to delete too large files
			if(!$i){ // 首篇 Only
				$flgh = $PIO->getPostStatus($status);
				if($hiddenReply) $WARN_HIDEPOST = '<div class="omittedposts">'._T('notice_omitted',$hiddenReply).'</div>'; // There is a hidden response
			}
			// Automatically link category labels
			if($config['USE_CATEGORY']){
				$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
				$ary_category_count = count($ary_category);
				$ary_category2 = array();
				for($p = 0; $p < $ary_category_count; $p++){
					if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.$crossLink.$config['PHP_SELF'].'?mode=module&load=mod_searchcategory&c='.urlencode($c).'">'.$c.'</a>';
				}
				$category = implode(', ', $ary_category2);
			}else $category = '';
			// Final output
			if($i){ // Response
				$arrLabels = bindReplyValuesToTemplate($board, $config, $post_uid, $no, $postOPNumber, $sub, $name, $now, $category, $QUOTEBTN, $IMG_BAR, $imgsrc, $WARN_BEKILL, $com, $POSTFORM_EXTRA, '', $BACKLINKS, $resno);
				
				if($resno) $arrLabels['{$RESTO}']=$postOPNumber;
				$this->moduleEngine->useModuleMethods('ThreadReply', array(&$arrLabels, $posts[$i], $resno)); // "ThreadReply" Hook Point
				$thdat .= $this->templateEngine->ParseBlock('REPLY', $arrLabels);
			}else{ // First Article
				
				if($resno) $arrLabels['{$RESTO}']=$postOPNumber; else $THREADNAV = $this->buildThreadNavButtons($board, $threads, $threadIterator, $PIO);
				$arrLabels = bindOPValuesToTemplate($board, $config, $post_uid, $no, $sub, $name, $now, $category, $QUOTEBTN, $REPLYBTN, $IMG_BAR, $imgsrc, $fname, $imgsize, $imgw, $imgh, $imageURL, 
																					$replyCount, $WARN_OLD, $WARN_BEKILL, 	$WARN_ENDREPLY, $WARN_HIDEPOST, $com, $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $resno); 
				$arrLabels['{$BOARD_THREAD_NAME}'] = $overboardBoardTitleHTML;

				$this->moduleEngine->useModuleMethods('ThreadPost', array(&$arrLabels, $posts[$i], $resno)); // "ThreadPost" Hook Point
				
				$thdat .= $this->templateEngine->ParseBlock('THREAD', $arrLabels);
			}
		}
		$thdat .= $this->templateEngine->ParseBlock('THREADSEPARATE',($resno)?array('{$RESTO}'=>$postOPNumber):array());
		return $thdat;
	}
	
}		
