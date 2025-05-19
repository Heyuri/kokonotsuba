<?php

class mod_adminban extends moduleHelper {
	private string $BANFILE = '';
	private string $GLOBAL_BANS = '';
	private string $BANIMG = '';
	private string $DEFAULT_BAN_MESSAGE = '';
	private string $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);

		$this->BANFILE = $this->board->getBoardStoragePath() . 'bans.log.txt';
		$this->BANIMG = $this->config['STATIC_URL'] . "image/banned.jpg";
		$this->GLOBAL_BANS = getBackendGlobalDir().$this->config['GLOBAL_BANS'];
		$this->DEFAULT_BAN_MESSAGE = $this->config['DEFAULT_BAN_MESSAGE'];
		$this->mypage = $this->getModulePageURL();

		@touch($this->BANFILE);
		@touch($this->GLOBAL_BANS);
	}

	public function getModuleName() {
		return __CLASS__ . ' : K! Admin Ban';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2025';
	}

	public function autoHookRegistBegin() {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';

		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		$this->handleBan($log, $ip, $this->BANFILE);
		$this->handleBan($glog, $ip, $this->GLOBAL_BANS, true);
	}

	public function autoHookLinksAboveBar(&$link, $pageId, $level) {
		if ($level->isAtLeast($this->config['AuthLevels']['CAN_BAN']) && $pageId === 'admin') {
			$link .= '<li class="adminNavLink"><a href="' . $this->mypage . '">Manage bans</a></li>';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();

		$ip = htmlspecialchars($post['host']) ?? '';
		$delMode = $_REQUEST['mode'] ?? '';

		if ($roleLevel->isAtLeast($this->config['AuthLevels']['CAN_BAN'])) {
			$modfunc .= '<span class="adminFunctions adminBanFunction">[<a href="' . $this->mypage . '&post_uid=' . htmlspecialchars($post['post_uid']) . '&ip=' . htmlspecialchars($ip) . '" title="Ban">B</a>]</span> ';
		}
		if (!empty($ip) && $roleLevel->isAtLeast($this->config['AuthLevels']['CAN_VIEW_IP_ADDRESSES']) && $delMode !== 'managePosts') {
			$modfunc .= '<span class="adminFunctions host">[Host: <a href="?mode=managePosts&host=' . htmlspecialchars($ip) . '">' . htmlspecialchars($ip) . '</a>]</span>';
		}
	}

	public function ModulePage() {
		$globalHTML = new globalHTML($this->board);
		$softErrorHandler = new softErrorHandler($globalHTML);
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_BAN']);

		$PIO = PIOPDO::getInstance();

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$banAction = $_POST['adminban-action'] ?? '';
			switch($banAction) {
				case 'add-ban':
					$this->processBanForm();
					break;
				case 'delete-ban':
					$this->processBanDeletions();
					break;
			}
		}

		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];
		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];

		$log = $this->sortBansByNewest($log);
		$glog = $this->sortBansByNewest($glog);

		$tables = [
			[
				'{$TITLE}' => 'Local bans',
				'{$TABLE_ID}' => 'localBanTable',
				'{$MODULE_URL}' => $this->mypage,
				'{$ROWS}' => $this->convertBanLogToRows($log, 'del')
			],
			[
				'{$TITLE}' => 'Global bans',
				'{$TABLE_ID}' => 'globalBanTable',
				'{$MODULE_URL}' => $this->mypage,
				'{$ROWS}' => $this->convertBanLogToRows($glog, 'delg')
			]
		];

		$post_uid = $_GET['post_uid'] ?? '';
		$postNumber = $PIO->resolvePostNumberFromUID($post_uid);
		

		$templateData = [
			'{$POST_NUMBER}' => $postNumber ? htmlspecialchars($postNumber) : "No post selected.  ",
			'{$POST_UID}' => htmlspecialchars($_GET['post_uid'] ?? ''),
			'{$IP}' => htmlspecialchars($_GET['ip'] ?? ''),
			'{$DEFAULT_BAN_MESSAGE}' => htmlspecialchars($this->DEFAULT_BAN_MESSAGE),
			'{$MODULE_URL}' => $this->mypage,
			'{$TABLES}' => $tables,
		];

		$adminBanManagePageHtml = $this->adminPageRenderer->ParseBlock('ADMIN_BAN_MANAGEMENT_PAGE', $templateData);

		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminBanManagePageHtml], true);
	}

	private function sortBansByNewest(array $bans): array {
		usort($bans, function($a, $b) {
			[$ipA, $startA] = explode(',', $a, 3);
			[$ipB, $startB] = explode(',', $b, 3);
			return intval($startB) - intval($startA); // newest to oldest
		});
		return $bans;
	}

	private function handleBan(&$log, $ip, $banFile) {
		$htmlOutput = '';
		foreach ($log as $i => $entry) {
			list($banip, $starttime, $expires, $reason) = explode(',', $entry, 4);
			if (strpos($ip, gethostbyname($banip)) !== false) {
				$htmlOutput .= $this->drawBanPage($starttime, $expires, $reason, $this->BANIMG);
				if ($_SERVER['REQUEST_TIME'] > intval($expires)) {
					unset($log[$i]);
					file_put_contents($banFile, implode(PHP_EOL, $log));
				}
				die($htmlOutput);
			}
		}
		
	}

	private function processBanForm() {
		$PIO = PIOPDO::getInstance();

		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		$reasonFromRequest = $_POST['privmsg'] ?? '';
		$newip = $_POST['ip'] ?? '';
		$reason = $reasonFromRequest ?: "No reason given.";
		$duration = $_POST['duration'] ?? '0';
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

		if ($makePublic) {
			$post_uid = $_POST['post_uid'] ?? 0;
			$post = $PIO->fetchPosts($post_uid)[0];
			$post['com'] .= $publicBanMessageHTML;
			$PIO->updatePost($post_uid, $post);
			$this->board->rebuildBoard();
		}

		redirect($_SERVER['HTTP_REFERER']);
		exit;
	}

	private function processBanDeletions() {
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];
		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];

		//lazy hack
		$log = $this->sortBansByNewest($log);
		$glog = $this->sortBansByNewest($glog);

		$newLocalLog = [];
		$newGlobalLog = [];

		foreach ($log as $i => $entry) {
			if (!isset($_POST["del$i"]) || $_POST["del$i"] !== 'on') {
				$newLocalLog[] = $entry;
			}
		}

		foreach ($glog as $i => $entry) {
			if (!isset($_POST["delg$i"]) || $_POST["delg$i"] !== 'on') {
				$newGlobalLog[] = $entry;
			}
		}

		file_put_contents($this->BANFILE, implode(PHP_EOL, $newLocalLog));
		file_put_contents($this->GLOBAL_BANS, implode(PHP_EOL, $newGlobalLog));
		redirect($_SERVER['HTTP_REFERER']);
	}

	private function calculateBanDuration($duration) {
		preg_match_all('/(\d+)([wdhm])/', $duration, $matches, PREG_SET_ORDER);
		$seconds = 0;

		foreach ($matches as $match) {
			$value = intval($match[1]);
			switch ($match[2]) {
				case 'w': $seconds += $value * 604800; break;
				case 'd': $seconds += $value * 86400; break;
				case 'h': $seconds += $value * 3600; break;
				case 'm': $seconds += $value * 60; break;
			}
		}

		return $seconds;
	}

	private function convertBanLogToRows(array $bans, string $prefix): array {
		$rows = [];
		foreach ($bans as $i => $ban) {
			list($ip, $start, $expires, $reason) = explode(',', $ban, 4);
			$rows[] = [
				'{$CHECKBOX_NAME}' => $prefix . $i,
				'{$IP}' => htmlspecialchars($ip),
				'{$START}' => date('Y/m/d H:i:s', intval($start)),
				'{$EXPIRES}' => date('Y/m/d H:i:s', intval($expires)),
				'{$REASON}' => htmlspecialchars($reason),
			];
		}
		return $rows;
	}

	private function drawBanPage($starttime, $expires, $reason, $banImage = '') {
		$isExpired = ($_SERVER['REQUEST_TIME'] > intval($expires));

		$templateValues = [
				'{$RETURN_URL}'	=> $this->config['PHP_SELF2'] ?? './',
				'{$BAN_TYPE}'		=> ($starttime == $expires) ? 'warned' : 'banned',
				'{$REASON}'			=> $reason,
				'{$BAN_IMAGE}'		=> $banImage,
				'{$BAN_DETAIL}'	=> $isExpired
					? 'Now that you have seen this message, you can post again.'
					: "<p>Your ban was filed on " . date('Y/m/d \a\t H:i:s', $starttime) .
						" and expires on " . date('Y/m/d \a\t H:i:s', $expires) . ".</p>"
		];

		return $this->adminPageRenderer->ParsePage('BAN_PAGE', $templateValues);
	}

}
