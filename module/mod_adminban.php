<?php

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

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
		$boardIO = boardIO::getInstance();
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();

		$ip = htmlspecialchars($post['host']) ?? '';
		$delMode = $_REQUEST['mode'] ?? '';

		$listedBoards = $boardIO->getAllListedBoardUIDs();
		$boardList = implode('+', $listedBoards);

		if ($roleLevel->isAtLeast($this->config['AuthLevels']['CAN_BAN'])) {
			$modfunc .= '<span class="adminFunctions adminBanFunction">[<a href="' . $this->mypage . '&post_uid=' . htmlspecialchars($post['post_uid']) . '&ip=' . htmlspecialchars($ip) . '" title="Ban">B</a>]</span> ';
		}
		if (!empty($ip) && $roleLevel->isAtLeast($this->config['AuthLevels']['CAN_VIEW_IP_ADDRESSES']) && $delMode !== 'managePosts') {
			$modfunc .= '<span class="adminFunctions host">[Host: <a href="?mode=managePosts&board='. $boardList .'&ip_address=' . htmlspecialchars($ip) . '">' . htmlspecialchars($ip) . '</a>]</span>';
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
					$this->handleBanAddition();
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
			'{$DEFAULT_BAN_MESSAGE}' => $this->DEFAULT_BAN_MESSAGE,
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

	private function handleBan(&$log, $ip, $banFile, $isGlobalBan = false) {
		$htmlOutput = '';
		
		// Loop through each ban entry in the log
		foreach ($log as $i => $entry) {
			// Each entry is expected to be in the format: ip,starttime,expires,reason
			[$banip, $starttime, $expires, $reason] = explode(',', $entry, 4);

			// For global bans, match by prefix (e.g. "127.0.0.")
			// For regular bans, match the full IP exactly
			$match = $isGlobalBan ? (strpos($ip, $banip) === 0) : ($ip === $banip);
			
			if ($match) {
				// If IP matches, show ban page
				$htmlOutput .= $this->drawBanPage($starttime, $expires, $reason, $this->BANIMG);
				
				// If the ban has expired, remove it from the log and save
				if ($_SERVER['REQUEST_TIME'] > intval($expires)) {
					unset($log[$i]);
					file_put_contents($banFile, implode(PHP_EOL, $log));
				}
				
				// Stop further execution and show the ban page
				die($htmlOutput);
			}
		}
	}

	private function handleBanAddition() {
		// Extract data from the request
		$reasonFromRequest = $_POST['privmsg'] ?? '';
		$newIp = $_POST['ip'] ?? '';
		$duration = $_POST['duration'] ?? '0';
		$makePublic = $_POST['public'] ?? '';
		$publicBanMessageHTML = $_POST['banmsg'] ?? '';
		$postUid = intval($_POST['post_uid']) ?? null;
		$isGlobal = isset($_POST['global']);  // Check if global ban is selected

		// Process the ban form (add to log, update post if public, etc.)
		$this->processBanForm($reasonFromRequest, $newIp, $duration, $makePublic, $publicBanMessageHTML, $isGlobal, $postUid);
	
		// Log the ban action
		$this->logBanAction($newIp, $duration, $isGlobal, $postUid);


		// Redirect after processing
		redirect($_SERVER['HTTP_REFERER']);
		exit;
	}

	private function logBanAction($newIp, $duration, $isGlobal, $postUid) {
		// Get action logger and PIO instance
		$actionLogger = actionLogger::getInstance();
		$PIO = PIOPDO::getInstance();
		$boardIO = boardIO::getInstance();

		if($postUid) {
			$postData = $PIO->fetchPosts($postUid)[0];

			$boardUid = $postData['boardUID'];

			$postBoard = $boardIO->getBoardByUID($boardUid);

			$boardIdentifier = $postBoard->getBoardIdentifier();

		} else {
			$boardUid = $this->board->getBoardUID();

			$boardIdentifier = $this->board->getBoardTitle();
		}

		// Build the action string based on whether it's a global ban or related to a post
		$actionString = $this->buildActionString($newIp, $duration, $isGlobal, $postUid, $PIO, $boardIdentifier, $boardUid);


		// Log in the global scope if its a global ban
		if($isGlobal) {
			$boardUid = GLOBAL_BOARD_UID;
		}

		$actionLogger->logAction($actionString, $boardUid);
	}

	private function buildActionString(string $newIp, string $duration, bool $isGlobal, ?int $postUid, mixed $PIO, string $boardIdentifier, int $boardUid): string {
		// Initial action string (basic information about the ban)
		$actionString = "Banned $newIp for $duration";

		// If it's a global ban, update the action string
		if ($isGlobal) {
			$actionString = "Banned $newIp from all boards for $duration";
		}

		// Log it as a warn if the duration string is zero
		if($duration == '0') {
			$actionString = "Warned $newIp ";
		}


		// If the ban is related to a specific post, add post info to the action string
		if ($postUid) {
			$postNumber = $PIO->resolvePostNumberFromUID($postUid);
			if(!empty($postNumber)) { 
				$actionString .= " for post: $postNumber /$boardIdentifier/ ($boardUid)";
			}
		}

		return $actionString;
	}


	private function processBanForm(string $reasonFromRequest, 
		string $newip, 
		string $duration, 
		string $makePublic, 
		string $publicBanMessageHTML, 
		bool $isGlobal,
		?int $postUid = 0): void {
		$PIO = PIOPDO::getInstance();

		// Load ban logs
		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		// Set defaults if not provided
		$reason = $reasonFromRequest ?: "No reason given.";
		$starttime = $_SERVER['REQUEST_TIME']; // This remains from the server, no change
		$expires = $starttime + $this->calculateBanDuration($duration);

		// Replace all newlines with literal <br /> tags, and remove the actual newlines
		$reason = str_replace(["\r\n", "\n", "\r"], '<br />', $reason);

		// replace comma so it doesnt break the explode
		$reason = str_replace(',', '&#44;', $reason);

		if (!empty($newip)) {
			// Create the ban entry
			$banEntry = "{$newip},{$starttime},{$expires},{$reason}";
			if ($isGlobal) {  // Global ban
				$glog[] = $banEntry;
				file_put_contents($this->GLOBAL_BANS, implode(PHP_EOL, $glog));
			} else {  // Local ban
				$log[] = $banEntry;
				file_put_contents($this->BANFILE, implode(PHP_EOL, $log));
			}
		}

		if ($makePublic) {
			if ($postUid) {
				// Fetch and update the post with the ban message
				$post = $PIO->fetchPosts($postUid)[0];
				$post['com'] .= $publicBanMessageHTML;
				$PIO->updatePost($postUid, $post);
				$this->board->rebuildBoard();
			}
		}

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
		exit;
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
				'{$REASON}' => $reason,
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
