<?php
//This file contains functions for koko management mode and related features


/* Manage article(threads) mode */
function admindel(&$dat){
	global $config;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$AccountIO = PMCLibrary::getAccountIOInstance();

	$pass = $_POST['pass']??''; // Admin password
	$page = $_REQUEST['page']??0; // Toggle the number of pages
	$onlyimgdel = $_POST['onlyimgdel']??''; // Only delete the image
	$modFunc = '';
	$delno = $thsno = array();
	$message = ''; // Display message after deletion
	$searchHost = filter_var($_GET['host'], FILTER_VALIDATE_IP) ?: filter_var($_GET['host'], FILTER_VALIDATE_DOMAIN);
	if ($searchHost) {
		if ($AccountIO->valid() <= $config['roles']['LEV_JANITOR']) error('ERROR: No Access.');
		$noticeHost = '<h2>Viewing all posts from: '.$searchHost.'. Click submit to cancel.</h2><br>';
	}
	//username for logging
	$moderatorUsername = $AccountIO->getUsername();
	$moderatorLevel = $AccountIO->getRoleLevel();
	
	// Delete the article(thread) block
	$delno = array_merge($delno, $_POST['clist']??array());
	if($delno) logtime("Delete post No.$delno".($onlyimgdel?' (file only)':''), $moderatorUsername.' ## '.$moderatorLevel);
	if($onlyimgdel != 'on') $PMS->useModuleMethods('PostOnDeletion', array($delno, 'backend')); // "PostOnDeletion" Hook Point
	$files = ($onlyimgdel != 'on') ? $PIO->removePosts($delno) : $PIO->removeAttachments($delno);
	$FileIO->updateStorageSize(-$FileIO->deleteImage($files));
	deleteCache($delno);
	$PIO->dbCommit();

	$line = ($searchHost ? $PIO->fetchPostList(0, 0, 0, $searchHost) : $PIO->fetchPostList(0, $page * $config['ADMIN_PAGE_DEF'], $config['ADMIN_PAGE_DEF'])); // A list of tagged articles
	$posts_count = count($line); // Number of cycles
	$posts = $PIO->fetchPosts($line); // Article content array

	$dat.= '<form action="'.$config['PHP_SELF'].'" method="POST">';
	$dat.= '<input type="hidden" name="mode" value="admin">
	<input type="hidden" name="admin" value="del">
	<div align="left">'._T('admin_notices').'</div>'.
	$message.'<br>'.$noticeHost.'
	<center><table width="100%" cellspacing="0" cellpadding="0" border="1" class="postlists">
	<thead>
		<tr>'._T('admin_list_header').'</tr></thead>
	<tbody>';

	for($j = 0; $j < $posts_count; $j++){
		$bg = ($j % 2) ? 'row1' : 'row2'; // Background color
		extract($posts[$j]);
		
		// Modify the field style
		$name = htmlspecialchars(str_cut(html_entity_decode(strip_tags($name)), 9));
		$sub = htmlspecialchars(str_cut(html_entity_decode($sub), 8));
		if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		$com = str_replace('<br>',' ',$com);
		$com = htmlspecialchars(str_cut(html_entity_decode($com), 20));


		// The first part of the discussion is the stop tick box and module function
		$modFunc = ' ';
		$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$j], $resto)); // "AdminList" Hook Point
		if($resto==0){ // $resto = 0 (the first part of the discussion string)
			$flgh = $PIO->getPostStatus($status);
		}

		// Extract additional archived image files and generate a link
		if($ext && $FileIO->imageExists($tim.$ext)){
			$clip = '<a href="'.$FileIO->getImageURL($tim.$ext).'" target="_blank">'.$tim.$ext.'</a>';
			$size = $FileIO->getImageFilesize($tim.$ext);
			$thumbName = $FileIO->resolveThumbName($tim);
			if($thumbName != false) $size += $FileIO->getImageFilesize($thumbName);
		}else{
			$clip = $md5chksum = '--';
			$size = 0;
		}

		if ($AccountIO->valid() <= $config['roles']['LEV_JANITOR']) {
			$host = " - ";
		}

			// Print out the interface
		$dat .= '<tr align="LEFT">
    <th align="center">' . $modFunc . '</th><th><input type="checkbox" name="clist[]" value="' . $no . '"><a target="_blank" href="koko.php?res=' . $no . '">' . $no . '</a></th>
    <td><small class="time">' . $now . '</small></td>
    <td><b class="title">' . $sub . '</b></td>
    <td><b class="name">' . $name . '</b></td>
    <td>' . $com . '</td>
    <td>' . $host . ' <a target="_blank" href="https://otx.alienvault.com/indicator/ip/' . $host . '" title="Resolve hostname"><img height="12" src="' . $config['STATIC_URL'] . 'image/glass.png"></a> <a href="?mode=admin&admin=del&host=' . $host . '" title="See all posts">★</a></td>
    <td align="center">' . $clip . ' (' . $size . ')<br>' . $md5chksum . '</td>
