<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ .'/classes/board.php';
require_once __DIR__ .'/classes/thread.php';
require_once __DIR__ .'/classes/post.php';
require_once __DIR__ .'/classes/file.php';

require_once __DIR__ .'/classes/hook.php';
require_once __DIR__ .'/classes/auth.php';
require_once __DIR__ .'/classes/fileHandler.php';
require_once __DIR__ .'/classes/html.php';

require_once __DIR__ .'/classes/repos/repoBoard.php';
require_once __DIR__ .'/classes/repos/repoThread.php';
require_once __DIR__ .'/classes/repos/repoPost.php';
//require_once __DIR__ .'/classes/repos/repoFile.php';

require_once __DIR__ .'/common.php';

$AUTH = AuthClass::getInstance();
$HOOK = HookClass::getInstance();
$POSTREPO = PostRepoClass::getInstance();
$THREADREPO = ThreadRepoClass::getInstance();
$BOARDREPO = BoardRepoClass::getInstance();

$globalConf = require __DIR__ ."/conf.php";

//@session_start();

function genUserPostFromRequest($conf, $thread){
	global $AUTH;
	global $HOOK;
	global $globalConf;

	/*
	 * below is the exact same thing as this
	 * 
	 * $name;
	 * if($_POST['name']) == true){
	 *     $name = $_POST['name'];
	 * }elseif ($conf['allowNoName'] == true){
	 *     $name = $conf['defaultName'];
	 * }else{
	 *     displayErrorPageAndDie("name is a required feild");
	 * }
	 */
	$name = isset($_POST['name']) ? $_POST['name'] : ($conf['allowNoName'] ? $conf['defaultName'] : displayErrorPageAndDie("your Name is required."));
	$email = isset($_POST['email']) ? $_POST['email'] : ($conf['allowNoEmail'] ? $conf['defaultEmail'] : displayErrorPageAndDie("your Email is required."));
	$subject = isset($_POST['subject']) ? $_POST['subject'] : ($conf['allowNoSubject'] ? $conf['defaultSubject'] : displayErrorPageAndDie("a Subject is required."));
	$comment = isset($_POST['comment']) ? $_POST['comment'] : ($conf['allowNoComment'] ? $conf['defaultComment'] : displayErrorPageAndDie("a comment is required."));;
	
	$password = isset($_POST['password']) ? $_POST['password'] : (isset($_COOKIE['password']) ? $_COOKIE["password"] : null);
	//gen post password if none is provided
	if($password == null){
		$hasinput = $_SERVER['REMOTE_ADDR'] . time() . $globalConf['passwordSalt'];
		$hash = hash('sha256', $hasinput);
		$password = substr($hash, -8); 
	}

	setrawcookie('password', $_POST['password'], $conf['cookieExpireTime']);
	setrawcookie('name', $_POST['name'], $conf['cookieExpireTime']);
	setrawcookie('email', $_POST['email'], $conf['cookieExpireTime']);


	$fileHandler = new fileHandlerClass($conf['fileConf']);
	$post = new PostDataClass(	$conf,$name,$email,$subject,
								$comment,$password,time(),$_SERVER['REMOTE_ADDR'],
								$thread->getThreadID());

	/*
	// get the uploaded files and put them inside the post object.
	$uploadFiles = $fileHandler->getFilesFromPostRequest();
	foreach ($uploadFiles as $file) {
		$postData->addFile($file);
	}
	
	// do file procssesing like make thumbnails. make hash. etc.
	$postData->procssesFiles(); 
	*/

	// if we are not admin or mod, remove any html tags.
	if( !$AUTH->isAdmin() || !$AUTH->isMod()){ 	
		$post->stripHtml();
	}

	//if the board lets you tripcode, apply tripcode to name.
	if($conf['canTripcode']){
		$post->applyTripcode();	
	}

	$HOOK->executeHook("onUserPostToBoard", $post, $fileHandler);// HOOK base post fully loaded

	/* prep post for db and drawing */

	// if the board allows embeding of links
	if($conf['autoEmbedLinks']){
		$post->embedLinks();
	}
	// if board allows post to link to other post.
	if($conf['allowQuoteLinking']){
		$post->quoteLinks();
	}

	// stuff like bb code, emotes, capcode, ID, should all be handled in moduels.
	$HOOK->executeHook("onPostPrepForDrawing", $post);// HOOK post with html fully loaded
	return $post;
}
function userPostToThread($board){
	$conf = $board->getConf();
	global $POSTREPO;

	// load existing thread
	$thread = $board->getThreadByID($_POST['threadID']);
	
	// create post with thread
	$post = genUserPostFromRequest($conf, $thread);

	// save post to data base.
	$POSTREPO->createPost($conf, $post);

	return;
}
function userPostNewThread($board){
	$conf = $board->getConf();
	global $POSTREPO;
	global $THREADREPO;

	// make a new thread
	$thread = new threadClass($conf, time());

	// create post with thread
	$post = genUserPostFromRequest($conf, $thread);

	// save post and thread to data base.
	$POSTREPO->createPost($conf, $post);
	$THREADREPO->createThread($conf, $thread, $post);

	return;
}
function userDeletedPost(){

}

