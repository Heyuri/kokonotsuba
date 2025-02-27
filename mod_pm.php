<?php
/* mod_pm : Personal Messages for Trips (Pre-Alpha)
 * $Id$
 */
class mod_pm extends ModuleHelper {
	private $MESG_LOG = ''; // Log file location
	private $MESG_CACHE = ''; // Cache file location
	private $myPage;
	private $trips;
	private $lastno;

	public function __construct($PMS) {
		parent::__construct($PMS);

		$this->MESG_LOG = $this->config['ModuleSettings']['PM_DIR'].'tripmesg.log';
		$this->MESG_CACHE = $this->config['ModuleSettings']['PM_DIR'].'tripmesg.cc';

		$this->trips = array();
		$this->myPage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return 'mod_pm';
	}

	public function getModuleVersionInfo(){
		return 'mod_pm : Personal Messages for Trip (Pre-Alpha) (v140606)';
	}

	/* Automatic mounting:Top link column */
	public function autoHookToplink(&$linkbar, $isReply){
		$linkbar .= '[<a href="'.$this->myPage.'">Inbox</a>] [<a href="'.$this->myPage.'&amp;action=write">Write PM</a>]'."\n";
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply){
		if($this->config['ModuleSettings']['APPEND_TRIP_PM_BUTTON_TO_POST'] === false) return;
		if(strpos($post['name'], '◆') === false) return;

		$username = $post['name'];

		list($name, $trip) = explode('◆',$username);
		$tripSanitized = strip_tags($trip);
		if($trip) $arrLabels['{$NAME}'] = $username.'<a href="'.$this->myPage.'&action=write&t='.$tripSanitized.'" style="text-decoration: overline underline" title="PM">❖</a>';
	}

	public function autoHookThreadReply(&$arrLabels, $post, $isReply){
		$this->autoHookThreadPost($arrLabels, $post, $isReply);
	}

	private function _tripping($str) {
		$salt = preg_replace('/[^\.-z]/', '.', substr($str.'H.', 1, 2));
		$salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
		return substr(crypt($str, $salt), -10);
	}

	private function _latestPM() {
		$htm = '
		
<table id="tableLatestPM" class="postlists">
<caption><h3>Messages in the last 10 days</h3></caption>
	<thead>
		<tr>
			<th>Date sent</th>
			<th>Trip</th>
			<th>Messages</th>
		</tr>
	</thead>
	<tbody>';
		$this->_loadCache();

		foreach($this->trips as $t => $v) { //d=last update date, c=count
			if($v['d']<time()-864000) break; // out of range (10 days)
			$htm.='
		<tr>
			<td>'.date('Y-m-d H:i:s',$v['d']).($v['d']>time()-86400?' <span class="newPM">(new!)</span>':'').'</td>
			<td><span class="name">'._T('trip_pre').substr($t,0,5)."</span>...</td>
			<td>$v[c] "._T('info_basic_threads')."</td>
		</tr>";
		}
		return $htm.'
	</tbody>
</table>';
	}

	private function _loadCache() {
		if(!$this->trips) {
			if($logs=@file($this->MESG_CACHE)) { // Has cache
				$this->lastno=trim($logs[0]);
				$this->trips=unserialize($logs[1]);
				return true;
			} else { // No cache
				return $this->_rebuildCache();
			}
		} else return true;
	}

	private function _rebuildCache() {
		$this->trips = array();
		if($logs=@file($this->MESG_LOG)) { // mesgno,trip,date,from,topic,mesg = each $logs, order desc
			if(!$this->lastno) if(isset($logs[0])) $this->lastno = intval(substr($logs[0],strpos($logs[0],','))); // last no
			foreach($logs as $log) {
				list($mno,$trip,$pdate,)=explode(',',trim($log));
				if(isset($this->trips[$trip])) {
					$this->trips[$trip]['c']++;
//					if($this->trips[$trip]['d']<$pdate) $this->trips[$trip]['d'] = $pdate;
				} else {
					$this->trips[$trip]=array('c'=>1,'d'=>$pdate);
				}
			}
			// Sort in order
			foreach ($this->trips as $key => $row) {
				$c[$key] = $row['c'];
				$d[$key] = $row['d'];
			}
			array_multisort($d, SORT_DESC, $c, SORT_ASC, $this->trips);

			$this->_writeCache();

			return true;
		} else {
			$this->_writeCache();
			return false;
		}
	}

	private function _writeCache() {
		$this->_write($this->MESG_CACHE,$this->lastno."\n".serialize($this->trips));
	}

	private function _write($file,$data) {
		$rp = fopen($file, "w");
		flock($rp, LOCK_EX); // Lock files
		@fputs($rp,$data);
		flock($rp, LOCK_UN); // Unlock
		fclose($rp);
		chmod($file,0666);
	}