</tr>';
	}
	$dat.= '</tbody></table>
		<p>
        <button type="button" onclick="selectAll()">Select All</button>
			<input type="submit" value="'._T('admin_submit_btn').'"> <input type="reset" value="'._T('admin_reset_btn').'"> [<label><input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">'._T('del_img_only').'</label>]
		</p>
		<p>'._T('admin_totalsize', $FileIO->getCurrentStorageSize()).'</p>
</center></form>
<hr size="1">
<script>
function selectAll() {
    var checkboxes = document.querySelectorAll(\'input[name="clist[]"]\');
        checkboxes.forEach(function(checkbox) {
        checkbox.checked = true;
    });
}
</script>
';

	$countline = $PIO->postCount(); // Total number of articles(threads)
	$page_max = ($searchHost ? 0 : ceil($countline / $config['ADMIN_PAGE_DEF']) - 1); // Total number of pages
	$dat.= '<table id="pager" border="1" cellspacing="0" cellpadding="0"><tbody><tr>';
	if($page) $dat.= '<td><a href="'.$config['PHP_SELF'].'?mode=admin&admin=del&page='.($page - 1).($searchHost?'&host='.$searchHost:'').'">'._T('prev_page').'</a></td>';
	else $dat.= '<td nowrap="nowrap">'._T('first_page').'</td>';
	$dat.= '<td>';
	for($i = 0; $i <= $page_max; $i++){
		if($i==$page) $dat.= '[<b>'.$i.'</b>] ';
		else $dat.= '[<a href="'.$config['roles']['PHP_SELF'].'?mode=admin&admin=del&page='.$i.($searchHost?'&host='.$searchHost:'').'">'.$i.'</a>] ';
	}
	$dat.= '</td>';
	if($page < $page_max) $dat.= '<td><a href="'.$config['PHP_SELF'].'?mode=admin&admin=del&page='.($page + 1).($searchHost?'&host='.$searchHost:'').'">'._T('next_page').'</a></td>';
	else $dat.= '<td nowrap="nowrap">'._T('last_page').'</td>';
	$dat.= '</tr></tbody></table>';
}

/* Display system information */
function showstatus(){
	global $config;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$AccountIO = PMCLibrary::getAccountIOInstance();

	$countline = $PIO->postCount(); // Calculate the current number of data entries in the submitted text log file
	$counttree = $PIO->threadCount(); // Calculate the current number of data entries in the tree structure log file
	$tmp_total_size = $FileIO->getCurrentStorageSize(); // The total size of the attached image file usage
	$tmp_ts_ratio = $config['STORAGE_MAX'] > 0 ? $tmp_total_size / $config['STORAGE_MAX'] : 0; // Additional image file usage

	// Determines the color of the "Additional Image File Usage" prompt
  	if($tmp_ts_ratio < 0.3 ) $clrflag_sl = '235CFF';
	elseif($tmp_ts_ratio < 0.5 ) $clrflag_sl = '0CCE0C';
	elseif($tmp_ts_ratio < 0.7 ) $clrflag_sl = 'F28612';
	elseif($tmp_ts_ratio < 0.9 ) $clrflag_sl = 'F200D3';
	else $clrflag_sl = 'F2004A';

	// Generate preview image object information and whether the functions of the generated preview image are normal
	$func_thumbWork = '<span class="offline">'._T('info_nonfunctional').'</span>';
	$func_thumbInfo = '(No thumbnail)';
	if($config['USE_THUMB'] !== 0){
		$thumbType = $config['USE_THUMB']; if($config['USE_THUMB']==1){ $thumbType = 'gd'; }
		require($config['ROOTPATH'].'lib/thumb/thumb.'.$thumbType.'.php');
		$thObj = new ThumbWrapper();
		if($thObj->isWorking()) $func_thumbWork = '<span class="online">'._T('info_functional').'</span>';
		$func_thumbInfo = $thObj->getClass();
		unset($thObj);
	}

	// PIOSensor
	if(count($config['LIMIT_SENSOR']))
		$piosensorInfo=nl2br(PIOSensor::info($config['LIMIT_SENSOR']));

	$dat = '';
	head($dat);
	$links = '[<a href="'.$config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>] [<a href="'.$config['PHP_SELF'].'?mode=moduleloaded">'._T('module_info_top').'</a>]';
	$level = $AccountIO->valid();
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'status',$level));
	$dat .= $links.'<center class="theading2"><b>'._T('info_top').'</b></center>
