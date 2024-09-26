<?php
// admin extra module made for kokonotsuba by deadking
class mod_adminban extends ModuleHelper {
	private $BANFILE ='';
	private $BANIMG = '';
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		
		$this->BANFILE = $this->config['STORAGE_PATH'].'bans.log.txt';
		$this->BANIMG = $this->config['STATIC_URL']."image/banned.jpg";
		
		$this->mypage = $this->getModulePageURL();
		touch($this->BANFILE);
		touch($this->config['GLOBAL_BANS']);
	}

	public function getModuleName() {
		return __CLASS__.' : K! Admin Ban';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBegin() {
		$ip = getREMOTE_ADDR();
		$glog = array_map('rtrim', file($this->config['GLOBAL_BANS']));
		$log = array_map('rtrim', file($this->BANFILE));
		
		for ($i=0; $i<count($log); $i++) {
			list($banip, $starttime, $expires, $reason) = explode(',', $log[$i], 4);
			if (strstr($ip, gethostbyname($banip))) {
				// ban page
				$dat = "";
				head($dat);
				$dat.= "
<style>
#banimg {
	float: right;
	max-width: 300px;
}
</style>
<h2>You have been ".($starttime==$expires?'warned':'banned')."! ヽ(ー_ー )ノ</h2><hr>
<img id=\"banimg\" src=\"".$this->BANIMG."\" alt=\"BANNED!\" align=\"RIGHT\" border=\"1\">
<p>$reason</p>";
				if ($_SERVER['REQUEST_TIME']>intval($expires)) {
					$dat.= 'Now that you have seen this message you can post again.';
					unset($log[$i]);
					file_put_contents($this->BANFILE, implode("\r\n", $log));
				} else {
					$dat.= "Your ban was filed on ".date('Y/m/d \a\t H:i:s', $starttime)." and expires on ".date('Y/m/d \a\t H:i:s', $expires).".";
				}
				$dat.= "<br>[<a href='".$this->config['PHP_SELF2']."'>Return</a>]
				<br clear=\"ALL\"><hr>";
				foot($dat);
				die($dat);
			}
		}
		// global ban page
		for ($i=0; $i<count($glog); $i++) {
			list($banip, $starttime, $expires, $reason) = explode(',', $glog[$i], 4);
			if (strstr($ip, $banip)) {
				// ban page
				$dat.= '';
				head($dat);
				$dat.= "
<style>
#banimg {
	float: right;
	max-width: 300px;
}
</style>
<h2>You have been ".($starttime==$expires?'warned':'banned')."! ヽ(ー_ー )ノ</h2><hr>
<img id=\"banimg\" src=\"".$this->BANIMG."\" alt=\"BANNED!\" align=\"RIGHT\" border=\"1\">
<p>$reason</p>";
				if ($_SERVER['REQUEST_TIME']>intval($expires)) {
					$dat.= 'Now that you have seen this message you can post again.';
					unset($glog[$i]);
					file_put_contents($this->config['GLOBAL_BANS'], implode("\r\n", $glog));
				} else {
					$dat.= "Your ban was filed on ".date('Y/m/d \a\t H:i:s', $starttime)." and expires on ".date('Y/m/d \a\t H:i:s', $expires).".";
				}
				$dat.= "<br>[<a href='".$this->config['PHP_SELF2']."'>Return</a>]
				<br clear=\"ALL\"><hr>";
				foot($dat);
				die($dat);
			}
		}
	}

	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		if ($AccountIO->valid() < $this->config['roles']['LEV_MODERATOR']
		 || $pageId != 'admin') return;
		$link.= '[<a href="'.$this->mypage.'">Manage Bans</a>] ';
	}

	private function _lookupPostIP($no) {
		$PIO = PMCLibrary::getPIOInstance();
		$v = $PIO->getPostIP($no);
		if ($v) return $v;
		return false;
		$f = @fopen($this->config['STORAGE_PATH'].$this->config['ACTION_LOG'], 'r');
		if (!$f) return false;
		while ($line=fgets($f)) {
			preg_match("/^(\w+) \((\d+\.\d+\.\d+\.\d+)\)\: \d+\/\d+\/\d+ \d+\:\d+\:\d+\: Post No.(\d+)/i", $line, $matches);
			if (count($matches)<3) continue;
			if ($matches[3]==$no) {
				fclose($f);
				return $matches[2];
			}
		}
		fseek($f, 0);
		fclose($f);
		return false;
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$FileIO = PMCLibrary::getFileIOInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		if ($AccountIO->valid() < $this->config['roles']['LEV_MODERATOR']) return;
		if (!($ip=$this->_lookupPostIP($post['no']))) return;
		$modfunc.= '[<a href="'.$this->mypage.'&no='.$post['no'].'&ip='.$ip.'" title="Ban">B</a>]';
		if (empty($_GET['mode']) && empty($_POST['mode'])) $modfunc.= '<small>[HOST: <a href="?mode=admin&admin=del&host='.$ip.'">'.$ip.'</a>]</small>';
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$PMS = PMCLibrary::getPMSInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();

		if ($AccountIO->valid() < $this->config['roles']['LEV_MODERATOR']) {
			error('403 Access denied');
		}

		if ($_SERVER['REQUEST_METHOD']!='POST') {
			$dat = '';
			head($dat);
			$dat.= '[<a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]
<br clear="ALL">
<script>
var trolls = Array(
	"Hatsune Miku is nothing more than an overated normie whore.",
	"HAHA NIGGER MODS DELETING POSTS THEY CAN\'T TAKE CRITICISM LITERALLY YANDERE DEV OF IMAGE BOARDS",
	"You\'re imposing on muh freedoms of speech! See you in court, buddy.",
	"Being gay is okay.",
	"<span class=\"unkfunc\">&gt;Soooooooooooy</span>",
	"I know where you live.<br>I watch everything you do.<br>I know everything about you and I am coming!",
	"Ooooh muh god! Literally can\'t even!<br>I didn\'t even break any of the rules and I was banned?!",
	"Unrestricted access to the internet is a human right.",
	"get live Child Pizza at http:/jbbait.gov<br>get live Child Pizza at http:/jbbait.gov<br>get live Child Pizza at http:/jbbait.gov<br>get live Child Pizza at http:/jbbait.gov",
	"<span class=\"unkfunc\">&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br></span>"
);
var troll = trolls[Math.floor(Math.random()*trolls.length)];

function updatepview(event=null) {
	var msg = document.getElementById("banmsg");
	var pview = document.getElementById("msgpview");
	pview.innerHTML = troll+msg.value;
}

window.onload = function () {
	var msg = document.getElementById("banmsg");
	msg.insertAdjacentHTML("afterend", \'<br>Preview:<br><table><tbody><tr><td class="reply"><blockquote id="msgpview"></blockquote></td></tr></tbody></table>\');
	msg.oninput = updatepview;
	updatepview();
}
</script>
<style>
fieldset {
	display: inline-block;
}
#bigredbutton {
	font-size: larger;
	background-color: #F00;
	color: white;
	cursor: pointer;
	border-style: outset;
	border-width: 3px;
	outline: none;
	font-weight: bold;
	font-family: Verdana, Tahoma, Arial;
}
#bigredbutton:active:hover {
	border-style: inset;
}
#days {
	width: 4em; 
}
</style>
<fieldset class="menu"><legend>Ban User</legend>
	<form action="'.$this->config['PHP_SELF'].'" method="POST">
		<input type="hidden" name="mode" value="module">
		<input type="hidden" name="load" value="mod_adminban">
		<label>Global?<input type="checkbox" name="global"></label> <small>(Check this box if you want to rangeban)</small><br>
		<label>IP Addr:<input type="text" name="ip" value="'.($_GET['ip']??'').'"></label> <small>(Leave blank to use poster IP)</small><br>
		<label>Expires (ex. 1w2d3h4min):<input type="text" id="days" name="duration" value="1d"></label> <small>[Set to 0 (zero) or leave blank for warning]</small><br>
		<label>Private Message:<br>
			<textarea name="privmsg" cols="80" rows="6">No reason given.</textarea></label><br>
		<details'.($_GET['no']??0 ? ' open="open"' : '').'><summary>Public message</summary><blockquote>
			<label>Post No.<input type="number" name="no" min="0" value="'.($_GET['no']??'0').'"></label><br>
			<textarea id="banmsg" name="msg" cols="80" rows="6"><br><br><b class="warning">(USER WAS BANNED FOR THIS POST)</b> <img style= "vertical-align: baseline;" src="'.$this->config['STATIC_URL'].'image/hammer.gif">