/*-------------------------------------------------------MAIN ENTRY-------------------------------------------------------*/

/*
 * this file is the main request handler of the board after it is already installed.
 * below handels the routing.
 * 
 * you should not try and hack anything into this file unless you know what you are doing.
 * ./moduels/ is where your hacks should be put. and then enable them through the admin pannel.
 */

$boardID = $_GET['boardID'] ?? $_POST['boardID'] ?? '';

if (!is_numeric($boardID)) {
	displayErrorPageAndDie("you must have a boardID");
}

$board = $BOARDREPO->loadBoardByID($boardID);
if(is_null($board) || $board->getConf()['unlisted']) {
	displayErrorPageAndDie("board with the boardID of \"".$boardID."\"dose not exist");
}

$html = new htmlclass($board->getConf(), $board);

/*----------get action recived----------*/
if (isset($_GET['action'])){
	$action = $_GET['action'];
	switch ($action) {
		case 'thread'://go to thead.
			break;
		default:
			break;
	}
}
/*----------post action recived----------*/
elseif(isset($_POST['action'])){
	$action = $_POST['action'];
	switch ($action) {
		case 'postToThread':
			userPostToThread($board);
			break;
		case 'postNewThread':
			userPostNewThread($board);
			displayErrorPageAndDie("thread created");// temp. make a suscsses and redirect
			break;
		default:
			$stripedInput = htmlspecialchars($_POST['action'], ENT_QUOTES, 'UTF-8');
			displayErrorPageAndDie("invalid action: " . $stripedInput);
			break;
	}
}
/*----------no action recived----------*/
else{
	$html->drawPage(1);
}