</div>
<center id="status">
	<table cellspacing="0" cellpadding="0" border="1"><thead>
		<tr><th colspan="4">'._T('info_basic').'</th></tr>
	</thead><tbody>
		<tr><td width="240">'._T('info_basic_ver').'</td><td colspan="3"> '.$config['PIXMICAT_VER'].' </td></tr>
		<tr><td>'._T('info_basic_pio').'</td><td colspan="3"> '.$config['PIXMICAT_BACKEND'].' : '.$PIO->pioVersion().'</td></tr>
		<tr><td>'._T('info_basic_threadsperpage').'</td><td colspan="3"> '.$config['PAGE_DEF'].' '._T('info_basic_threads').'</td></tr>
		<tr><td>'._T('info_basic_postsperpage').'</td><td colspan="3"> '.$config['RE_DEF'].' '._T('info_basic_posts').'</td></tr>
		<tr><td>'._T('info_basic_postsinthread').'</td><td colspan="3"> '.$config['RE_PAGE_DEF'].' '._T('info_basic_posts').' '._T('info_basic_posts_showall').'</td></tr>
		<tr><td>'._T('info_basic_bumpposts').'</td><td colspan="3"> '.$config['MAX_RES'].' '._T('info_basic_posts').' '._T('info_basic_0disable').'</td></tr>
		<tr><td>'._T('info_basic_bumphours').'</td><td colspan="3"> '.$config['MAX_AGE_TIME'].' '._T('info_basic_hours').' '._T('info_basic_0disable').'</td></tr>
		<tr><td>'._T('info_basic_urllinking').'</td><td colspan="3"> '.$config['AUTO_LINK'].' '._T('info_0no1yes').'</td></tr>
		<tr><td>'._T('info_basic_com_limit').'</td><td colspan="3"> '.$config['COMM_MAX']._T('info_basic_com_after').'</td></tr>
		<tr><td>'._T('info_basic_anonpost').'</td><td colspan="3"> '.$config['ALLOW_NONAME'].' '._T('info_basic_anonpost_opt').'</td></tr>
		<tr><td>'._T('info_basic_del_incomplete').'</td><td colspan="3"> '.$config['KILL_INCOMPLETE_UPLOAD'].' '._T('info_0no1yes').'</td></tr>
		<tr><td>'._T('info_basic_use_sample', $config['THUMB_SETTING']['Quality']).'</td><td colspan="3"> '.$config['USE_THUMB'].' '._T('info_0notuse1use').'</td></tr>
		<tr><td>'._T('info_basic_useblock').'</td><td colspan="3"> '.$config['BAN_CHECK'].' '._T('info_0disable1enable').'</td></tr>
		<tr><td>'._T('info_basic_showid').'</td><td colspan="3"> '.$config['DISP_ID'].' '._T('info_basic_showid_after').'</td></tr>
		<tr><td>'._T('info_basic_cr_limit').'</td><td colspan="3"> '.$config['BR_CHECK']._T('info_basic_cr_after').'</td></tr>
		<tr><td>'._T('info_basic_timezone').'</td><td colspan="3"> GMT '.$config['TIME_ZONE'].'</td></tr>
		<tr><td>'._T('info_basic_theme').'</td><td colspan="3"> '.$PTE->BlockValue('THEMENAME').' '.$PTE->BlockValue('THEMEVER').'<br>by '.$PTE->BlockValue('THEMEAUTHOR').'</td></tr>
		<tr><th colspan="4">'._T('info_dsusage_top').'</th></tr>
		<tr align="center"><td>'._T('info_basic_threadcount').'</td><td colspan="'.(isset($piosensorInfo)?'2':'3').'"> '.$counttree.' '._T('info_basic_threads').'</td>'.(isset($piosensorInfo)?'<td rowspan="2">'.$piosensorInfo.'</td>':'').'</tr>
		<tr align="center"><td>'._T('info_dsusage_count').'</td><td colspan="'.(isset($piosensorInfo)?'2':'3').'">'.$countline.'</td></tr>
		<tr><th colspan="4">'._T('info_fileusage_top').$config['STORAGE_LIMIT'].' '._T('info_0disable1enable').'</th></tr>';

	if($config['STORAGE_LIMIT']){
		$dat .= '
		<tr align="center"><td>'._T('info_fileusage_limit').'</td><td colspan="2">'.$config['STORAGE_MAX'].' KB</td><td rowspan="2">'._T('info_dsusage_usage').'<br><font color="#'.$clrflag_sl.'">'.substr(($tmp_ts_ratio * 100), 0, 6).'</font> %</td></tr>
		<tr align="center"><td>'._T('info_fileusage_count').'</td><td colspan="2"><font color="#'.$clrflag_sl.'">'.$tmp_total_size.' KB</font></td></tr>';
	}else{
		$dat .= '
		<tr align="center"><td>'._T('info_fileusage_count').'</td><td>'.$tmp_total_size.' KB</td><td colspan="2">'._T('info_dsusage_usage').'<br><span class="green">'._T('info_fileusage_unlimited').'</span></td></tr>';
	}

	$dat .= '
		<tr><th colspan="4">'._T('info_server_top').'</th></tr>
		<tr align="center"><td colspan="3">'.$func_thumbInfo.'</td><td>'.$func_thumbWork.'</td></tr>
	</tbody></table>
	<hr size="1">
