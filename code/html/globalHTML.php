<?php

// Handle misc html output for koko

class globalHTML {
	private $config, $board;

	private $templateEngine, $moduleEngine, $threadSingleton;

	public function __construct(IBoard $board) { 
		$this->board = $board;
		$this->config = $board->loadBoardConfig();

		$this->templateEngine = $board->getBoardTemplateEngine();
		$this->moduleEngine = new moduleEngine($board);
		$this->threadSingleton = threadSingleton::getInstance();
	}

	/* 輸出A錯誤畫面 */
	public function error($mes, $dest='') {
		if(is_file($dest)) unlink($dest);
		$pte_vals = array('{$SELF2}'=>$this->config['PHP_SELF2'].'?'.time(), '{$MESG}'=>$mes, '{$RETURN_TEXT}'=>_T('return'), '{$BACK_TEXT}'=>_T('error_back'), '{$BACK_URL}'=>htmlspecialchars($_SERVER['HTTP_REFERER']??''));
		$dat = '';
		$this->head($dat);
		$dat .= $this->templateEngine->ParseBlock('ERROR',$pte_vals);
		$this->foot($dat);
		exit($dat);
	}
	
	/* 輸出表頭 | document head */
	public function head(&$dat, $resno=0){
		$html = '';
		
		$pte_vals = array('{$RESTO}'=>$resno?$resno:'', '{$IS_THREAD}'=>boolval($resno));
		if ($resno) {
			$post = $this->threadSingleton->fetchPostsFromThread($resno);
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
			'{$FORM_NAME_FIELD}' => '<input maxlength="'.$this->config['INPUT_MAX'].'" type="text" name="name" id="name" value="'.$name.'" class="inputtext">',
			'{$FORM_EMAIL_FIELD}' => '<input maxlength="'.$this->config['INPUT_MAX'].'" type="text" name="email" id="email" value="'.$mail.'" class="inputtext">',
			'{$FORM_TOPIC_FIELD}' => '<input maxlength="'.$this->config['INPUT_MAX'].'"  type="text" name="sub" id="sub" value="'.$sub.'" class="inputtext">',
			'{$FORM_SUBMIT}' => '<button id="buttonPostFormSubmit" type="submit" name="mode" value="regist">'.($resno ? 'Post' : 'New thread' ).'</button>',
			'{$FORM_COMMENT_FIELD}' => '<textarea maxlength="'.$this->config['COMM_MAX'].'" name="com" id="com" class="inputtext">'.$com.'</textarea>',
			'{$FORM_DELETE_PASSWORD_FIELD}' => '<input type="password" name="pwd" id="pwd" maxlength="8" value="" class="inputtext">',
			'{$FORM_EXTRA_COLUMN}' => '',
			'{$FORM_FILE_EXTRA_FIELD}' => '',
			'{$FORM_NOTICE}' => (
				$this->config['TEXTBOARD_ONLY']
					? ''
					: _T(
						'form_notice',
						implode(', ', array_keys($this->config['ALLOW_UPLOAD_EXT'])),
						$this->config['MAX_KB'],
						$resno ? $this->config['MAX_RW'] : $this->config['MAX_W'],
						$resno ? $this->config['MAX_RH'] : $this->config['MAX_H']
					)
			),
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
			$pte_vals += array('{$FORM_CATEGORY_FIELD}' => '<input type="text" name="category" id="category" value="'.$cat.'" class="inputtext">');
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

	/**
	 * Replace quote links in a post comment with anchor tags.
	 * If the quoted post exists (based on quote links), it links to that post.
	 * If not, the quote is displayed as a deleted reference using <del>.
	 */
	public function quote_link(array $quoteLinksFromBoard, array $post, int $threadNumber): string {
		if (
			empty($this->config['USE_QUOTESYSTEM']) ||
			empty($post['com']) ||
			empty($post['post_uid'])
		) {
			return $post['com'] ?? '';
		}
	
		$comment = $post['com'];
		$postUid = $post['post_uid'];
	
		// Safely get quoteLink entries for this specific post
		$quoteLinkEntries = $quoteLinksFromBoard[$postUid] ?? [];
	
		// Index target post numbers to their thread number
		$targetPostToThreadNumber = [];
		foreach ($quoteLinkEntries as $entry) {
			if (
				isset($entry['target_post']['no'], $entry['target_thread']['post_op_number']) &&
				is_numeric($entry['target_post']['no']) &&
				is_numeric($entry['target_thread']['post_op_number'])
			) {
				$postNo = (int)$entry['target_post']['no'];
				$threadNo = (int)$entry['target_thread']['post_op_number'];
				$targetPostToThreadNumber[$postNo] = $threadNo;
			}
		}
	
		// Match all quote-like strings in the comment
		if (!preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)) {
			return $comment;
		}
	
		// Build replacements in one pass
		$replacements = [];
		foreach ($matches as $match) {
			$fullMatch = $match[0];         // Full quoted text (e.g. ">>123")
			$postNumber = (int)$match[2];   // Extracted numeric part (e.g. 123)
	
			if (isset($replacements[$fullMatch])) {
				continue; // Skip duplicates
			}
	
			// Check if we have a known thread number for the quoted post number
			if (isset($targetPostToThreadNumber[$postNumber])) {
				// Get the thread number where the quoted post resides
				$targetThreadNumber = $targetPostToThreadNumber[$postNumber];

				// Determine if the quoted post is in a different thread (i.e. cross-thread)
				$isCrossThread = $targetThreadNumber !== $threadNumber;

				// Generate the full URL to the quoted post
				$url = htmlspecialchars($this->board->getBoardThreadURL($targetThreadNumber, $postNumber));

				// Assign a CSS class, adding 'crossThreadLink' if the post is in a different thread
				$linkClass = 'quotelink' . ($isCrossThread ? ' crossThreadLink' : '');

				// Build the final anchor tag for replacement
				$replacements[$fullMatch] = '<a href="' . $url . '" class="' . $linkClass . '">' . $fullMatch . '</a>';
			} else {
				// Post was not found — strike out
				$replacements[$fullMatch] = '<a href="javascript:void(0);" class="quotelink"><del>' . $fullMatch . '</del></a>';
			}
		}
	
		// Replace in one pass
		return strtr($comment, $replacements);
	}
	
	public function buildThreadNavButtons(array $threadList, int $threadInnerIterator): string {
		if (!$threadList || !isset($threadList[$threadInnerIterator]['thread'])) return '';
	
		$threadsPerPage = $this->config['PAGE_DEF'] ?? 10;
		$offset = intdiv($threadInnerIterator, $threadsPerPage) * $threadsPerPage;
	
		// Slice the list to only the current page range
		$threadList = array_slice($threadList, $offset, $threadsPerPage);
		$currentIndex = $threadInnerIterator % $threadsPerPage;
	
		$upArrow = '';
		$downArrow = '';
		$postFormButton = '<a title="Go to post form" href="#postform">&#9632;</a>';
	
		// Up arrow (previous thread)
		if ($currentIndex > 0 && isset($threadList[$currentIndex - 1]['thread'])) {
			$aboveThread = $threadList[$currentIndex - 1]['thread'];
			$upArrow = '<a title="Go to above thread" href="#t' . htmlspecialchars($aboveThread['boardUID']) . '_' . htmlspecialchars($aboveThread['post_op_number']) . '">&#9650;</a>';
		}
	
		// Down arrow (next thread)
		if ($currentIndex < count($threadList) - 1 && isset($threadList[$currentIndex + 1]['thread'])) {
			$belowThread = $threadList[$currentIndex + 1]['thread'];
			$downArrow = '<a title="Go to below thread" href="#t' . htmlspecialchars($belowThread['boardUID']) . '_' . htmlspecialchars($belowThread['post_op_number']) . '">&#9660;</a>';
		}
	
		return $postFormButton . $upArrow . $downArrow;
	}	
	
	/* 取得完整的網址 */
	public function fullURL(){
		return '//'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], $this->config['PHP_SELF']));
	}
	
	public function drawAdminLoginForm(string $adminUrl) {
		return "
			<form action=\"$adminUrl\" method=\"post\">
				<table class=\"formtable centerBlock\">
					<tbody>
						<tr>
							<td class='postblock'><label for=\"username\">Username</label></td>
							<td><input type=\"text\" name=\"username\" id=\"username\" value=\"\" class=\"inputtext\"></td>
						</tr>
						<tr>
							<td class='postblock'><label for=\"password\">Password</label></td>
							<td><input type=\"password\" name=\"password\" id=\"password\" value=\"\" class=\"inputtext\"></td>
						</tr>
					</tbody>
				</table>
				<button type=\"submit\" name=\"mode\" value=\"admin\">Login</button>
			</form>";
	}
	
	public function drawAdminTheading(&$dat, $staffSession) {
		$username = htmlspecialchars($staffSession->getUsername(), ENT_QUOTES, 'UTF-8');
		$roleEnum = $staffSession->getRoleLevel();
		$roleName = htmlspecialchars($roleEnum->displayRoleName(), ENT_QUOTES, 'UTF-8');
	
		$loggedInInfo = '';
		
		if($roleEnum !== \Kokonotsuba\Root\Constants\userRole::LEV_NONE) {
			$loggedInInfo = "<div class=\"username\">Logged in as $username ($roleName)</div>";
		}
	
		$html = "<div class=\"theading3\"><h2>Administrator mode</h2>$loggedInInfo</div>";
		
		$dat .= $html;
		return $html;
	}	
	
	//for the board filter form
	public function generateBoardListCheckBoxHTML($currentBoard, $filterBoard, $boards = null, $selectAll = false) {
		$BoardIO = boardIO::getInstance();

		$listHTML = '';
	
		if(!$boards) $boards = $BoardIO->getAllBoards();

		foreach($boards as $board) {
			$boardTitle = $board->getBoardTitle();
			$boardUID = htmlspecialchars($board->getBoardUID());
		
			$isChecked = $selectAll || in_array($boardUID, $filterBoard) || ($boardUID === $currentBoard->getBoardUID() && empty($filterBoard));
			
			$listHTML .= '<li><label class="filterSelectBoardItem"><input name="board[]" type="checkbox" value="' . $boardUID . '" ' . ($isChecked ? 'checked' : '') . '>' . $boardTitle . '</label></li>';
		}
	
		return $listHTML;
	}

	//for the rebuild action form
	public function generateRebuildListCheckboxHTML(array $boards) {
		$listHTML = '<ul class="filterSelectBoardList">';

		foreach($boards as $board) {
			$boardTitle = $board->getBoardTitle();
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
			
			$boardTitle = $board->getBoardTitle();
			$boardUID = htmlspecialchars($board->getBoardUID());
			
			$listHTML .= '<label> <input name="radio-board-selection" type="radio" value="' . $boardUID . '">'.$boardTitle.'</label>  ';
		}
		
		return $listHTML;
	}
	

	public function drawPushPostForm(&$dat, $pushPostCharacterLimit,  $url) {
		$PIO = PIOPDO::getInstance();

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

	public function drawModFilterForm(string &$dat, board $board, array $filters) {
		$filterIP = $filters['ip_address'];
		$filterDateBefore = $filters['date_before'];
		$filterDateAfter = $filters['date_after'];
		$filterName = $filters['log_name'];
		$filterBan = !empty($filters['ban']) ? 'checked' : '';
		$filterDelete = !empty($filters['deleted']) ? 'checked' : '';
		$filterRole = is_array($filters['role']) ? $filters['role'] : [];
		$filterBoard = is_array($filters['board']) ? $filters['board'] : [];

		// role levels
		$none = \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value;
		$user = \Kokonotsuba\Root\Constants\userRole::LEV_USER->value;
		$janitor = \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR->value;
		$moderator = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR->value;
		$admin = \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value;
	
		$boardCheckboxHTML = $this->generateBoardListCheckBoxHTML($board, $filterBoard);
		$dat .= '
		<form class="detailsboxForm" id="actionLogFilterForm" action="' . $this->fullURL() . $this->config['PHP_SELF'] . '" method="get">
			<details id="filtercontainer" class="detailsbox">
				<summary>Filter action log</summary>
				<div class="detailsboxContent">
					<input type="hidden" name="mode" value="actionLog">
					<input type="hidden" name="filterSubmissionFlag" value="true">

					<table>
						<tbody>
							<tr>
								<td class="postblock"><label for="ip">IP address</label></td>
								<td><input class="inputtext" id="ip_address" name="ip_address" value="' . htmlspecialchars($filterIP) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="log_name">Name</label></td>
								<td><input class="inputtext" id="log_name" name="log_name" value="' . htmlspecialchars($filterName) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="date_after">From</label></td>
								<td><input class="inputtext" type="date" id="date_after" name="date_after" value="' . htmlspecialchars($filterDateAfter) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="date_before">To</label></td>
								<td><input class="inputtext" type="date" id="date_before" name="date_before" value="' . htmlspecialchars($filterDateBefore) . '"></td>
							</tr>
							<tr>
								<td class="postblock">Actions</td>
								<td> 
									<label><input type="checkbox" id="ban" name="ban" ' . htmlspecialchars($filterBan) . '>Bans</label>  
									<label><input type="checkbox" id="deletions" name="deleted" ' . htmlspecialchars($filterDelete) . '>Deletions</label>
								</td>
							</tr>
							<tr id="rolerow">
								<td class="postblock">Roles <br> <div class="selectlinktextjs" id="roleselectall">[<a>Select all</a>]</div></td>
								<td>
									<ul class="littlelist">
										<li><label><input name="role[]" type="checkbox" value="' . $none . '" ' . (in_array($none, $filterRole) ? 'checked' : '') . '>None</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $user . '" ' . (in_array($user, $filterRole) ? 'checked' : '') . '>User</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $janitor . '" ' . (in_array($janitor, $filterRole) ? 'checked' : '') . '>Janitor</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $moderator . '" ' . (in_array($moderator, $filterRole) ? 'checked' : '') . '>Moderator</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $admin . '" ' . (in_array($admin, $filterRole) ? 'checked' : '') . '>Admin</label></li>
									</ul>
								</td>
							</tr>
							<tr id="boardrow">
								<td class="postblock"><label for="filterboard">Boards</label><div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div></td>
								<td>
									<ul class="boardFilterList">
										' . $boardCheckboxHTML . '
									</ul>
								</td>
							</tr>
						</tbody>
					</table>
					<div class="buttonSection">
						<input type="submit" value="Filter">
						<input type="reset" value="Reset">
					</div>
				</div>
			</details>
		</form>
		';
	}	
	
	public function drawManagePostsFilterForm(&$dat, board $board, array $filters) {
		$filterIP = $filters['ip_address'];
		$filterName = $filters['post_name'];
		$filterTripcode = $filters['tripcode'];
		$filterCapcode = $filters['capcode'];
		$filterSubject = $filters['subject'];
		$filterComment = $filters['comment'];
		$filterBoard = $filters['board'];
		
		$boardIO = boardIO::getInstance();

		$allListedBoards = $boardIO->getAllListedBoards();

		$boardCheckboxHTML = $this->generateBoardListCheckBoxHTML($board, $filterBoard, $allListedBoards);
		$dat .= '
		<form class="detailsboxForm" action="' . $this->fullURL() . $this->config['PHP_SELF'].'" method="get">
			<details id="filtercontainer" class="detailsbox">
				<summary>Filter posts</summary>
				<div class="detailsboxContent">
					<input type="hidden" name="mode" value="managePosts">
					<input type="hidden" name="filterSubmissionFlag" value="true">

					<table id="adminPostFilterTable" class="centerBlock">
						<tbody>
							<tr>
								<td class="postblock"><label for="ip_address">IP address</label></td>
								<td><input class="inputtext" id="ip_address" name="ip_address" value="'.htmlspecialchars($filterIP).'"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="post_name">Name</label></td>
								<td><input class="inputtext" id="post_name" name="post_name" value="'.htmlspecialchars($filterName).'"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="tripcode">Tripcode</label></td>
								<td><input class="inputtext" id="tripcode" name="tripcode" value="'.htmlspecialchars($filterTripcode).'"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="capcode">Capcode</label></td>
								<td><input class="inputtext" id="capcode" name="capcode" value="'.htmlspecialchars($filterCapcode).'"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="subject">Subject</label></td>
								<td><input class="inputtext" id="subject" name="subject" value="'.htmlspecialchars($filterSubject).'"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="comment">Comment</label></td>
								<td><input class="inputtext" id="comment" name="comment" value="'.htmlspecialchars($filterComment).'"></td>
							</tr>
							<tr id="boardrow">
								<td class="postblock">
									<label for="filterboard">Boards</label>
									<div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div>
								</td>
								<td>
									<ul id="managePostsBoardFilterList" class="boardFilterList">
										'.$boardCheckboxHTML.'
									</ul>
								</td>
							</tr>
						</tbody>
					</table>
					<div class="buttonSection">
						<button type="submit" name="filterformsubmit" value="filter">Filter</button>
						<input type="reset" value="Reset">
					</div>
				</div>
			</details>
		</form>
		';
	}
	
	public function drawOverboardFilterForm(&$dat, $board) {
		$boardIO = boardIO::getInstance();
		
		$allListedBoards = $boardIO->getAllListedBoardUIDs();
		
		$filterBoard = json_decode($_COOKIE['overboard_filterboards'] ?? ''); if(!is_array($filterBoard)) $filterBoard = $allListedBoards;
		$boardCheckboxHTML = $this->generateBoardListCheckBoxHTML($board, $filterBoard, $boardIO->getBoardsFromUIDs($allListedBoards));
		$dat .= '
			<form class="detailsboxForm" id="overboardFilterForm" action="' . $this->fullURL() . $this->config['PHP_SELF'].'?mode=overboard" method="POST">
				<details id="filtercontainer" class="detailsbox">
					<summary>Filter boards</summary>
					<div class="detailsboxContent">
						<ul id="overboardFilterList" class="boardFilterList">
							'.$boardCheckboxHTML.'
						</ul>
						<div class="selectlinktextjs" id="overboardselectall">[<a>Select all</a>]</div>
						<div class="buttonSection">
							<button type="submit" name="filterformsubmit" value="filter">Filter</button> <input type="reset" value="Reset">
						</div>
					</div>
				</details>
			</form>
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
				if ($accountRoleLevel->value + 1 <= \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value) $actionHTML .= '[<a title="Promote account" href="' . $this->config['PHP_SELF'] . '?mode=handleAccountAction&up=' . $accountID. '">▲</a>]';
				if ($accountRoleLevel->value - 1 > \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value) $actionHTML .= '[<a title="Demote account" href="' . $this->config['PHP_SELF'] . '?mode=handleAccountAction&dem=' . $accountID . '">▼</a>]';
				
				

				$accountsHTML .= '<tr> 
						<td class="colAccountID">' . $accountID . '</td>
						<td class="colUsername">' . $accountUsername . ' </td>
						<td class="colRoleLevel">' . $accountRoleLevel->displayRoleName() . '</td>
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
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?page=0">Live frontend</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=rebuild">Rebuild board</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=managePosts">Manage posts</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=actionLog">Action log</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=account">Accounts</a></li>
				<li class="adminNavLink"><a href="'.$this->config['PHP_SELF'].'?mode=boards">Boards</a></li>
				';
		$this->moduleEngine->useModuleMethods('LinksAboveBar', array(&$linksAboveBar,'admin',$authRoleLevel));
		$linksAboveBar .= "</ul>";
		return $linksAboveBar;
	}
	
	private function validateAndClampPagination(int $entriesPerPage, int $totalEntries, int $currentPage): array {
		if ((filter_var($totalEntries, FILTER_VALIDATE_INT) === false || $totalEntries < 0) ||
			(filter_var($entriesPerPage, FILTER_VALIDATE_INT) === false || $entriesPerPage < 0)) {
			$this->error("Total entries must be a valid non-negative integer.");
		}
	
		$totalPages = (int) ceil($totalEntries / $entriesPerPage);
	
		// Validate current page number
		if (filter_var($currentPage, FILTER_VALIDATE_INT) === false) {
			$this->error("Invalid page number");
		}
	
		// Clamp the current page to be within the valid range
		$currentPage = max(0, min($totalPages - 1, $currentPage));
	
		return [$totalPages, $currentPage];
	}
	
	private function getBoardPageLink(int $page, bool $isStaticAll, string $liveFrontEnd, bool $isLiveFrontend): string {
		if ($isLiveFrontend) {
			return $liveFrontEnd . '?page=' . $page;
		}
	
		if ($isStaticAll || $page <= $this->config['STATIC_HTML_UNTIL'] - 1) {
			return ($page === 0) ? 'index.html' : $page . '.html';
		}
	
		return $liveFrontEnd . '?page=' . $page;
	}

	private function renderPager(int $currentPage, int $totalPages, callable $getLink, ?callable $getForm = null): string {
		$pageHTML = '<table id="pager"><tbody><tr>';

		// Previous
		if ($currentPage <= 0) {
			$pageHTML .= '<td id="pagerPreviousCell">First</td>';
		} else {
			$pageHTML .= '<td id="pagerPreviousCell">' . ($getForm ? $getForm($currentPage - 1, 'Previous') : '<form action="' . $getLink($currentPage - 1) . '" method="get"><button type="submit">Previous</button></form>') . '</td>';
		}

		// Page Numbers
		$pageHTML .= '<td id="pagerPagesCell"><div id="pagerPagesContainer">';
		for ($i = 0; $i < $totalPages; $i++) {
			if ($i == $currentPage) {
				$pageHTML .= "<span class=\"pagerPageLink\" id=\"pagerSelectedPage\">[$i]</span> ";
			} else {
				$pageHTML .= '<span class="pagerPageLink">[<a href="' . $getLink($i) . '">' . $i . '</a>]</span> ';
			}
		}
		$pageHTML .= '</div></td>';

		// Next
		if ($currentPage >= $totalPages - 1) {
			$pageHTML .= '<td id="pagerNextCell">Last</td>';
		} else {
			$pageHTML .= '<td id="pagerPreviousCell">' . ($getForm ? $getForm($currentPage + 1, 'Next') : '<form action="' . $getLink($currentPage + 1) . '" method="get"><button type="submit">Next</button></form>') . '</td>';
		}

		$pageHTML .= '</tr></tbody></table>';
		return $pageHTML;
	}

	public function drawBoardPager(int $entriesPerPage, int $totalEntries, string $url, int $currentPage): string {
		[$totalPages, $currentPage] = $this->validateAndClampPagination($entriesPerPage, $totalEntries, $currentPage);

		$staticUntil = $this->config['STATIC_HTML_UNTIL'];

		$isStaticAll = ($staticUntil === -1);

		$getLink = function($page) use ($url, $staticUntil, $isStaticAll) {
			if ($page === 0) {
				return $url . $this->config['PHP_SELF2'];
			}
			if (!$isStaticAll && $page >= $staticUntil) {
				// Fallback to dynamic link
				return $url . $this->config['PHP_SELF'] . '?page=' . $page;
			}
			return $page . '.html';
		};

		$getForm = function($page, $label) use ($url, $staticUntil, $isStaticAll) {
			if ($page === 0) {
				return '<a href="' . htmlspecialchars($url . $this->config['PHP_SELF2']) . '"><button type="button">' . htmlspecialchars($label) . '</button></a>';
			}
			if (!$isStaticAll && $page >= $staticUntil) {
				return '<form action="' . htmlspecialchars($url . $this->config['PHP_SELF']) . '" method="get">
					<input type="hidden" name="page" value="' . intval($page) . '">
					<button type="submit">' . htmlspecialchars($label) . '</button>
				</form>';
			}

			return '<a href="' . htmlspecialchars($page . '.html') . '"><button type="button">' . htmlspecialchars($label) . '</button></a>';
		};

		return $this->renderPager($currentPage, $totalPages, $getLink, $getForm);
	}


	public function drawLiveBoardPager(int $entriesPerPage, int $totalEntries, string $url): string {
		$currentPage = $_REQUEST['page'] ?? 0;

		[$totalPages, $currentPage] = $this->validateAndClampPagination($entriesPerPage, $totalEntries, $currentPage);

		$actionUrl = $url . $this->config['PHP_SELF'];
		$isStaticAll = ($this->config['STATIC_HTML_UNTIL'] == -1);
		$getLink = fn($page) => $this->getBoardPageLink($page, $isStaticAll, $actionUrl, true);

		$getForm = fn($page, $label) => '<form action="' . $actionUrl . '" method="get">
			<input type="hidden" name="page" value="' . $page . '">
			<button type="submit">' . $label . '</button>
		</form>';

		return $this->renderPager($currentPage, $totalPages, $getLink, $getForm);
	}

	public function drawPager(int $entriesPerPage, int $totalEntries, string $url): string {
		$currentPage = $_REQUEST['page'] ?? 0;

		[$totalPages, $currentPage] = $this->validateAndClampPagination($entriesPerPage, $totalEntries, $currentPage);

		$getLink = fn($page) => $url . '&page=' . $page;

		$getForm = function($page, $label) use ($url) {
			$params = $_GET;
			unset($params['page']);
			$params['page'] = $page;
		
			$inputs = '';
			foreach ($params as $key => $val) {
				if (is_array($val)) {
					foreach ($val as $subVal) {
						$inputs .= '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($subVal) . '">' . "\n";
					}
				} else {
					$inputs .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">' . "\n";
				}
			}
		
			return '<form action="' . htmlspecialchars($url) . '" method="get">' . $inputs . '
				<button type="submit">' . htmlspecialchars($label) . '</button>
			</form>';
		};		
		

		return $this->renderPager($currentPage, $totalPages, $getLink, $getForm);
	}
	
}		
