<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\request\redirect;

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
		$this->myPage = $this->getModulePageURL([], true, true);
		$this->BANFILE = $this->moduleContext->board->getBoardStoragePath() . 'bans.log.txt';
		$this->GLOBAL_BANS = getBackendGlobalDir() . $this->getConfig('GLOBAL_BANS');
		$this->DEFAULT_BAN_MESSAGE = $this->getConfig('DEFAULT_BAN_MESSAGE');

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post, false);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post, true);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderPostWidget($widgetArray, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);
	}

	private function onRenderPostAdminControls(string &$modfunc, array &$post, bool $noScript): void {
		$ip = htmlspecialchars($post['host']) ?? '';

		$modulePageUrl = $this->generateBanUrl($ip, $post['post_uid']);

		$modfunc .= generateModerateButton(
			$modulePageUrl, 
			'B', 
			'Ban this user', 
			'adminBanFunction',
			$noScript
		);
	}

	private function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a href="' . $this->myPage . '">Manage bans</a></li>';
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
		// generate ban url
		$banUrl = $this->generateBanUrl($post['host'], $post['post_uid']);

		// build the widget entry for deletion
		$banWidget = $this->buildWidgetEntry(
			$banUrl, 
			'ban', 
			'Ban', 
			''
		);
		
		// add the widget to the array
		$widgetArray[] = $banWidget;
	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// get ban template
		$banTemplate = $this->generateBanJsTemplate();

		// append ban template to header
		$moduleHeader .= $banTemplate;

		// include ban js
		$this->includeScript('ban.js', $moduleHeader);
	}

	private function generateBanJsTemplate(): string {
		// template placeholders
		// Leave most values blank/zero - the js will fill these values/fields
		$templateValues = $this->getBanFormTemplateValues(
			0,
			0,
			'',
			$this->DEFAULT_BAN_MESSAGE
		);

		// generate an empty ban form (parse block)
		$banFormHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ADMIN_BAN_FORM', $templateValues);

		// generate template
		// wraps content in HTML <template> tags
		$banTemplate = $this->generateTemplate('banFormTemplate', $banFormHtml);

		// return the HTML template
		return $banTemplate;
	}
	
	private function generateBanUrl(string $ipAddress, int $postUid): string {
		// build parameters for the url
		$params = [
			'ip' => $ipAddress,
			'post_uid' => $postUid
		];

		// generate the url
		$deletionUrl = $this->getModulePageURL($params, false, true);

		// return the url
		return $deletionUrl;
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

		$postUid = $_GET['post_uid'] ?? 0;
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);
		
		// IP address from GET
		$ipAddress = $_GET['ip'] ?? '';

		$templateData = $this->getBanFormTemplateValues(
			$postNumber,
			$postUid,
			$ipAddress,
			$this->DEFAULT_BAN_MESSAGE
		);

		// add tables template value 
		// (exclusive to the full ban page)
		// which are the Ban/Mute tables
		$templateData['{$TABLES}'] = $tables;


		$adminBanManagePageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ADMIN_BAN_MANAGEMENT_PAGE', $templateData);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminBanManagePageHtml], true);
	}

	private function getBanFormTemplateValues(
		int $postNumber, 
		int $postUid, 
		string $ipAddress, 
		string $defaultBanMessage
	): array {
		// ban form template values
		return [
			'{$POST_NUMBER}' => htmlspecialchars($postNumber),
			'{$POST_UID}' => htmlspecialchars($postUid),
			'{$IP}' => htmlspecialchars($ipAddress),
			'{$DEFAULT_BAN_MESSAGE}' => $defaultBanMessage,
			'{$MODULE_URL}' => $this->myPage,
		];
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

		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

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
				
				// parameters to update in the query
				$updatePostParameters = [
					'com' => $post['com']
				];

				$this->moduleContext->postRepository->updatePost($post['post_uid'], $updatePostParameters);
				
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
		// use regex to find all occurrences of number + unit (e.g. 1d, 2h, etc.)
		preg_match_all('/(\d+(\.\d+)?)([ywdhm])/', $duration, $matches, PREG_SET_ORDER);
		
		// total ban duration in seconds
		$seconds = 0;

		// loop through matches and calculate the total duration in seconds
		foreach ($matches as $match) {
			// cast to float for decimal support (e.g. 1.5d for 1 day and 12 hours)
			$value = floatval($match[1]);
			
			// now match the unit and convert to seconds
			switch ($match[3]) {
				case 'y': $seconds += $value * 31536000; break;
				case 'm': $seconds += $value * 2597120; break;
				case 'w': $seconds += $value * 604800; break;
				case 'd': $seconds += $value * 86400; break;
				case 'h': $seconds += $value * 3600; break;
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