	private function _postPM($from,$to,$topic,$mesg) {
		$globalHTML = new globalHTML($this->board);
		if(!preg_match('/^[0-9a-zA-Z\.\/]{10}$/',$to)) $globalHTML->error("Incorrect Tripcode");
		$from=$globalHTML->CleanStr($from); $to=$globalHTML->CleanStr($to); $topic=$globalHTML->CleanStr($topic); $mesg=$globalHTML->CleanStr($mesg);
		if(!$from) if($this->config['ALLOW_NONAME']) $from = $this->config['DEFAULT_NONAME'];
		if(!$topic) $topic = $this->config['DEFAULT_NOTITLE'];
		if(!$mesg) $globalHTML->error("Please write a message");
		if(preg_match('/(.*?)[#＃](.*)/u', $from, $regs)){ // Tripcode Functrion
			$from = $nameOri = $regs[1]; $cap = strtr($regs[2], array('&amp;'=>'&'));
			$from = $from.'<span class="postertrip">'._T('trip_pre').$this->_tripping($cap)."</span>";
		}
		$from = str_replace(_T('admin'), '"'._T('admin').'"', $from);
		$from = str_replace(_T('deletor'), '"'._T('deletor').'"', $from);
		$from = str_replace('&'._T('trip_pre'), '&amp;'._T('trip_pre'), $from); // Avoid &#xxxx; being treated as Trip left behind & causing parsing errors
		$mesg = str_replace(',','&#44;',$mesg); // Convert ","
		$mesg = str_replace("\n",'<br>',$mesg); //new line breaking

		$this->_loadCache();

		$logs=(++$this->lastno).",$to,".time().",$from,$topic,$mesg,$_SERVER[REMOTE_ADDR],\n".@file_get_contents($this->MESG_LOG);
		$this->_write($this->MESG_LOG,$logs);

		$this->_rebuildCache();
	}

	private function _getPM($trip) {
		$PMS = self::$PMS;
		$PTE = PTELibrary::getInstance();
		$dat='';
		$trip=substr($trip,1);
		$tripped=$this->_tripping($trip);

		if($logs=@file($this->MESG_LOG)) { // mesgno,trip,date,from,topic,mesg,ip = each $logs, order desc
			foreach($logs as $log) {
				list($mno,$totrip,$pdate,$from,$topic,$mesg,$ip)=explode(',',trim($log));
				if($totrip==$tripped) {
					if(!$dat) $dat=$PTE->ParseBlock('REALSEPARATE',array()).'<form action="'.$this->myPage.'" method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="trip" value="'.$trip.'">';
					$arrLabels = array('{$NO}'=>$mno, '{$SUB}'=>$topic, '{$NAME}'=>$from, '{$NOW}'=>date('Y-m-d H:i:s',$pdate), '{$COM}'=>$mesg, '{$QUOTEBTN}'=>$mno, '{$REPLYBTN}'=>'', '{$IMG_BAR}'=>'', '{$IMG_SRC}'=>'', '{$WARN_OLD}'=>'', '{$WARN_BEKILL}'=>'', '{$WARN_ENDREPLY}'=>'', '{$WARN_HIDEPOST}'=>'', '{$NAME_TEXT}'=>_T('post_name'), '{$RESTO}'=>1);
					$dat .= $PTE->ParseBlock('THREAD',$arrLabels);
					$dat .= $PTE->ParseBlock('REALSEPARATE',array());
				}
			}
		}
		if(!$dat) $dat="No information.";
		else $dat.='<input type="submit" name="delete" value="'._T('del_btn').'"></form>';
		return $dat;
	}

	private function _deletePM($no,$trip) {
		$tripped=$this->_tripping($trip);
		$found=false;
		if($logs=@file($this->MESG_LOG)) { // mesgno,trip,date,from,topic,mesg = each $logs, order desc
			$countlogs=count($logs);
			foreach($no as $n) {
				for($i=0;$i<$countlogs;$i++) {
					list($mno,$totrip,)=explode(',',$logs[$i]);
					if($totrip==$tripped && $mno==$n) {
						$logs[$i]=''; // deleted
						$found=true;
						break;
					}
				}
			}
			if($found) {
				$newlogs=implode('',$logs);
				$this->_write($this->MESG_LOG,$newlogs);
				$this->_rebuildCache();
			}
		}
	}