</center>';

	foot($dat);
	echo $dat;
}

/* write to admin log */
function actionlog(&$dat) {
	global $config;
	$LIMIT = 40;
	$page = intval($_REQUEST['page']??0);
	$offset = $page*$LIMIT;
	// filter
	$filter = $_REQUEST['filter']??'';
	$ipfilter = preg_quote($_REQUEST['ipfilter']??'');
	$dat.= '<p align="LEFT"><form action="'.$config['PHP_SELF'].'" method="GET">
	<input type="hidden" name="mode" value="admin">
	<input type="hidden" name="admin" value="action">
	<input type="hidden" name="page" value="'.$page.'">
	<select name="filter">
		<option'.($filter==''?' selected="selected"':'').' value="">All actions</option>
		<option'.($filter=='system'?' selected="selected"':'').' value="system">System actions only</option>
		<option'.($filter=='user'?' selected="selected"':'').' value="user">User actions only</option>
		<option'.($filter=='moderator'?' selected="selected"':'').' value="moderator">Moderator actions only</option>
		<option'.($filter=='janitor'?' selected="selected"':'').' value="janitor">## Janitor actions only</option>
		<option'.($filter=='mod'?' selected="selected"':'').' value="mod">## Mod actions only</option>
		<option'.($filter=='admin'?' selected="selected"':'').' value="admin">## Admin actions only</option>
	</select><input type="submit" value="Filter"><br>
	<label>IP Addr:<input class="textinput" type="text" name="ipfilter" value="'.($_REQUEST['ipfilter']??'').'"></label>
</form>';
	switch ($filter) {
		case 'user':
			$regex = 'USER';
			break;
		case 'system':
			$regex = 'SYSTEM';
			break;
		case 'moderator':
			$regex = '(JANITOR|MOD|ADMIN)';
			break;
		case 'janitor':
			$regex = 'JANITOR';
			break;
		case 'mod':
			$regex = 'MOD';
			break;
		case 'admin':
			$regex = 'ADMIN';
			break;
		default:
			$regex = '';
			break;
	}
	if ($ipfilter) $regex.= "\s\($ipfilter\)";
	// log
	$dat.= '<pre class="actionlog">';
	$log = array_reverse(file($config['STORAGE_PATH'].$config['ACTION_LOG']));
	$log = array_filter($log, function ($a) use ($regex) { return preg_match("/$regex/", $a); });
	$log = array_values($log);
	$find = false;
	for ($i=$offset; $i<$offset+$LIMIT; $i++) {
		if (!isset($log[$i])) continue;
		$dat.= $log[$i];
		$find = true;
	}
	if (!$find) $dat.= 'No result found with specified filter.';
	$dat.= '</pre>';
	// pager
	$dat.= '<table id="pager" cellspacing="0" cellpadding="0" border="1"><tbody><tr>';
	if ($page) $dat.= '<td><a href="'.$config['PHP_SELF'].'?mode=admin&admin=action&page='.($page-1).'&filter='.$filter.'">Prev</a></td>';
	else $dat.= '<td>First</td>';
	$dat.= '<td>';
	for ($i=0; $i<count($log); $i+=$LIMIT) {
		$p = $i/$LIMIT;
		if ($p==$page) $dat.= '[<b>'.($p+1).'</b>]';
		else $dat.= '[<a href="'.$config['PHP_SELF'].'?mode=admin&admin=action&page='.$p.'&filter='.$filter.'&">'.($p+1).'</a>]';
	}
	$dat.= '</td>';
	if ($offset<count($log)-$LIMIT) $dat.= '<td><a href="'.$config['PHP_SELF'].'?mode=admin&admin=action&page='.($page+1).'&filter='.$filter.'">Next</a></td>';
	else $dat.= '<td>Last</td>';
	$dat.= '</tr></tbody></table><br clear="ALL">';
}


