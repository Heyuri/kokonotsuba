<?php
//Handle GET mode values for koko
class modeHandler {
	private readonly array $config;
	private readonly board $board;
	private readonly globalHTML $globalHTML;
	private readonly overboard $overboard;
	private readonly pageRenderer $pageRenderer;
	private readonly pageRenderer $adminPageRenderer;
	private readonly mixed $FileIO;
	private readonly mixed $PIO;
	private readonly boardIO $boardIO;
	private readonly AccountIO $AccountIO;
	private readonly ActionLogger $actionLogger;
	private readonly softErrorHandler $softErrorHandler;
	private readonly staffAccountFromSession $staffSession;
	private readonly postValidator $postValidator;

	private moduleEngine $moduleEngine;
	private templateEngine $templateEngine;
	private templateEngine $adminTemplateEngine;
	
	public function __construct(board $board) {
		// Validate required directories before anything else
		if (!file_exists($board->getFullConfigPath())) {
			throw new \RuntimeException("Board's config file <i>" . $board->getFullConfigPath() . "</i> was not found.");
		}

		if (!file_exists($board->getBoardStoragePath())) {
			throw new \RuntimeException("Board's storage directory <i>" . $board->getBoardStoragePath() . "</i> does not exist.");
		}

		$this->board = $board;
		$this->config = $board->loadBoardConfig();

		// Global HTML helper
		$this->globalHTML = new globalHTML($board);

		// Module and Template Engines
		$this->moduleEngine = new moduleEngine($board);
		$this->templateEngine = $board->getBoardTemplateEngine();
		$this->overboard = new overboard($this->config, $this->moduleEngine, $this->templateEngine);

		// Admin Template Engine Setup
		$adminTemplateFile = getBackendDir() . 'templates/admin.tpl';
		$dependencies = [
			'config'	=> $this->config,
			'boardData'	=> [
				'title'		=> $board->getBoardTitle(),
				'subtitle'	=> $board->getBoardSubTitle()
			]
		];
		$this->adminTemplateEngine = new templateEngine($adminTemplateFile, $dependencies);

		// Page Renderers
		$this->adminPageRenderer = new pageRenderer($this->adminTemplateEngine, $this->globalHTML);
		$this->pageRenderer = new pageRenderer($this->templateEngine, $this->globalHTML);

		// soft error page handler
		$this->softErrorHandler = new softErrorHandler($board);

		// account from session
		$this->staffSession = new staffAccountFromSession;

		// post + ip validator
		$IPValidator = new IPValidator($this->config, new IPAddress);
		$this->postValidator = new postValidator($this->board, $this->config, $this->globalHTML, $IPValidator);
	
		// File I/O and Logging
		$this->boardIO = boardIO::getInstance();
		$this->FileIO = PMCLibrary::getFileIOInstance();
		$this->PIO = PIOPDO::getInstance();
		$this->AccountIO = AccountIO::getInstance();
		$this->actionLogger = ActionLogger::getInstance();
	}


	public function handle() {
		if ($this->config['GZIP_COMPRESS_LEVEL'] && ($Encoding = CheckSupportGZip())) {
			ob_start();
			ob_implicit_flush(0);
		}
	
		$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';
	
		$routes = [
			'regist'	=> function() {
				$route = new registRoute(
					$this->board,
					$this->config,
					$this->globalHTML,
					$this->postValidator,
					$this->staffSession,
					$this->moduleEngine,
					$this->actionLogger,
					$this->FileIO,
					$this->PIO
				);
				$route->registerPostToDatabase();
			},
			'admin'	=> function() {
				$route = new adminRoute(
					$this->board,
					$this->config,
					$this->globalHTML,
					$this->staffSession,
					$this->moduleEngine,
					$this->AccountIO
				);
				$route->drawAdminPage();
			},
			'status' => function() {
				$route = new statusRoute(
					$this->board,
					$this->config,
					$this->globalHTML,
					$this->staffSession,
					$this->templateEngine,
					$this->moduleEngine,
					$this->PIO,
					$this->FileIO
				);
				$route->drawStatus();
			},
			'module' => function() {
				$route = new moduleRoute($this->globalHTML, $this->moduleEngine);
				$route->handleModule();
			},
			'moduleloaded' => function() {
				$route = new moduleloadedRoute(
					$this->config, 
					$this->globalHTML, 
					$this->staffSession, 
					$this->moduleEngine
				);
				$route->listModules();
			},
			'account' => function() {
				$route = new accountRoute(
					$this->config,
					$this->staffSession,
					$this->globalHTML,
					$this->softErrorHandler,
					$this->AccountIO,
					$this->adminTemplateEngine,
					$this->adminPageRenderer
				);
				$route->drawAccountPage();
			},
			'boards' => function() {
				$route = new boardsRoute(
					$this->config, 
					$this->staffSession, 
					$this->softErrorHandler, 
					$this->globalHTML, 
					$this->adminTemplateEngine, 
					$this->adminPageRenderer, 
					$this->boardIO, 
					$this->board
				);
				$route->drawBoardPage();
			},
			'overboard' => function() {
				$route = new overboardRoute(
					$this->config, 
					$this->boardIO, 
					$this->board, 
					$this->overboard, 
					$this->globalHTML
				);
				$route->drawOverboard();
			},
			'handleAccountAction' => function() {
				$route = new handleAccountActionRoute(
					$this->config, 
					$this->board, 
					$this->softErrorHandler, 
					$this->staffSession
				);
				$route->handleAccountRequests();
			},
			'handleBoardRequests' => function() {
				$route = new handleBoardRequestsRoute(
					$this->config, 
					$this->softErrorHandler, 
					$this->boardIO, 
					$this->globalHTML
				);
				$route->handleBoardRequests();
			},
			'usrdel' => function() {
				$route = new usrdelRoute(
					$this->config,
					$this->board, 
					$this->staffSession, 
					$this->globalHTML, 
					$this->moduleEngine, 
					$this->actionLogger, 
					$this->PIO, 
					$this->FileIO
				);
				$route->userPostDeletion();
			},
			'rebuild' => function() {
				$route = new rebuildRoute(
					$this->config, 
					$this->board, 
					$this->softErrorHandler, 
					$this->actionLogger, 
					$this->globalHTML
				);
				$route->handleRebuild();
			}
		];
	
		if (isset($routes[$mode])) {
			$routes[$mode]();
		} else {
			$defaultRoute = new defaultRoute(
				$this->config, 
				$this->board, 
				$this->actionLogger, 
				$this->globalHTML
			);
			$defaultRoute->handleDefault();
		}
	
		if ($this->config['GZIP_COMPRESS_LEVEL'] && $Encoding) {
			$this->finalizeGzip($Encoding);
		}
	}	

