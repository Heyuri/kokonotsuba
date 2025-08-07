<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	private readonly string $BANFILE;
	private readonly string $GLOBAL_BANS;
	private readonly string $DEFAULT_BAN_MESSAGE;
	private readonly string $myPage;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_BAN');
    }

	public function getName(): string {
		return 'Admin ban tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->myPage = $this->getModulePageURL();
		$this->BANFILE = $this->moduleContext->board->getBoardStoragePath() . 'bans.log.txt';
		$this->GLOBAL_BANS = getBackendGlobalDir() . $this->getConfig('GLOBAL_BANS');
		$this->DEFAULT_BAN_MESSAGE = $this->getConfig('DEFAULT_BAN_MESSAGE');

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);
	}

	public function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a href="' . $this->myPage . '">Manage bans</a></li>';
	}

	public function onRenderPostAdminControls(string &$modfunc, array &$post) {
		$ip = htmlspecialchars($post['host']) ?? '';

		$modulePageUrl = $this->getModulePageURL([
			'post_uid' => $post['post_uid'],
			'ip' => $ip
		]);

		$modfunc .= '<span class="adminFunctions adminBanFunction">[<a href="' . $modulePageUrl . '" title="Ban">B</a>]</span> ';
	}

	public function ModulePage() {
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
				'{$MODULE_URL}' => $this->myPage,
				'{$ROWS}' => $this->convertBanLogToRows($log, 'del')
			],
			[
				'{$TITLE}' => 'Global bans',
				'{$TABLE_ID}' => 'globalBanTable',
				'{$MODULE_URL}' => $this->myPage,
				'{$ROWS}' => $this->convertBanLogToRows($glog, 'delg')
			]
		];

		$post_uid = $_GET['post_uid'] ?? '';
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($post_uid);
		

		$templateData = [
			'{$POST_NUMBER}' => $postNumber ? htmlspecialchars($postNumber) : "No post selected.  ",
			'{$POST_UID}' => htmlspecialchars($_GET['post_uid'] ?? ''),
			'{$IP}' => htmlspecialchars($_GET['ip'] ?? ''),
			'{$DEFAULT_BAN_MESSAGE}' => $this->DEFAULT_BAN_MESSAGE,
			'{$MODULE_URL}' => $this->myPage,
			'{$TABLES}' => $tables,
		];

		$adminBanManagePageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ADMIN_BAN_MANAGEMENT_PAGE', $templateData);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminBanManagePageHtml], true);
	}

	private function sortBansByNewest(array $bans): array {
		usort($bans, function($a, $b) {
			[$ipA, $startA] = explode(',', $a, 3);
			[$ipB, $startB] = explode(',', $b, 3);
			return intval($startB) - intval($startA); // newest to oldest
		});
		return $bans;
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

		$post = $this->moduleContext->postRepository->getPostByUid($postUid);

		// Process the ban form (add to log, update post if public, etc.)
		$this->processBanForm($reasonFromRequest, $newIp, $duration, $makePublic, $publicBanMessageHTML, $isGlobal, $post);
	
		// Log the ban action
		$this->logBanAction($newIp, $duration, $isGlobal, $post);


		// Redirect after processing
		redirect($_SERVER['HTTP_REFERER']);
		exit;
	}

	private function logBanAction(string $newIp, string $duration, bool $isGlobal, array|false $post) {
		// Build the action string based on whether it's a global ban or related to a post
		$actionString = $this->buildActionString($newIp, $duration, $isGlobal, $post);

		// Log the action with the board UID
		$boardUid = $this->moduleContext->board->getBoardUID();

		// Log globally if its a global ban 
		if($isGlobal) {
			$boardUid = -1;
		}

		$this->moduleContext->actionLoggerService->logAction($actionString, $boardUid);
	}

	private function buildActionString(string $newIp, 
		string $duration, 
		bool $isGlobal, 
		array|false $post): string {
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
		if ($post) {
			// post number
			$postNumber = $post['no'];

			// board uid of the post
			$boardUID = $post['boardUID'];
			
			// fetch the board from memory
			$board = searchBoardArrayForBoard($post['boardUID']);
			
			// board title
			$boardTitle = $board->getBoardTitle();	

			if(!empty($postNumber)) { 
				$actionString .= " for post No.$postNumber";
			
				if($isGlobal) {
					$actionString .= " $boardTitle ($boardUID)";
				}
			}
		}

		return $actionString;
	}


	private function processBanForm(
		string $reasonFromRequest, 
		string $newip,  
		string $duration, 
		string $makePublic, 
		string $publicBanMessageHTML, 
		bool $isGlobal,
		array|false $post = []): void {
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
			if ($post) {
				
				$post['com'] .= $publicBanMessageHTML;
				
				$this->moduleContext->postRepository->updatePost($post['post_uid'], $post);
				
				$board = searchBoardArrayForBoard($post['boardUID']);
				
				$board->rebuildBoard();
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

}