function drawAccountCreationForm(&$dat) {
	global $config;
	$dat .= '
	<center>
		<h4>Add a new moderator account</h4>
       <form action="'.$config['PHP_SELF'].'?mode=createAcc" method="post">
       <table ><tbody>
       <tr>
           <td class="postblock"><label for="usrname">Account username:</label></td>
           <td><input  required maxlength="50" id="usrname" name="usrname"></td>
       </tr>
       <tr>
           <td class="postblock"><label for="passwd">Account password:</label></td>
           <td><input type="password" id="passwd" name="passwd" required="" maxlength="1000"></td>
       </tr>
		<tr>
			<td class="postblock"><label for="hashed">Already hashed?</label></td>
			<td><input type="checkbox" id="hashed" name="ishashed"></td>
			</tr>
       <tr>
           <td class="postblock"><label for="role">Role</label></td>
           <td>
           <select id="role" name="role" required>
           <option value="" disabled="" selected="">Select a role</option>
           	<option value="'.$config['roles']['LEV_USER'].'">User</option>
			<option value="'.$config['roles']['LEV_JANITOR'].'">Janitor</option>
			<option value="'.$config['roles']['LEV_MODERATOR'].'">Moderator</option>
			<option value="'.$config['roles']['LEV_ADMIN'].'">Admin</option>
		</select></td>
       </tr>
       <tr>
           <td><input type="submit" value="Create account"></td>   
       </tr>
      </tbody>
	</table>
    </form>
    </center> <br>';
}