	private function finalizeGzip($Encoding) {
		if (!ob_get_length()) exit; // No content, no need to compress
		header('Content-Encoding: ' . $Encoding);
		header('X-Content-Encoding-Level: ' . $this->config['GZIP_COMPRESS_LEVEL']);
		header('Vary: Accept-Encoding');
		print gzencode(ob_get_clean(), $this->config['GZIP_COMPRESS_LEVEL']); // Compressed content
	}
<<<<<<< HEAD
=======

	/* Write to post table */
	public function regist(){	
		$this->board->updateBoardPathCache();

		$chktime = '';
		$flgh = '';
		$ThreadExistsBefore = false;
		$fname = '';
		$ext = '';
		$dest = '';
		$tmpfile = '';
		$up_incomplete = 0; 
		$is_admin = false;
		
		/* get post data */
		$name = filter_var($_POST['name']??'', FILTER_SANITIZE_SPECIAL_CHARS);
		$email = filter_var($_POST['email']??'', FILTER_SANITIZE_SPECIAL_CHARS);
		$sub = filter_var($_POST['sub']??'', FILTER_SANITIZE_SPECIAL_CHARS);
		$com = filter_var($_POST['com']??'', FILTER_SANITIZE_SPECIAL_CHARS);
		$pwd = $_POST['pwd']??'';
		$category = filter_var($_POST['category']??'', FILTER_SANITIZE_SPECIAL_CHARS);
		$resno = intval($_POST['resto']??0);
		$thread_uid = $this->PIO->resolveThreadUidFromResno($this->board, $resno);
		$pwdc = $_COOKIE['pwdc']??'';

		$ip = new IPAddress; 
		$host = gethostbyaddr($ip);
		// Unix timestamp in seconds
		$time = $_SERVER['REQUEST_TIME'];
		// Unix timestamp in milliseconds
		$tim  = intval($_SERVER['REQUEST_TIME_FLOAT'] * 1000);
		$upfile = '';
		$upfile_path = '';
		$upfile_name = '';
		$upfile_status = 4;
		
		$roleLevel = $this->staffSession->getRoleLevel();
		
		$this->postValidator->spamValidate($name, $email, $sub, $com);
		/* hook call */
		$this->moduleEngine->useModuleMethods('RegistBegin', array(&$name, &$email, &$sub, &$com, array('file'=>&$upfile, 'path'=>&$upfile_path, 'name'=>&$upfile_name, 'status'=>&$upfile_status), array('ip'=>$ip, 'host'=>$host), $thread_uid)); // "RegistBegin" Hook Point
		if($this->config['TEXTBOARD_ONLY'] == false) {
				processFiles($this->board, $this->postValidator, $this->globalHTML, $upfile, $upfile_path, $upfile_name, $upfile_status, $md5chksum, $imgW, $imgH, $imgsize, $W, $H, $fname, $ext, $age, $status, $thread_uid, $tim, $dest, $tmpfile);
		}
		
		// Check the form field contents and trim them
		if(strlenUnicode($name) > $this->config['INPUT_MAX'])	$this->globalHTML->error(_T('regist_nametoolong'), $dest);
		if(strlenUnicode($email) > $this->config['INPUT_MAX'])	$this->globalHTML->error(_T('regist_emailtoolong'), $dest);
		if(strlenUnicode($sub) > $this->config['INPUT_MAX'])	$this->globalHTML->error(_T('regist_topictoolong'), $dest);

		setrawcookie('namec', rawurlencode(htmlspecialchars_decode($name)), time()+7*24*3600);
		
		// E-mail / Title trimming
		$email = str_replace("\r\n", '', $email); 
		$sub = str_replace("\r\n", '', $sub);
	
		applyTripcodeAndCapCodes($this->config, $this->globalHTML, $this->staffSession, $name, $email, $dest);
		$this->postValidator->cleanComment($com, $upfile_status, $is_admin, $dest);
		addDefaultText($this->config, $sub, $com);
		applyPostFilters($this->config, $this->globalHTML, $com, $email);
	
		// Trimming label style
		if($category && $this->config['USE_CATEGORY']){
				$category = explode(',', $category); // Disassemble the labels into an array
				$category = ','.implode(',', array_map('trim', $category)).','; // Remove the white space and merge into a single string (left and right, you can directly search in the form XX)
		}else{ 
				$category = ''; 
		}
		
		if($up_incomplete){
				$com .= '<p class="incompleteFile"><span class="warning">'._T('notice_incompletefile').'</span></p>'; // Tips for uploading incomplete additional image files
		}
	
		// Password and time style
		if($pwd==''){
				$pwd = ($pwdc=='') ? substr(rand(),0,8) : $pwdc;
		}
	
		$pass = $pwd ? substr(md5($pwd), 2, 8) : '*'; // Generate a password for true storage judgment (the 8 characters at the bottom right of the imageboard where it says Password ******** SUBMIT for deleting posts)
		$now = generatePostDay($this->config, $time);
		$now .= generatePostID($roleLevel, $this->config, $email,$now, $time, $thread_uid, $this->PIO);

		$this->postValidator->validateForDatabase($pwdc, $com, $time, $pass, $ip,  $upfile, $md5chksum, $dest, $this->PIO, $roleLevel);
		if($thread_uid){
				$ThreadExistsBefore = $this->PIO->isThread($thread_uid);
		}
	
		$this->postValidator->pruneOld($this->moduleEngine, $this->PIO, $this->FileIO);
		$this->postValidator->threadSanityCheck($chktime, $flgh, $thread_uid, $this->PIO, $dest, $ThreadExistsBefore);
	
		// Calculate the last feilds needed before putitng in db
		$no = $this->board->getLastPostNoFromBoard() + 1;
		if(!isset($ext)) $ext = '';
		if(!isset($imgW)) $imgW = 0;
		if(!isset($imgH)) $imgH = 0;
		if(!isset($imgsize)) $imgsize = '';
		if(!isset($W)) $W = 0;
		if(!isset($H)) $H = 0;
		if(!isset($md5chksum)) $md5chksum = '';
		$age = false;
		$status = '';
		applyAging($this->config, $thread_uid, $this->PIO, $time, $chktime, $email, $name, $age);
	
		// noko
		$redirect = $this->config['PHP_SELF2'].'?'.$tim;
		if (strstr($email, 'noko') && !strstr($email, 'nonoko')) {
				$redirect = $this->config['PHP_SELF'].'?res='.($resno?$resno:$no);
				if (!strstr($email, 'dump')){
						$redirect.= "#p".$this->board->getBoardUID()."_$no";
				}
		}
		$email = preg_replace('/^(no)+ko\d*$/i', '', $email);
	
		// Get number of pages to rebuild
		$threads = $this->PIO->getThreadListFromBoard($this->board);
		$threads_count = count($threads);
		$page_end = ($thread_uid ? floor(array_search($thread_uid, $threads) / $this->config['PAGE_DEF']) : ceil($threads_count / $this->config['PAGE_DEF']));
		$this->moduleEngine->useModuleMethods('RegistBeforeCommit', array(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $thread_uid, array($W, $H, $imgW, $imgH, $tim, $ext), &$status)); // "RegistBeforeCommit" Hook Point
		$this->PIO->addPost($this->board, $no, $thread_uid, $md5chksum, $category, $tim, $fname, $ext, $imgW, $imgH, $imgsize, $W, $H, $pass, $now, $name, $email, $sub, $com, $ip, $age, $status);
		
		$this->actionLogger->logAction("Post No.$no registered", $this->board->getBoardUID());
		// Formal writing to storage
		$lastno = $this->board->getLastPostNoFromBoard() - 1; // Get this new article number
		$this->moduleEngine->useModuleMethods('RegistAfterCommit', array($lastno, $thread_uid, $name, $email, $sub, $com)); // "RegistAfterCommit" Hook Point
	
		// Cookies storage: password and e-mail part, for one week
		setcookie('pwdc', $pwd, time()+7*24*3600);
		setcookie('emailc', htmlspecialchars_decode($email), time()+7*24*3600);
		makeThumbnailAndUpdateStats($this->board, $this->config, $this->FileIO, $dest, $ext, $tim, $tmpfile ,$imgW, $imgH, $W, $H);
		runWebhooks($this->board, $resno, $no, $sub);
	
	
		$this->board->rebuildBoard(0, -1, false, $page_end);
		redirect($redirect, 0);
	}


