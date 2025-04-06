<?php

class mod_adminban extends moduleHelper {
	private string $BANFILE = '';
	private string $GLOBAL_BANS = '';
	private string $BANIMG = '';
	private string $DEFAULT_BAN_MESSAGE = '';
	private string $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);
		// Ensure required configuration keys exist
		$this->BANFILE = $this->board->getBoardStoragePath() . 'bans.log.txt';
		$this->BANIMG = $this->config['STATIC_URL'] . "image/banned.jpg";
		$this->GLOBAL_BANS = getBackendGlobalDir().$this->config['GLOBAL_BANS'];
		$this->DEFAULT_BAN_MESSAGE = $this->config['DEFAULT_BAN_MESSAGE'];

		$this->mypage = $this->getModulePageURL();

		// Ensure ban files exist
		@touch($this->BANFILE);
		@touch($this->GLOBAL_BANS);
	}

	public function getModuleName() {
		return __CLASS__ . ' : K! Admin Ban';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBegin() {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';

		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		$this->handleBan($log, $ip, $this->BANFILE);
		$this->handleBan($glog, $ip, $this->GLOBAL_BANS, true);
	}

	private function handleBan(&$log, $ip, $banFile, $isGlobal = false) {
		$globalHTML = new globalHTML($this->board);
		foreach ($log as $i => $entry) {
			list($banip, $starttime, $expires, $reason) = explode(',', $entry, 4);
			if (strpos($ip, gethostbyname($banip)) !== false) { # PHP7 compatibility. change for PHP8: if (str_contains($ip, gethostbyname($banip))) {
				// Render the ban page
				$dat = '';
				$globalHTML->head($dat);
				$globalHTML->drawBanPage($dat, $banip, $starttime, $expires, $reason, $this->BANIMG);
				$globalHTML->foot($dat);
				// If ban expired, remove it
				if ($_SERVER['REQUEST_TIME'] > intval($expires)) {
					unset($log[$i]);
					file_put_contents($banFile, implode(PHP_EOL, $log));
				}

				die($dat);
			}
		}
	}



	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		$staffSession = new staffAccountFromSession;

		if ($staffSession->getRoleLevel() >= $this->config['roles']['LEV_MODERATOR'] && $pageId === 'admin') {
			$link .= '[<a href="' . $this->mypage . '">Manage bans</a>] ';
		}
	}
	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();
		
		$ip = htmlspecialchars($post['host']) ?? '';
		$delMode = $_REQUEST['admin'] ?? '';
		if ($roleLevel >= $this->config['AuthLevels']['CAN_BAN']) $modfunc .= '<span class="adminBanFunction">[<a href="' . $this->mypage . '&post_uid=' . $post['post_uid'] . '&ip=' . $ip . '" title="Ban">B</a>]</span> ';
		if (!empty($ip) && $roleLevel >= $this->config['AuthLevels']['CAN_VIEW_IP_ADDRESSES'] && $delMode !== 'del') $modfunc .= '<span class="host">[HOST: <a href="?mode=admin&admin=del&host=' . $ip . '">' . $ip . '</a>]</span>';
	}

	public function ModulePage() {
		$softErrorHandler = new softErrorHandler($this->board);
		$globalHTML = new globalHTML($this->board);
		
		
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_BAN']);
		
		$dat = '';
		$globalHTML->head($dat);
		
		$dat .= '			<script>
var trolls = Array(
	"Hatsune Miku is nothing more than an overated normie whore.",
	"HAHA NIGGER MODS DELETING POSTS THEY CAN\'T TAKE CRITICISM LITERALLY YANDERE DEV OF IMAGE BOARDS",
	"You\'re imposing on muh freedoms of speech! See you in court, buddy.",
	"Being gay is okay.",
	"<span class=\"unkfunc\">&gt;Soooooooooooy</span>",
	"I know where you live.<br>I watch everything you do.<br>I know everything about you and I am coming!",
	"Ooooh muh god! qLiterally can\'t even!<br>I didn\'t even break any of the rules and I was banned?!",
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
	msg.insertAdjacentHTML("afterend", \'<br>Preview:<br><table><tbody><tr><td class="reply"><div id="msgpview" class="comment"></div></td></tr></tbody></table>\');
	msg.oninput = updatepview;
	updatepview();
}
			</script>';
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$banAction = $_POST['adminban-action'] ?? '';
			switch($banAction) {
				case 'add-ban':
					$this->processBanForm($dat);
				break;
				case 'delete-ban':
					$this->processBanDeletions();
				break;
			}
		} else {
			$globalHTML->drawBanManagementPage($dat, $this->BANFILE, $this->mypage, $this->DEFAULT_BAN_MESSAGE, $this->GLOBAL_BANS);
		}
		$globalHTML->foot($dat);
		echo $dat;
	}

	private function processBanForm() {
		$PIO = PIOPDO::getInstance();

		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		$reasonFromRequest = $_POST['privmsg'] ?? '';
		$newip = htmlspecialchars($_POST['ip'] ?? '');
		$reason = htmlspecialchars($reasonFromRequest ? $reasonFromRequest : "No reason given.");
		$duration = htmlspecialchars($_POST['duration'] ?? '0');
		$starttime = $_SERVER['REQUEST_TIME'];
		$makePublic = $_POST['public'] ?? '';
		$publicBanMessageHTML = $_POST['banmsg'] ?? '';

		$expires = $starttime + $this->calculateBanDuration($duration);

		if (!empty($newip)) {
			$banEntry = "{$newip},{$starttime},{$expires},{$reason}";
			if (!empty($_POST['global'])) {
				$glog[] = $banEntry;
				file_put_contents($this->GLOBAL_BANS, implode(PHP_EOL, $glog));
			} else {
				$log[] = $banEntry;
				file_put_contents($this->BANFILE, implode(PHP_EOL, $log));
			}
		}

		if($makePublic) {
			$post_uid = $_POST['post_uid'] ?? 0;
			$post = $PIO->fetchPosts($post_uid)[0];
			
			$post['com'] .= $publicBanMessageHTML;
			$PIO->updatePost($post_uid, $post);
			$this->board->rebuildBoard();
		}

		redirect($_SERVER['HTTP_REFERER']);
		exit;
	}

	private function calculateBanDuration($duration) {
		preg_match_all('/(\d+)([wdhm])/', $duration, $matches, PREG_SET_ORDER);
		$seconds = 0;

		foreach ($matches as $match) {
			$value = intval($match[1]);
			switch ($match[2]) {
				case 'w':
					$seconds += $value * 604800;
					break;
				case 'd':
					$seconds += $value * 86400;
					break;
				case 'h':
					$seconds += $value * 3600;
					break;
				case 'm':
					$seconds += $value * 60;
					break;
			}
		}

		return $seconds;
	}
	
	private function processBanDeletions() {
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];
		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];

		$newLocalLog = [];
		$newGlobalLog = [];

		foreach ($log as $i => $entry) {
			if (isset($_POST["del$i"]) && $_POST["del$i"] === 'on') {
				continue;
			}
			$newLocalLog[] = $entry;
		}

		foreach ($glog as $i => $entry) {
			if (isset($_POST["delg$i"]) && $_POST["delg$i"] === 'on') {
				continue;
			}
			$newGlobalLog[] = $entry;
		}

		// Write the updated logs back to the respective files
		file_put_contents($this->BANFILE, implode(PHP_EOL, $newLocalLog));
		file_put_contents($this->GLOBAL_BANS, implode(PHP_EOL, $newGlobalLog));
		redirect($_SERVER['HTTP_REFERER']);
	}


}
