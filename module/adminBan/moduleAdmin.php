<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanFileOperationsTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\getPageFromRequest;
use function Kokonotsuba\libraries\html\pageToOffset;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
	use AuditableTrait;
	use BanFileOperationsTrait;

	private readonly string $DEFAULT_BAN_MESSAGE;
	private readonly string $modulePageUrl;
	private readonly int $bansPerPage;

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
		$this->modulePageUrl = $this->getModulePageURL([], false, true);
		$this->DEFAULT_BAN_MESSAGE = $this->getConfig('DEFAULT_BAN_MESSAGE');
		$this->bansPerPage = (int)$this->getConfig('ADMIN_PAGE_DEF', 100);

		$this->registerPostControlPair('onRenderPostAdminControls');
		$this->registerLinksAboveBarHook(_T('admin_nav_ban_title'), $this->modulePageUrl, _T('admin_nav_ban'));
		$this->registerSimplePostWidget(
			fn(Post $post) => $this->generateBanUrl($post->getIp(), $post->getUid()),
			'ban',
			'Ban'
		);
		$this->registerAdminHeaderHook('onGenerateModuleHeader');
	}

	private function onRenderPostAdminControls(string &$modfunc, Post &$post, bool $noScript): void {
		$ip = htmlspecialchars($post->getIp()) ?? '';

		$modulePageUrl = $this->generateBanUrl($ip, $post->getUid());

		$modfunc .= generateModerateButton(
			$modulePageUrl, 
			'B', 
			'Ban this user', 
			'adminBanFunction',
			$noScript
		);
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
			'ipAddress' => $ipAddress,
			'postUid' => $postUid
		];

		// generate the url
		$deletionUrl = $this->getModulePageURL($params, false, true);

		// return the url
		return $deletionUrl;
	}

	public function ModulePage() {
		if ($this->moduleContext->request->isPost()) {
			requirePostWithCsrf($this->moduleContext->request);

			$banAction = $this->moduleContext->request->getParameter('adminban-action', 'POST', '');
			switch($banAction) {
				case 'add-ban':
					$this->handleBanAddition();
					break;
				case 'delete-ban':
					$this->processBanDeletions();
					break;
			}
		}

		$log = $this->sortBansByNewest($this->readBanLog($this->getBanFilePath()));
		$glog = $this->sortBansByNewest($this->readBanLog($this->getGlobalBanFilePath()));

		// Pagination
		$request = $this->moduleContext->request;

		$localPage = getPageFromRequest($request, 'lpage');
		$globalPage = getPageFromRequest($request, 'gpage');

		$localOffset = pageToOffset($localPage, $this->bansPerPage);
		$globalOffset = pageToOffset($globalPage, $this->bansPerPage);

		$localPageEntries = array_slice($log, $localOffset, $this->bansPerPage);
		$globalPageEntries = array_slice($glog, $globalOffset, $this->bansPerPage);

		$localPager = drawPager($this->bansPerPage, count($log), $this->modulePageUrl, $request, 'lpage');
		$globalPager = drawPager($this->bansPerPage, count($glog), $this->modulePageUrl, $request, 'gpage');

		$tables = [
			[
				'{$TITLE}' => 'Local bans',
				'{$TABLE_ID}' => 'localBanTable',
				'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
				'{$ROWS}' => $this->convertBanLogToRows($localPageEntries, 'del', $localOffset),
				'{$PAGER}' => $localPager
			],
			[
				'{$TITLE}' => 'Global bans',
				'{$TABLE_ID}' => 'globalBanTable',
				'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
				'{$ROWS}' => $this->convertBanLogToRows($globalPageEntries, 'delg', $globalOffset),
				'{$PAGER}' => $globalPager
			]
		];

		$postUid = $this->moduleContext->request->getParameter('postUid', 'GET', 0);
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);
		
		// IP address from GET
		$ipAddress = $this->moduleContext->request->getParameter('ipAddress', 'GET', '');

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
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
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
		$reasonFromRequest = $this->moduleContext->request->getParameter('privmsg', 'POST', '');
		$newIp = $this->moduleContext->request->getParameter('ipAddress', 'POST', '');
		$duration = $this->moduleContext->request->getParameter('duration', 'POST', '0');
		$makePublic = $this->moduleContext->request->getParameter('public', 'POST', '');
		$publicBanMessageHTML = $this->moduleContext->request->getParameter('banmsg', 'POST', '');
		$postUid = intval($this->moduleContext->request->getParameter('postUid', 'POST', 0));
		$isGlobal = $this->moduleContext->request->hasParameter('global', 'POST');  // Check if global ban is selected

		/** @var Post|false $post */
		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

		// Process the ban form (add to log, update post if public, etc.)
		$this->processBanForm($reasonFromRequest, $newIp, $duration, $makePublic, $publicBanMessageHTML, $isGlobal, $post);
	
		// Log the ban action
		$this->logBanAction($newIp, $duration, $isGlobal, $post);


		// Redirect after processing
		redirect($this->moduleContext->request->getReferer());
		exit;
	}

	private function logBanAction(string $newIp, string $duration, bool $isGlobal, Post|false $post) {
		// Build the action string based on whether it's a global ban or related to a post
		$actionString = $this->buildActionString($newIp, $duration, $isGlobal, $post);

		// Log the action with the board UID
		$boardUid = $this->moduleContext->board->getBoardUID();

		// Log globally if its a global ban 
		if($isGlobal) {
			$boardUid = -1;
		}

		$this->logAction($actionString, $boardUid);
	}

	private function buildActionString(string $newIp, 
		string $duration, 
		bool $isGlobal, 
		Post|false $post): string {
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
			$postNumber = $post->getNumber();

			// board uid of the post
			$boardUID = $post->getBoardUID();
			
			// fetch the board from memory
			$board = searchBoardArrayForBoard($post->getBoardUID());
			
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
		string $newIp,  
		string $duration, 
		string $makePublic, 
		string $publicBanMessageHTML, 
		bool $isGlobal,
		Post|false $post = false): void {
		// Set defaults if not provided
		$reason = $reasonFromRequest ?: "No reason given.";
		$starttime = $this->moduleContext->request->getRequestTime();
		$expires = $starttime + $this->calculateBanDuration($duration);

		if (!empty($newIp)) {
			$banFile = $isGlobal ? $this->getGlobalBanFilePath() : $this->getBanFilePath();
			$this->addBanEntry($banFile, $newIp, $starttime, $expires, $reason);
		}

		if ($makePublic) {
			if ($post) {
				
				$post->setComment($post->getComment() . $publicBanMessageHTML);
				
				// parameters to update in the query
				$updatePostParameters = [
					'com' => $post->getComment()
				];

				$this->moduleContext->postRepository->updatePost($post->getUid(), $updatePostParameters);
				
				$board = searchBoardArrayForBoard($post->getBoardUID());
				
				$board->rebuildBoard();
			}
		}

	}

	private function processBanDeletions() {
		$localBanFile = $this->getBanFilePath();
		$globalBanFile = $this->getGlobalBanFilePath();

		$log = $this->sortBansByNewest($this->readBanLog($localBanFile));
		$glog = $this->sortBansByNewest($this->readBanLog($globalBanFile));

		$localIndicesToRemove = [];
		$globalIndicesToRemove = [];

		foreach ($log as $i => $entry) {
			if ($this->moduleContext->request->hasParameter("del$i", 'POST') && $this->moduleContext->request->getParameter("del$i", 'POST') === 'on') {
				$localIndicesToRemove[] = $i;
			}
		}

		foreach ($glog as $i => $entry) {
			if ($this->moduleContext->request->hasParameter("delg$i", 'POST') && $this->moduleContext->request->getParameter("delg$i", 'POST') === 'on') {
				$globalIndicesToRemove[] = $i;
			}
		}

		// Note: removeBanEntries works on unsorted logs, but we sorted first.
		// We must write the sorted logs back with indices removed.
		$revokedLocalIps = $this->removeBanEntriesFromSorted($log, $localIndicesToRemove, $localBanFile);
		$revokedGlobalIps = $this->removeBanEntriesFromSorted($glog, $globalIndicesToRemove, $globalBanFile);

		// Log revocations if any
		$allRevoked = array_merge($revokedLocalIps, $revokedGlobalIps);
		if (!empty($allRevoked)) {
			if (count($allRevoked) === 1) {
				$msg = "Revoked ban for {$allRevoked[0]}";
			} else {
				$msg = "Revoke bans for: " . implode(", ", $allRevoked);
			}
			$boardUid = !empty($revokedGlobalIps) ? -1 : $this->moduleContext->board->getBoardUID();
			$this->logAction($msg, $boardUid);
		}

		redirect($this->moduleContext->request->getReferer());
		exit;
	}

	private function removeBanEntriesFromSorted(array $sortedLog, array $indicesToRemove, string $banFile): array {
		$newLog = [];
		$removedIps = [];

		foreach ($sortedLog as $i => $entry) {
			if (in_array($i, $indicesToRemove, true)) {
				$parts = explode(',', $entry, 4);
				if (!empty($parts[0])) {
					$removedIps[] = $parts[0];
				}
			} else {
				$newLog[] = $entry;
			}
		}

		$this->writeBanLog($banFile, $newLog);
		return $removedIps;
	}

	private function convertBanLogToRows(array $bans, string $prefix, int $offset = 0): array {
		$rows = [];
		foreach ($bans as $i => $ban) {
			list($ip, $start, $expires, $reason) = explode(',', $ban, 4);
			$rows[] = [
				'{$CHECKBOX_NAME}' => $prefix . ($offset + $i),
				'{$IP}' => htmlspecialchars($ip),
				'{$START}' => $this->moduleContext->postDateFormatter->formatFromTimestamp(intval($start)),
				'{$EXPIRES}' => $this->moduleContext->postDateFormatter->formatFromTimestamp(intval($expires)),
				'{$REASON}' => $reason,
			];
		}
		return $rows;
	}

}