	private function drawAdminList() {
		if(isset($_POST['username']) && isset($_POST['password'])) adminLogin($this->AccountIO, $this->globalHTML);

		$recentStaffAccountFromSession = new staffAccountFromSession;

		$currentRoleLevel = $recentStaffAccountFromSession->getRoleLevel(); // get the newly set role level if login was successful
		$adminPageHandler = new adminPageHandler($this->board, $this->moduleEngine); // router for some admin pages, mostly legacy
		$admin = $_REQUEST['admin']??'';
		$dat = '';
		$this->globalHTML->head($dat);
		$links = $this->globalHTML->generateAdminLinkButtons();
		
		$dat .= $links; //hook above bar links
		
		$this->globalHTML->drawAdminTheading($dat, $this->staffSession);
		
		$dat.= '<div id="adminOptionContainer" class="centerText"><form action="'.$this->config['PHP_SELF'].'" method="POST" name="adminform">';
		$admins = array(
			array('name'=>'del', 'level'=>$this->config['roles']['LEV_JANITOR'], 'label'=>'Manage posts', 'func'=>'admindel'),
			array('name'=>'action', 'level'=>$this->config['roles']['LEV_ADMIN'], 'label'=>'Action log', 'func'=>'actionlog'),
			array('name'=>'logout', 'level'=>$this->config['roles']['LEV_USER'], 'label'=>'Logout', 'func'=>'adminLogout'),
		);

		foreach ($admins as $adminmode) {
			if ($currentRoleLevel==$this->config['roles']['LEV_NONE'] && $adminmode['name']=='logout') continue;
			$checked = ($admin==$adminmode['name']) ? ' checked="checked"' : '';
			$dat.= '<label><input type="radio" name="admin" value="'.$adminmode['name'].'"'.$checked.'>'.$adminmode['label'].'</label> ';
		}
		if ($currentRoleLevel==$this->config['roles']['LEV_NONE']) {
			$dat.= $this->globalHTML->drawAdminLoginForm()."</form>";
		} else {
			$dat.= '<button type="submit" name="mode" value="admin">Submit</button></form>';
		}
		$find = false;
		
		$dat.= '</div><hr>';

		foreach ($admins as $adminmode) {
			if ($admin!=$adminmode['name']) continue;
			$find = true;
			if ($adminmode['level']>$currentRoleLevel) {
				$dat.= '<div class="centerText"><span class="error">ERROR: Access denied.</span></div><hr>';
				break;
			}
			if ($adminmode['func']) {
				$adminPageHandler->handleAdminPageSelection($adminmode['func'], $dat);
			}
		}

		$this->globalHTML->foot($dat);
		die($dat.'</body></html>');
	}