	public function ModulePage(){
		$PMS = self::$PMS;
		$PIO = PIOPDO::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();

		$globalHTML = new globalHTML($this->board);
		$trip=isset($_REQUEST['t'])?$_REQUEST['t']:'';
		$action=isset($_REQUEST['action'])?$_REQUEST['action']:'';
		$dat='';

		if($action != 'postverify') {
			$globalHTML->head($dat);
			echo $dat.'[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>]';
		}
		if($action == 'write') {
			echo '
<div id="PMContainer">
	<h2 class="theading2">Send PM</h2>

	<div class="postformTable">
		<form id="pmform" action="'.$this->myPage.'" method="POST">
			<input type="hidden" name="action" value="post">
			<table cellpadding="1" cellspacing="2" id="postform_tbl" style="margin: 0px auto; text-align: left;">
				<tbody>
					<tr>
						<td class="postblock"><label for="inputFrom">From</label></td>
						<td><input type="text" class="inputtext" name="from" id="inputFrom" value=""><span class="inputInfo">(format: yourname#tripcode)</span></td>
					</tr>
					<tr>
						<td class="postblock"><label for="inputTo">To</label></td>
						<td>'._T('trip_pre').'<input type="text" class="inputtext" name="t" id="inputTo" value="'.$trip.'" maxlength="10"></td>
					</tr>
					<tr>
						<td class="postblock"><label for="inputSubject">'._T('form_topic').'</label></td>
						<td><input type="text" class="inputtext" name="topic" id="inputSubject" value=""><input type="submit" name="submit" value="'._T('form_submit_btn').'"></td>
					</tr>
					<tr>
						<td class="postblock"><label for="inputComment">'._T('form_comment').'</label></td>
						<td><textarea  class="inputtext" name="content" id="inputComment"></textarea></td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>
</div>

<script>
	$g("pmform").from.value=getCookie("namec");
</script>
';
		} elseif($action == 'post') {
			echo '
<div id="PMContainer">
	<h2 class="theading2">Confirm message</h2>

	<div class="postformTable">
		<form id="pmform" action="'.$this->myPage.'" method="POST">
			<input type="hidden" name="action" value="postverify">
			<table cellpadding="1" cellspacing="1" id="postform_tbl" style="margin: 0px auto; text-align: left;">
				<tbody>
					<tr>
						<td class="postblock"><label for="inputFrom">From</label></td>
						<td><input type="text" class="inputtext" name="from" id="inputFrom" value="'.$_POST['from'].'"></td>
					</tr>
					<tr>
						<td class="postblock"><label for="inputTo">To</label></td>
						<td>'._T('trip_pre').'<input type="text" class="inputtext" name="t" id="inputTo" value="'.$_POST['t'].'" maxlength="10"></td>
					</tr>
					<tr>
						<td class="postblock"><label for="inputSubject">'._T('form_topic').'</label></td>
						<td><input type="text" class="inputtext" name="topic" id="inputSubject" value="'.$_POST['topic'].'"><input type="submit" name="submit" value="'._T('form_submit_btn').'"></td>
					</tr>
					<tr>
						<td class="postblock"><label for="inputComment">'._T('form_comment').'</label></td>
						<td><textarea  class="inputtext" name="content" id="inputComment">'.$_POST['content'].'</textarea></td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>
</div>';
		} elseif($action == 'postverify') {
			$this->_postPM($_POST['from'],$_POST['t'],$_POST['topic'],$_POST['content']);
			if(preg_match('/(.*?)[#＃](.*)/u', $_POST['from'], $regs)){ // トリップ(Trip)機能
				$_POST['from'] = '<span class="postername">' . htmlspecialchars($regs[1]) . '</span>';
				$cap = strtr($regs[2], array('&amp;'=>'&'));
				$_POST['from'] = $_POST['from'].'<span class="postertrip">'._T('trip_pre').$this->_tripping($cap)."</span>";
			}
			$globalHTML->head($dat);
			echo $dat.'[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>]';
			echo '
<div id="PMContainer">
	<h2 class="theading2">Message sent</h2>

	<div class="postformTable">
		<table cellpadding="1" cellspacing="1" id="postform_tbl">
			<tbody>
				<tr>
					<td class="postblock"><label for="">From</label></td>
					<td class="name">'.$_POST['from'].'</td>
				</tr>
				<tr>
					<td class="postblock"><label for="">To</label></td>
					<td>'._T('trip_pre').$_POST['t'].'</td>
				</tr>
				<tr>
					<td class="postblock"><label for="">'._T('form_topic').'</label></td>
					<td>'.$_POST['topic'].'</td>
				</tr>
				<tr>
					<td class="postblock"><label for="">'._T('form_comment').'</label></td>
					<td><div class="comment">'.$_POST['content'].'</div></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>';
		} else {
			echo '
<div id="PMContainer">
	<h2 class="theading2">Inbox</h2>';
			if ($action === 'delete' && isset($_POST['trip'])) {
				$delno = [];
				
				foreach ($_POST as $key => $value) {
					if ($value === 'delete') {
						$delno[] = $key;
					}
				}
				
				if (!empty($delno)) {
					$this->_deletePM($delno, $_POST['trip']);
				}
			}
			echo $this->_latestPM();
			echo 'Check your inbox by inputting your password below<form id="pmform" action="'.$this->myPage.'" method="POST">
	<input type="hidden" name="action" value="check">
	<label>Trip:<input type="text" class="inputtext" name="trip" value="" size="28"></label><input type="submit" name="submit" value="'._T('form_submit_btn').'">(Trip pass with #)
	</form>
</div>
	<script>
		$g("pmform").trip.value=getCookie("namec").replace(/^[^#]*#/,"#");
	</script>';
			if($action == 'check' && isset($_POST['trip']) && substr($_POST['trip'],0,1) == '#') echo $this->_getPM($_POST['trip']);

		}
		$dat='';
		echo $dat;
	}
}//End-Of-Module