</textarea>
		</blockquote></details>
		<center><button type="submit" id="bigredbutton">BAN!</button></center>
	</form>
</fieldset>';
			$unban = $_GET['unban']??'';
			if ($unban) $dat.= '<p class="warning">The user\'s IP is selected, please click [Revoke] to confirm.</p>';
			$dat.= '<form action="'.$this->config['PHP_SELF'].'" method="POST">
	<input type="hidden" name="mode" value="module">
	<input type="hidden" name="load" value="mod_adminban">
	<table class="postlists" width="800">
		<thead>
			<tr><th width="1">Del</th><th>Pattern</th><th>Start Time</th><th>Expires</th><th>Reason</th><tr>
		</thead><tbody>';
			$log = array_map('rtrim', file($this->BANFILE));
			if (!count($log)) {
				$dat.= '<tr><td colspan="5">No active bans.</td></tr>';
			} else {
				for ($i=0; $i<count($log); $i++) {
					list($ip, $starttime, $expires, $reason) = explode(',', $log[$i], 4);
					$dat.= '<tr>
<td align="CENTER"><input type="checkbox" id="del'.$i.'" name="del'.$i.'"'.($unban==$log[$i]?' checked="checked"':'').' value="on"></td>
<td><label for="del'.$i.'">'.$ip.'</label></td>
<td>'.date('Y/m/d H:i:s', $starttime).'</td>
<td>'.date('Y/m/d H:i:s', $expires).'</td>
<td>'.( strlen($reason)>9001 ? substr($reason, 0, 9001).'&hellip;' : $reason ).'</td></tr>';
				}
			}

			$dat .= '<tr><td colspan="5">GLOBAL BANS</td></tr>';

			$log = array_map('rtrim', file($this->config['GLOBAL_BANS']));
			if (!count($log)) {
				$dat.= '<tr><td colspan="5">No active bans.</td></tr>';
			} else {
				for ($i=0; $i<count($log); $i++) {
					list($ip, $starttime, $expires, $reason) = explode(',', $log[$i], 4);
					$dat.= '<tr>
<td align="CENTER"><input type="checkbox" id="delg'.$i.'" name="delg'.$i.'"'.($unban==$log[$i]?' checked="checked"':'').' value="on"></td>
<td><label for="delg'.$i.'">'.$ip.'</label></td>
<td>'.date('Y/m/d H:i:s', $starttime).'</td>
<td>'.date('Y/m/d H:i:s', $expires).'</td>
<td>'.( strlen($reason)>9001 ? substr($reason, 0, 9001).'&hellip;' : $reason ).'</td></tr>';
				}
			}

			$dat.= '</tbody></table><button type="submit">Revoke</button></form><hr>';

			foot($dat);
			echo $dat;
		} else {
			$glog = array_map('rtrim', file($this->config['GLOBAL_BANS']));
			$log = array_map('rtrim', file($this->BANFILE));
			$g = fopen($this->config['GLOBAL_BANS'], 'w');
			$f = fopen($this->BANFILE, 'w');

			$newip = str_replace(",", "&#44;", htmlspecialchars($_POST['ip']??''));
			$no = intval($_POST['no']??0);
			if (!$newip) $newip = $this->_lookupPostIP($no);
			$msg = $_POST['msg']??'';
			if ($newip) {
				$reason = str_replace(",", "&#44;", preg_replace("/[\r\n]/", '', nl2br($_POST['privmsg']??'')));
				if(!$reason) $reason='No reason given.';
				$starttime = $_SERVER['REQUEST_TIME'];
				$duration = $_POST['duration'];
				$durationWeeks = preg_match("/(\d+)w/", $duration, $matchWeeks);
				$durationDays = preg_match("/(\d+)d/", $duration, $matchDays);
				$durationHours = preg_match("/(\d+)h/", $duration, $matchHours);
				$durationMinutes = preg_match("/(\d+)min/", $duration, $matchMinutes);
				$expires = $starttime + ($matchWeeks[0] * 604800) + ($matchDays[0] * 86400) + ($matchHours[0] * 3600) + ($matchMinutes[0] * 60);
				if(isset($_POST["global"])) {
					if($_POST["global"]) {
						fwrite($g, "$newip,$starttime,$expires,$reason\r\n");
					} else {
						fwrite($f, "$newip,$starttime,$expires,$reason\r\n");
					}
				} else {
					fwrite($f, "$newip,$starttime,$expires,$reason\r\n");
				}

				if ($msg) {
					// update post message
					$msg = preg_replace('/[\r\n]/', '', $msg);
					$post = $PIO->fetchPosts($no);
					if (count($post)) {
						$post[0]['com'].= $msg;
						$PIO->updatePost($no, $post[0]);
						$PIO->dbCommit();
						$parentNo = $post[0]['resto'] ? $post[0]['resto'] : $post[0]['no'];
						deleteCache(array($parentNo));
					}
				}
				$AccountIO = PMCLibrary::getAccountIOInstance();
				$AccountIO->valid();
				
				$moderatorUsername = $AccountIO->getUsername();
				$moderatorLevel = $AccountIO->getRoleLevel();
				logtime("Banned $newip for post: $no", $moderatorUsername.' ## '.$moderatorLevel);
			}

			for ($i=0; $i<count($log); $i++) {
				if (($_POST["del".$i]??'')=='on')
					continue;
				if ($log[$i]==$newip)
					continue;
				fwrite($f, $log[$i]."\r\n");
			}
			for ($i=0; $i<count($glog); $i++) {
				if (($_POST["delg".$i]??'')=='on')
					continue;
				if ($glog[$i]==$newip)
					continue;
				fwrite($g, $glog[$i]."\r\n");
			}

			fclose($g);
			fclose($f);

			updatelog();
			redirect('back', 0);
		}
	}
}