	/* Show instance/board information */
	private function showstatus() {
		$countline = $this->PIO->postCountFromBoard($this->board); // Calculate the current number of data entries in the submitted text log file
		$counttree = $this->PIO->threadCountFromBoard($this->board); // Calculate the current number of data entries in the tree structure log file
		$tmp_total_size = $this->FileIO->getCurrentStorageSize($this->board); // The total size of the attached image file usage
		$tmp_ts_ratio = $this->config['STORAGE_MAX'] > 0 ? $tmp_total_size / $this->config['STORAGE_MAX'] : 0; // Additional image file usage

		// Determines the color of the "Additional Image File Usage" prompt
		if ($tmp_ts_ratio < 0.3 ) $clrflag_sl = '235CFF';
		elseif ($tmp_ts_ratio < 0.5 ) $clrflag_sl = '0CCE0C';
		elseif ($tmp_ts_ratio < 0.7 ) $clrflag_sl = 'F28612';
		elseif ($tmp_ts_ratio < 0.9 ) $clrflag_sl = 'F200D3';
		else $clrflag_sl = 'F2004A';

		// Generate preview image object information and whether the functions of the generated preview image are normal
		$func_thumbWork = '<span class="offline">'._T('info_nonfunctional').'</span>';
		$func_thumbInfo = '(No thumbnail)';
		if ($this->config['USE_THUMB'] !== 0) {
				$thumbType = $this->config['USE_THUMB']; if ($this->config['USE_THUMB'] == 1) { $thumbType = 'gd'; }
				require(getBackendDir() . 'lib/thumb/thumb.' . $thumbType . '.php');
				$thObj = new ThumbWrapper();
				if ($thObj->isWorking()) $func_thumbWork = '<span class="online">'._T('info_functional').'</span>';
				$func_thumbInfo = $thObj->getClass();
				unset($thObj);
		}

		// PIOSensor
		if (count($this->config['LIMIT_SENSOR']))
				$PIOsensorInfo = nl2br(PIOSensor::info($this->board, $this->config['LIMIT_SENSOR']));

		$dat = '';
		$this->globalHTML->head($dat);
		$links = '[<a href="' . $this->config['PHP_SELF2'] . '?' . time() . '">' . _T('return') . '</a>] [<a href="' . $this->config['PHP_SELF'] . '?mode=moduleloaded">' . _T('module_info_top') . '</a>]';
		$level = $this->staffSession->getRoleLevel();
		$this->moduleEngine->useModuleMethods('LinksAboveBar', array(&$links, 'status', $level));
		$dat .= $links . '<h2 class="theading2">' . _T('info_top') . '</h2>
<table id="status" class="postlists">
	<thead>
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_basic') . '</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td width="240">' . _T('info_basic_threadsperpage') . '</td>
			<td colspan="3"> ' . $this->config['PAGE_DEF'] . ' ' . _T('info_basic_threads') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_postsperpage') . '</td>
			<td colspan="3"> ' . $this->config['RE_DEF'] . ' ' . _T('info_basic_posts') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_postsinthread') . '</td>
			<td colspan="3"> ' . $this->config['RE_PAGE_DEF'] . ' ' . _T('info_basic_posts') . ' ' . _T('info_basic_posts_showall') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_bumpposts') . '</td>
			<td colspan="3"> ' . $this->config['MAX_RES'] . ' ' . _T('info_basic_posts') . ' ' . _T('info_basic_0disable') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_bumphours') . '</td>
			<td colspan="3"> ' . $this->config['MAX_AGE_TIME'] . ' ' . _T('info_basic_hours') . ' ' . _T('info_basic_0disable') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_urllinking') . '</td>
			<td colspan="3"> ' . $this->config['AUTO_LINK'] . ' ' . _T('info_0no1yes') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_com_limit') . '</td>
			<td colspan="3"> ' . $this->config['COMM_MAX'] . _T('info_basic_com_after') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_anonpost') . '</td>
			<td colspan="3"> ' . $this->config['ALLOW_NONAME'] . ' ' . _T('info_basic_anonpost_opt') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_del_incomplete') . '</td>
			<td colspan="3"> ' . $this->config['KILL_INCOMPLETE_UPLOAD'] . ' ' . _T('info_0no1yes') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_use_sample', $this->config['THUMB_SETTING']['Quality']) . '</td>
			<td colspan="3"> ' . $this->config['USE_THUMB'] . ' ' . _T('info_0notuse1use') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_useblock') . '</td>
			<td colspan="3"> ' . $this->config['BAN_CHECK'] . ' ' . _T('info_0disable1enable') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_showid') . '</td>
			<td colspan="3"> ' . $this->config['DISP_ID'] . ' ' . _T('info_basic_showid_after') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_cr_limit') . '</td>
			<td colspan="3"> ' . $this->config['BR_CHECK'] . _T('info_basic_cr_after') . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_timezone') . '</td>
			<td colspan="3"> GMT ' . $this->config['TIME_ZONE'] . '</td>
		</tr>
		<tr>
			<td>' . _T('info_basic_theme') . '</td>
			<td colspan="3"> ' . $this->templateEngine->BlockValue('THEMENAME') . ' ' . $this->templateEngine->BlockValue('THEMEVER') . '<div>by ' . $this->templateEngine->BlockValue('THEMEAUTHOR') . '</div></td>
		</tr>
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_dsusage_top') . '</th>
		</tr>
		<tr class="centerText">
			<td>' . _T('info_basic_threadcount') . '</td>
			<td colspan="' . (isset($this->PIOsensorInfo) ? '2' : '3') . '"> ' . $counttree . ' ' . _T('info_basic_threads') . '</td>' . (isset($this->PIOsensorInfo) ? '
			<td rowspan="2">' . $PIOsensorInfo . '</td>' : '') . '
		</tr>
		<tr class="centerText">
			<td>' . _T('info_dsusage_count') . '</td>
			<td colspan="' . (isset($this->PIOsensorInfo) ? '2' : '3') . '">' . $countline . '</td>
		</tr>
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_fileusage_top') . $this->config['STORAGE_LIMIT'] . ' ' . _T('info_0disable1enable') . '</th>
		</tr>';

		if ($this->config['STORAGE_LIMIT']) {
				$dat .= '
		<tr class="centerText">
			<td>' . _T('info_fileusage_limit') . '</td>
			<td colspan="2">' . $this->config['STORAGE_MAX'] . ' KB</td>
			<td rowspan="2">' . _T('info_dsusage_usage') . '<div><span style="color:#' . $clrflag_sl . '">' . substr(($tmp_ts_ratio * 100), 0, 6) . '</span> %</div></td>
		</tr>
		<tr class="centerText">
			<td>' . _T('info_fileusage_count') . '</td>
			<td colspan="2"><span style="color:#' . $clrflag_sl . '">' . $tmp_total_size . ' KB</span></td>
		</tr>';
		} else {
				$dat .= '
		<tr class="centerText">
			<td>' . _T('info_fileusage_count') . '</td>
			<td>' . $tmp_total_size . ' KB</td>
			<td colspan="2">' . _T('info_dsusage_usage') . '<br><span class="green">' . _T('info_fileusage_unlimited') . '</span></td>
		</tr>';
		}

		$dat .= '
		<tr>
			<th class="theadLike" colspan="4">' . _T('info_server_top') . '</th>
		</tr>
		<tr class="centerText">
			<td colspan="3">' . $func_thumbInfo . '</td>
			<td>' . $func_thumbWork . '</td>
		</tr>
	</tbody>