function createAccount() {
	global $config;
	$AccountIO = PMCLibrary::getAccountIOInstance();
	if($AccountIO->valid() < $config['roles']['LEV_ADMIN']) error("403 Access Denied");
	
	$dat = '';
	head($dat);
	
	$dat .= '[<a href="'.$config['PHP_SELF2'].'?'.time().'">Return</a>]';
	//just check if one of the fields is set
	if(!empty($_POST['usrname']) && !empty($_POST['passwd'])) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		$ishashed = false;
		if(isset($_POST['ishashed'])) $ishashed = $_POST['ishashed']; 
		
		$nUsername = strval(htmlspecialchars($_POST['usrname'])); //username for new account
		$nPass = strval($_POST['passwd']);//password for new account
		$nRole = intval($_POST['role']);//moderation role

		(!$ishashed) ? $hashedPassword = password_hash($nPass, PASSWORD_DEFAULT) : $hashedPassword = $nPass; //password hash to be stored in account flatfile
		
		//auth role
		switch($nRole) {
			case $config['roles']['LEV_USER']:
			case $config['roles']['LEV_JANITOR']:
			case $config['roles']['LEV_MODERATOR']:
			case $config['roles']['LEV_ADMIN']:
				$AccountIO->addNewAccount($nUsername, $hashedPassword, $nRole); //enter account into flatfile
			break;
			default:
				error("Not a valid role");
			break;
		}
		
		
		
		$dat .= '<center>Creation of a new account was a success!<br> [<a href="'.$config['PHP_SELF'].'?mode=viewAcc">View mod list</a>] </center><br> ';
	} else {
		drawAccountCreationForm($dat);
	}
	
    foot($dat);
    echo $dat;
}

/* Comparision for account roles - used in sorting */
function compareAccountByRole($acc1, $acc2) {
	if($acc1['role'] == $acc2['role']) return 0;
	return ($acc1['role'] < $acc2['role']) ? 1 : -1;
}

function buildAccountArray($accountText) {
	$accountArray = array();
	foreach($accountText as $account) {
		if(sizeof($account) != 4) continue;
		$accountArray[] = array_combine(['id', 'username', 'password', 'role'], $account);
	}
	return $accountArray;
}

function handleAccountDelete($id) {
	$AccountIO = PMCLibrary::getAccountIOInstance();
	$id = intval($id);
	
	if(!is_numeric($id)) error("Invalid ID");
	$moderatorUsername = $AccountIO->getUsername();
	$moderatorLevel = $AccountIO->getRoleLevel();
	logtime("Deleted '".$id."' from accounts", $moderatorUsername.' ## '.$moderatorLevel);	
	$AccountIO->deleteAccount($id);
}

function handleAccountDemote($id) {
	global $config;
	if(!is_numeric($id)) error("Invalid ID");
	
	$AccountIO = PMCLibrary::getAccountIOInstance();
	$id = intval($id);
	$currentRole = $AccountIO->getAccountById($id)['role'];
	$newRole = $currentRole - 1;
	
	if(($currentRole - 1) <= $config['roles']['LEV_NONE']) {
		drawAlert("Could not demote $id. Can't lower role any further.");
		//redirect($config['PHP_SELF'].'?mode=viewAcc');
		return false;
	}
	
	$AccountIO->editAccountRole($id, $newRole); //subtract role number to 'demote'
	
	$moderatorUsername = $AccountIO->getUsername();
	$moderatorLevel = $AccountIO->getRoleLevel();
	logtime("Demoted '".$id."' to ".num2role($newRole)."", $moderatorUsername.' ## '.$moderatorLevel);	
}