/*
if(GZIP_COMPRESS_LEVEL && ($Encoding = CheckSupportGZip())){ ob_start(); ob_implicit_flush(0); } 

switch($mode){
	case 'postToThread':
		onUserPostToThread();
		break;
	case 'admin':
		if ($pass=$_POST['pass']??'')
			$_SESSION['kokologin'] = $pass;
		$level = valid($pass);
		$admin = $_REQUEST['admin']??'';
		$dat = '';
		head($dat);
		$links = '[<a href="'.PHP_SELF2.'?'.$_SERVER['REQUEST_TIME'].'">Return</a>] [<a href="'.PHP_SELF.'?mode=rebuild">Rebuild</a>] [<a href="'.PHP_SELF.'?pagenum=0">Live Frontend</a>]';
		$PMS->useModuleMethods('LinksAboveBar', array(&$dat,'admin',$level));
		$dat.= "$links<center class=\"theading3\"><b>Administrator mode</b></center>";
		$dat.= '<center><form action="'.PHP_SELF.'" method="POST" name="adminform">';
		$admins = array(
			array('name'=>'del', 'level'=>LEV_JANITOR, 'label'=>'Manage posts', 'func'=>'admindel'),
			array('name'=>'action', 'level'=>LEV_ADMIN, 'label'=>'Action log', 'func'=>'actionlog'),
			array('name'=>'optimize', 'level'=>LEV_ADMIN, 'label'=>'Optimize', 'func'=>''),
			array('name'=>'check', 'level'=>LEV_ADMIN, 'label'=>'Check data source', 'func'=>''),
			array('name'=>'repair', 'level'=>LEV_ADMIN, 'label'=>'Repair data source', 'func'=>''),
			array('name'=>'export', 'level'=>LEV_ADMIN, 'label'=>'Export data', 'func'=>''),
			array('name'=>'logout', 'level'=>LEV_JANITOR, 'label'=>'Logout', 'func'=>'logout'),
		);
		$dat.= '<nobr>';
		foreach ($admins as $adminmode) {
			if ($level==LEV_NONE && $adminmode['name']=='logout') continue;
			$checked = ($admin==$adminmode['name']) ? ' checked="checked"' : '';
			$dat.= '<label><input type="radio" name="admin" value="'.$adminmode['name'].'"'.$checked.' />'.$adminmode['label'].'</label> ';
		}
		$dat.= '</nobr>';
		if ($level==LEV_NONE) {
			$dat.= '<br/>
	<input class="inputtext" type="password" name="pass" value="" size="8" /><button type="submit" name="mode" value="admin">Login</button>
	</form></center><hr/>';
			foot($dat);
			die($dat.'</body></html>');
		} else {
			$dat.= '<button type="submit" name="mode" value="admin">Submit</button></form></center>';
		}
		$find = false;
		foreach ($admins as $adminmode) {
			if ($admin!=$adminmode['name']) continue;
			$find = true;
			if ($adminmode['level']>$level) {
				$dat.= '<center><b class="error">ERROR: No Access.</b></center><hr size="1" />';
				break;
			}
			if ($adminmode['func']) {
				$adminmode['func']($dat, $admin);
			} else {
				if(!$PIO->dbMaintanence($admin)) $dat.= '<center><b class="error">ERROR: Backend does not support this operation.</b></center><hr size="1" />';
				else $dat.= '<center>'.(($mret=$PIO->dbMaintanence($admin,true))
					? '<b class="good">Success!</b>'
					: '<b class="error">Failure!</b>').
					(is_bool($mret)?'':"<br />$mret<hr size='1' />").'</center>';
			}
		}
		if (!$find) $dat.= '<hr size="1" />';
		foot($dat);
		die($dat.'</body></html>');
		break;
	case 'search':
		search();
		break;
	case 'status':
		showstatus();
		break;
	case 'category':
		searchCategory();
		break;
	case 'module':
		$PMS = PMCLibrary::getPMSInstance();
		$load = $_GET['load']??$_POST['load']??'';
		if($PMS->onlyLoad($load)) $PMS->moduleInstance[$load]->ModulePage();
		else error("Module Not Found(".htmlspecialchars($load).")");
		break;
	case 'moduleloaded':
		listModules();
		break;
	case 'usrdel':
		usrdel();
	case 'rebuild':
		if (valid()>=LEV_JANITOR) {
			logtime("Rebuilt pages", valid());
			updatelog();
			if(THREAD_PAGINATION){
				if($oldCaches = glob(STORAGE_PATH.'cache/*')){
					foreach($oldCaches as $o) unlink($o); // Clear old catalog caches
				}
			}
		}
		header('HTTP/1.1 302 Moved Temporarily');
		header('Location: '.fullURL().PHP_SELF2.'?'.time());
		break;
	default:
		header('Content-Type: text/html; charset=utf-8');

		$res = isset($_GET['res']) ? $_GET['res'] : 0; // To respond to the number
		if($res){ // Response mode output
			$page = $_GET['pagenum']??'RE_PAGE_MAX';
			if(!($page=='all' || $page=='RE_PAGE_MAX')) $page = intval($_GET['pagenum']);
			updatelog($res, $page); // Implement pagin
		}elseif(isset($_GET['pagenum']) && intval($_GET['pagenum']) > -1){ // PHP dynamically outputs one page
			updatelog(0, intval($_GET['pagenum']));
		}else{ // Go to the static inventory page
			if(!is_file(PHP_SELF2)) {
				logtime("Rebuilt pages");
				updatelog();
			}
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.fullURL().PHP_SELF2.'?'.$_SERVER['REQUEST_TIME']);
		}
}

if(GZIP_COMPRESS_LEVEL && $Encoding){ // If Gzip is enabled
	if(!ob_get_length()) exit; // No content, no need to compress
	header('Content-Encoding: '.$Encoding);
	header('X-Content-Encoding-Level: '.GZIP_COMPRESS_LEVEL);
	header('Vary: Accept-Encoding');
	print gzencode(ob_get_clean(), GZIP_COMPRESS_LEVEL); // Compressed content
}
*/
//clearstatcache();