</table>
<hr>';

		$this->globalHTML->foot($dat);
		echo $dat;
	}


	/* Displays loaded module information */
	private function listModules() {
		$dat = '';
		
		$this->globalHTML->head($dat);

		$roleLevel = $this->staffSession->getRoleLevel();
		$links = '[<a href="' . $this->config['PHP_SELF2'] . '?' . time() . '">' . _T('return') . '</a>]';
		$this->moduleEngine->useModuleMethods('LinksAboveBar', array(&$links, 'modules', $roleLevel));

		$dat .= $links.'<h2 class="theading2">'._T('module_info_top').'</h2>
</div>

<div id="modules">
';

		/* Module Loaded */
		$dat .= _T('module_loaded') . '<ul>';
		foreach ($this->moduleEngine->getLoadedModules() as $m) {
				$dat .= '<li>' . $m . "</li>\n";
		}
		$dat .= "</ul><hr>\n";

		/* Module Information */
		$dat .= _T('module_info') . '<ul>';
		foreach ($this->moduleEngine->moduleInstance as $m) {
				$dat .= '<li>' . $m->getModuleName() . '<div>' . $m->getModuleVersionInfo() . "</div></li>\n";
		}
		$dat .= '</ul><hr>
		</div>
		';
		$this->globalHTML->foot($dat);
		echo $dat;
	}

	private function drawAccountScreen() {
		$authRoleLevel = $this->staffSession->getRoleLevel();
		$authUsername = htmlspecialchars($this->staffSession->getUsername());
		
		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_USER']);
		
		$accountTableList = ($authRoleLevel == $this->config['roles']['LEV_ADMIN']) ? $this->globalHTML->drawAccountTable() : ''; # == is for PHP7 compatibility, change to === in future for PHP8
		
		$currentAccount = $this->AccountIO->getAccountByID($this->staffSession->getUID());
		$accountTemplateValues = [
			'{$ACCOUNT_ID}' => htmlspecialchars($this->staffSession->getUID()),
			'{$ACCOUNT_NAME}' => htmlspecialchars($authUsername),
			'{$ACCOUNT_ROLE}' => htmlspecialchars($this->globalHTML->roleNumberToRoleName($authRoleLevel)),
			'{$ACCOUNT_ACTIONS}' => htmlspecialchars($currentAccount->getNumberOfActions()),
		];	
		
		$accountTemplateRoles = [
			'{$USER}' => $this->config['roles']['LEV_USER'],
			'{$JANITOR}' => $this->config['roles']['LEV_JANITOR'],
			'{$MODERATOR}' => $this->config['roles']['LEV_MODERATOR'],
			'{$ADMIN}' => $this->config['roles']['LEV_ADMIN'],
		];

		$template_values = [
			'{$ACCOUNT_LIST}' => "$accountTableList",
			'{$CREATE_ACCOUNT}' => ($authRoleLevel == $this->config['roles']['LEV_ADMIN']) ? $this->adminTemplateEngine->ParseBlock('CREATE_ACCOUNT', $accountTemplateRoles) : '', # == is for PHP7 compatibility, change to === in future for PHP8
			'{$VIEW_OWN_ACCOUNT}' => $this->adminTemplateEngine->ParseBlock('VIEW_ACCOUNT', $accountTemplateValues),
		];
		
		$accountPageHtml = $this->adminPageRenderer->ParseBlock('ACCOUNT_PAGE', $template_values);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $accountPageHtml], true);
	}

	private function drawBoardScreen() {
		$authRoleLevel = $this->staffSession->getRoleLevel();
		
		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_ADMIN']);
		
		
		
		$boardTableList = $this->globalHTML->drawBoardTable();
		$template_values = [
			'{$BOARD_LIST}' => $boardTableList,
			'{$CREATE_BOARD}' => ($authRoleLevel == $this->config['roles']['LEV_ADMIN']) ? $this->adminTemplateEngine->ParseBlock('CREATE_BOARD', # == is for PHP7 compatibility, change to === in future for PHP8
				 [
					'{$DEFAULT_CDN_DIR}' => $this->config['CDN_DIR'], 
					'{$DEFAULT_CDN_URL}' => $this->config['CDN_URL'], 
					'{$DEFAULT_ROOT_URL}' => $this->board->getBoardRootURL(),
					'{$DEFAULT_PATH}' => dirname(getcwd()).DIRECTORY_SEPARATOR
				]) : '',
		];
		
		//view board
		if(isset($_GET['view'])) {
			$id =  $_GET['view'] ?? null;
			if(!$id) throw new Exception("Board UID from GET was not set or invalid.".__CLASS__.' '.__LINE__);
			
			$board = $this->boardIO->getBoardByUID($id);

			$boardUID = $board->getBoardUID() ?? '';
			$boardIdentifier = $board->getBoardIdentifier() ?? '';
			$boardTitle = $board->getBoardTitle() ?? '';
			$boardSubtitle = $board->getBoardSubTitle() ?? '';
			$boardURL = $board->getBoardURL() ?? '';
			$boardListed = $board->getBoardListed() ?? '';
			$boardConfig = $board->getConfigFileName() ?? '';
			$boardStorageDirectoryName = $board->getBoardStorageDirName() ?? '';
			$boardDate = $board->getDateAdded() ?? '';

			$template_values['{$BOARD_UID}'] = $boardUID;
			$template_values['{$BOARD_IDENTIFIER}'] = $boardIdentifier;
			$template_values['{$BOARD_TITLE}'] = $boardTitle;
			$template_values['{$BOARD_SUB_TITLE}'] = $boardSubtitle;
			$template_values['{$BOARD_URL}'] = $boardURL;
			$template_values['{$BOARD_IS_LISTED}'] = $boardListed ? 'True' : 'False';
			
			$template_values['{$BOARD_DATE_ADDED}'] = $boardDate;
			$template_values['{$BOARD_CONFIG_FILE}'] = $boardConfig;
			$template_values['{$CHECKED}'] = $boardListed ? 'checked' : '';
			$template_values['{$BOARD_STORAGE_DIR}'] = $boardStorageDirectoryName;
			$template_values['{$EDIT_BOARD_HTML}'] = $this->adminTemplateEngine->ParseBlock('EDIT_BOARD', $template_values);

			$viewBoardHtml = $this->adminPageRenderer->ParseBlock('VIEW_BOARD', $template_values);
			
			echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $viewBoardHtml], true);
			return;
		}	
		
		$boardPageHtml = $this->adminPageRenderer->ParseBlock('BOARD_PAGE', $template_values);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $boardPageHtml], true);
	}

	public function handleAccountRequests() {
		$accountRequestHandler = new accountRequestHandler($this->board);

		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_USER']);

		if($this->staffSession->getRoleLevel() == $this->config['roles']['LEV_ADMIN']) { # == is for PHP7 compatibility, change to === in future for PHP8
			if(isset($_GET['del'])) $accountRequestHandler->handleAccountDelete();
			if(isset($_GET['dem'])) $accountRequestHandler->handleAccountDemote();
			if(isset($_GET['up'])) $accountRequestHandler->handleAccountPromote();
			if(!empty($_POST['usrname']) && !empty($_POST['passwd'])) $accountRequestHandler->handleAccountCreation($this->board);
		}
		//password reset
		if(!empty($_POST['new_account_password'] ?? '')) $accountRequestHandler->handleAccountPasswordReset($this->board);
		
		redirect($this->config['PHP_SELF'].'?mode=account');
	}

	public function handleBoardRequests() {
		$boardPathCachingIO = boardPathCachingIO::getInstance();
		
		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_ADMIN']);

		if (!empty($_POST['edit-board'])) {
			try {
				// Retrieve and validate the board ID from POST
				$modifiedBoardIdFromPOST = intval($_POST['edit-board-uid']) ?? ''; 
				if (!$modifiedBoardIdFromPOST) {
					throw new Exception("Board UID in board editing cannot be NULL!");
				}
		
				// Get the board object using the board ID
				$modifiedBoard = $this->boardIO->getBoardByUID($modifiedBoardIdFromPOST);
		
				// Check if the action is to delete the board
				if (isset($_POST['board-action-submit']) && $_POST['board-action-submit'] === 'delete-board') {
					$this->boardIO->deleteBoardByUID($modifiedBoard->getBoardUID());
					redirect($this->config['PHP_SELF'] . '?mode=boards');
				}
		
				// Prepare fields for editing the board
				$fields = [
					'board_identifier' => $_POST['edit-board-identifier'] ?? false,
					'board_title' => $_POST['edit-board-title'] ?? false,
					'board_sub_title' => $_POST['edit-board-sub-title'] ?? false,
					'config_name' => $_POST['edit-board-config-path'] ?? false,
					'storage_directory_name' => $_POST['edit-board-storage-dir'] ?? false,
					'listed' => $_POST['edit-board-listed'] ?? false
				];
		
				// Validate the config file and storage directory exists
				if (!file_exists(getBoardConfigDir() . $fields['config_name'])) $this->globalHTML->error("Invalid config file, doesn't exist.");
				if (!file_exists(getBoardStoragesDir() . $fields['storage_directory_name'])) $this->globalHTML->error("Invalid storage directory, doesn't exist.");
				// Edit the board values in the database
				$this->boardIO->editBoardValues($modifiedBoard, $fields);
			} catch (Exception $e) {
				// Handle any exceptions that occur
				http_response_code(500);
				echo "Error: " . $e->getMessage();
			}
		
			// Redirect after the operation is complete
			$boardRedirectUID = $_POST['edit-board-uid-for-redirect'] ?? '';
			redirect($this->config['PHP_SELF'] . '?mode=boards&view=' . $boardRedirectUID);
		}
		
		if (!empty($_POST['new-board'])) {
			// Fetch and validate input
			$boardTitle = $_POST['new-board-title'] ?? $this->globalHTML->error("Board title wasn't set!");
			$boardSubTitle = $_POST['new-board-sub-title'] ?? '';
			$boardIdentifier = $_POST['new-board-identifier'] ?? '';
			$boardListed = isset($_POST['new-board-listed']) ? 1 : 0;
			$boardPath = $_POST['new-board-path'] ?? $this->globalHTML->error("Board path wasn't set!");

			$fullBoardPath = $boardPath . $boardIdentifier.'/';
			$mockConfig = getTemplateConfigArray();
			$backendDirectory = getBackendDir();
			$cdnDir = $this->config['CDN_DIR'] . $boardIdentifier.'/';
		
			$createdPaths = [];
		
			try {
				// Create required directories
				$createdPaths[] = createDirectoryWithErrorHandle($fullBoardPath, $this->globalHTML);
		
				$imgDir = $this->config['USE_CDN'] ? $cdnDir . $mockConfig['IMG_DIR'] : $fullBoardPath . $mockConfig['IMG_DIR'];
				$thumbDir = $this->config['USE_CDN'] ? $cdnDir . $mockConfig['THUMB_DIR'] : $fullBoardPath . $mockConfig['THUMB_DIR'];
				$createdPaths[] = createDirectoryWithErrorHandle($imgDir, $this->globalHTML);
				$createdPaths[] = createDirectoryWithErrorHandle($thumbDir, $this->globalHTML);
		
				// Create required files
				$requireString = "\"$backendDirectory{$this->config['PHP_SELF']}\"";
				createFileAndWriteText($fullBoardPath, $mockConfig['PHP_SELF'], "<?php require_once {$requireString}; ?>");
		
				// Create storage directory for the new board
				$boardStorageDirectoryName = 'storage-'.$this->boardIO->getNextBoardUID();
				$dataDir = getBoardStoragesDir().$boardStorageDirectoryName;
				$createdPaths[] = createDirectoryWithErrorHandle($dataDir, $this->globalHTML);

				// Generate and save board configuration
				$boardConfigName = generateNewBoardConfigFile();
				$this->boardIO->addNewBoard($boardIdentifier, $boardTitle, $boardSubTitle, $boardListed, $boardConfigName, $boardStorageDirectoryName);

				// Get board UID and save to configuration file
				$newBoardUID = $this->boardIO->getLastBoardUID();
				createFileAndWriteText($fullBoardPath, 'boardUID.ini', "board_uid = $newBoardUID");

				// Cache board path
				$boardPathCachingIO->addNewCachedBoardPath($newBoardUID, $fullBoardPath);
			} catch (Exception $e) {
				// Rollback created paths in case of an error
				rollbackCreatedPaths($createdPaths);
				$this->globalHTML->error($e->getMessage());
			}
		}
		redirect($this->config['PHP_SELF'].'?mode=boards');
		
	}

	/* User post deletion */
	private function usrdel() {	
		$pwd = $_POST['pwd'] ?? '';
		$pwdc = $_COOKIE['pwdc'] ?? '';
		$onlyimgdel = $_POST['onlyimgdel'] ?? '';
		$delno = array();
		reset($_POST);
		foreach ($_POST as $key => $val) {
			if ($val === 'delete') {
				array_push($delno, $key);
			}
		}
	
		$haveperm = $this->staffSession->getRoleLevel() >= $this->config['roles']['LEV_JANITOR'];
		$this->moduleEngine->useModuleMethods('Authenticate', array($pwd, 'userdel', &$haveperm));
	
		if ($pwd == '' && $pwdc != '') $pwd = $pwdc;
		$pwd_md5 = substr(md5($pwd), 2, 8);
		$host = gethostbyaddr(new IPAddress);
		$search_flag = false;
		$delPosts = [];
		$delPostUIDs = [];
		$files = [];

		if (!count($delno)) $this->globalHTML->error(_T('del_notchecked'));
	
		$posts = $this->PIO->fetchPosts($delno);
		
		foreach ($posts as $post) {
			if ($pwd_md5 == $post['pwd'] || $host == $post['host'] || $haveperm) {
				$search_flag = true;
				array_push($delPostUIDs, intval($post['post_uid']));
				array_push($delPosts, $post);
				$this->actionLogger->logAction("Delete post No." . $post['no'] . ($onlyimgdel ? ' (file only)' : ''), $this->board->getBoardUID());
			}
		}
	
		if ($search_flag) {
			if (!$onlyimgdel) $this->moduleEngine->useModuleMethods('PostOnDeletion', array($delPosts, 'frontend'));
			$files = createBoardStoredFilesFromArray($delPosts);
			$onlyimgdel ? $this->PIO->removeAttachments($delPostUIDs) : $this->PIO->removePosts($delPostUIDs);
			
			$this->FileIO->deleteImagesByBoardFiles($files);
		} else {
			$this->globalHTML->error(_T('del_wrongpwornotfound'));
		}

		$this->board->rebuildBoard();
		if (isset($_POST['func']) && $_POST['func'] == 'delete') {
			if (isset($_SERVER['HTTP_REFERER'])) {
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: ' . $_SERVER['HTTP_REFERER']);
			}
			exit();
		} else {
			redirect($this->config['PHP_SELF']);
			exit();
		}
	}
	
	private function drawOverboard() {
		$filterAction = $_POST['filterformsubmit'] ?? null;
		
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filter') {
			$filterBoardFromPOST = $_POST['filterboard'] ?? '';
			$filterBoard = (is_array($filterBoardFromPOST) ? array_map('htmlspecialchars', $filterBoardFromPOST) : [htmlspecialchars($filterBoardFromPOST)]);
			
			setcookie('overboard_filterboards', serialize($filterBoard), time() + (86400 * 30), "/");

			redirect($this->config['PHP_SELF'].'?mode=overboard');
			exit;
		} else if($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filterclear') {
			setcookie('overboard_filterboards', "", time() - 3600, "/");

			redirect($this->config['PHP_SELF'].'?mode=overboard');
			exit;
		}
		$filtersBoards = (!empty($_COOKIE['overboard_filterboards'])) ? unserialize($_COOKIE['overboard_filterboards']) : null;

		//filter list for the database
		$filters = [
			'board' => $filtersBoards ?? ($this->boardIO->getAllListedBoardUIDs()),
		];


		$html = '';

		$this->overboard->drawOverboardHead($html, 0);
		$this->globalHTML->drawOverboardFilterForm($html, $this->board);
		$html .= $this->overboard->drawOverboardThreads($filters, $this->globalHTML);	
		
		$this->globalHTML->foot($html, 0);
		echo $html;
	}
>>>>>>> 2d870d11750d6b1ae72f2e09bf16652cb8dc90e8
	
}