function handleAccountPromote($id) {
	global $config;
	if(!is_numeric($id)) error("Invalid ID");
	
	$AccountIO = PMCLibrary::getAccountIOInstance();
	$id = intval($id);
	$currentRole = $AccountIO->getAccountById($id)['role'];
	$newRole = $currentRole + 1;
	
	if(($currentRole + 1) > $config['roles']['LEV_ADMIN']) {
		drawAlert("Could not demote $id. Can't raise role any further.");
		//redirect($config['PHP_SELF'].'?mode=viewAcc');
		return false;
	}
	
	$AccountIO->editAccountRole($id, $newRole); //subtract role number to 'demote'
	
	$moderatorUsername = $AccountIO->getUsername();
	$moderatorLevel = $AccountIO->getRoleLevel();
	logtime("Promoted '".$id."' to ".num2role($newRole)."", $moderatorUsername.' ## '.$moderatorLevel);	
}

function viewAccounts() {
	global $config;
	$AccountIO = PMCLibrary::getAccountIOInstance();
	if($AccountIO->valid() < $config['roles']['LEV_ADMIN']) error("403 Access Denied");
	
	//delete account
	if(isset($_GET['del'])) handleAccountDelete($_GET['del']);
	if(isset($_GET['dem'])) handleAccountDemote($_GET['dem']);
	if(isset($_GET['up'])) handleAccountPromote($_GET['up']);
	
	$dat = '';
	head($dat);
	$accountsHTML = '';
	$accounts = $AccountIO->getAllAccounts();
	
	
	//first, build
	$accountArrayData = buildAccountArray($accounts);
	
	usort($accountArrayData, 'compareAccountByRole');
	
	foreach($accountArrayData as $account) {
		$actionHTML = '[<a title="Delete account" href="'.$config['PHP_SELF'].'?mode=viewAcc&del='.$account['id'].'">D</a>] ';
		if($account['role'] + 1 <= $config['roles']['LEV_ADMIN']) $actionHTML .= '[<a title="Promote account" href="'.$config['PHP_SELF'].'?mode=viewAcc&up='.$account['id'].'">▲</a>]';
		if($account['role'] - 1 > $config['roles']['LEV_NONE']) $actionHTML .= '[<a title="Demote account" href="'.$config['PHP_SELF'].'?mode=viewAcc&dem='.$account['id'].'">▼</a>]';
		
		$accountsHTML .= '<tr> 
			<td><center> '.$account['id'].'</center></td>
			<td><center>'.$account['username'].' </center></td>
			<td><center>'.num2role($account['role']).'</center></td>
			<td><center> 
			'.$actionHTML.'
			 </center></td>
		</tr>';
	}
	
		$dat .= '[<a href="'.$config['PHP_SELF2'].'?'.time().'">Return</a>]';
		$dat .='	<center>
			<h4>Mod list</h4>
      	 <table  cellspacing="0" cellpadding="0" border="1" class="postlists">
      	 <tbody>
			<th> ID </th> <th> USERNAME </th> <th> ROLE </th> <th> ACTION </th>
			'.$accountsHTML.'
      	</tbody>
		</table>
    	</center> <br>';
	
	foot($dat);
	echo $dat;
}

function manageaccounts(&$dat) {
	global $config;
	$dat .= '
				<h3>Account management</h3>
		<fieldset class="menu" style="display: inline-block; width: 200px;"><legend>Panel</legend>
		[<a href="'.$config['PHP_SELF'].'?mode=viewAcc">Mod list</a>]
		<br>
		[<a href="'.$config['PHP_SELF'].'?mode=createAcc">Add a new account</a>]		
		</fieldset>';
}

function logout(&$dat) {
	global $config;
	unset($_SESSION['kokologin']);
	redirect(fullURL().$config['PHP_SELF'].'?mode=admin');
	exit;
}

function drawAdminList() {
		global $config;
		$PMS = PMCLibrary::getPMSInstance();
		$PIO = PMCLibrary::getPIOInstance();	
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		if ($pass=$_POST['pass']??'')
			$_SESSION['kokologin'] = $pass;
			
		//authenticate
		$level = $AccountIO->valid($pass);
		$log_in_msg = "<b class=\"username\">Logged in as ".$AccountIO->getUsername()." (".$AccountIO->getRoleLevel().")</b>";
		if($level == $config['roles']['LEV_NONE']) $log_in_msg = "";
		$admin = $_REQUEST['admin']??'';
		$dat = '';
		head($dat);
		$links = '[<a href="'.$config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>] [<a href="'.$config['PHP_SELF'].'?mode=rebuild">Rebuild</a>] [<a href="'.$config['PHP_SELF'].'?pagenum=0">Live Frontend</a>]';
		$PMS->useModuleMethods('LinksAboveBar', array(&$dat,'admin',$level));
		$dat .= $links; //hook above bar links
		$dat.= "<center class=\"theading3\"><b>Administrator mode</b> <br>$log_in_msg </center>";
		$dat.= '<center><form action="'.$config['PHP_SELF'].'" method="POST" name="adminform">';
		$admins = array(
			array('name'=>'del', 'level'=>$config['roles']['LEV_JANITOR'], 'label'=>'Manage posts', 'func'=>'admindel'),
			array('name'=>'action', 'level'=>$config['roles']['LEV_ADMIN'], 'label'=>'Action log', 'func'=>'actionlog'),
			array('name'=>'acct', 'level'=>$config['roles']['LEV_ADMIN'], 'label'=>'Manage accounts', 'func'=>'manageaccounts'),
			array('name'=>'export', 'level'=>$config['roles']['LEV_ADMIN'], 'label'=>'Export data', 'func'=>''),
			array('name'=>'optimize', 'level'=>$config['roles']['LEV_ADMIN'], 'label'=>'Optimize', 'func'=>''),
			array('name'=>'check', 'level'=>$config['roles']['LEV_ADMIN'], 'label'=>'Check data source', 'func'=>''),
			array('name'=>'repair', 'level'=>$config['roles']['LEV_ADMIN'], 'label'=>'Repair data source', 'func'=>''),
			array('name'=>'logout', 'level'=>$config['roles']['LEV_USER'], 'label'=>'Logout', 'func'=>'logout'),
		);
		$dat.= '<nobr>';
		foreach ($admins as $adminmode) {
			if ($level==$config['roles']['LEV_NONE'] && $adminmode['name']=='logout') continue;
			$checked = ($admin==$adminmode['name']) ? ' checked="checked"' : '';
			$dat.= '<label><input type="radio" name="admin" value="'.$adminmode['name'].'"'.$checked.'>'.$adminmode['label'].'</label> ';
		}
		$dat.= '</nobr>';
		if ($level==$config['roles']['LEV_NONE']) {
			$dat.= '<br>
				<input class="inputtext" type="password" name="pass" value="" size="8"><button type="submit" name="mode" value="admin">Login</button>
			</form></center><hr>';
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
				$dat.= '<center><b class="error">ERROR: No Access.</b></center><hr size="1">';
				break;
			}
			if ($adminmode['func']) {
				$adminmode['func']($dat, $admin);
			} else {
				if(!$PIO->dbMaintanence($admin)) $dat.= '<center><b class="error">ERROR: Backend does not support this operation.</b></center><hr size="1">';
				else $dat.= '<center>'.(($mret=$PIO->dbMaintanence($admin, true))
					? '<b class="good">Success!</b>'
					: '<b class="error">Failure!</b>').
					(is_bool($mret)?'':"<br>".print_r($mret, true)."<hr size='1'>").'</center>';
			}
		}
		if (!$find) $dat.= '<hr size="1">';
		foot($dat);
		die($dat.'</body></html>');
}
