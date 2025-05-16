<?php

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class actionLogRoute {
	private readonly board $board;
	private readonly array $config;
	private readonly globalHTML $globalHTML;
	private readonly actionLogger $actionLogger;
	private readonly softErrorHandler $softErrorHandler;
	private readonly pageRenderer $adminPageRenderer;

	public function __construct(
		board $board,
		array $config,
		globalHTML $globalHTML,
		actionLogger $actionLogger,
		softErrorHandler $softErrorHandler,
		pageRenderer $adminPageRenderer
	) {
		$this->board = $board;
		$this->config = $config;
		$this->globalHTML = $globalHTML;
		$this->actionLogger = $actionLogger;
		$this->softErrorHandler = $softErrorHandler;
		$this->adminPageRenderer = $adminPageRenderer;
	}

	public function drawActionLog() {
		$this->softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_VIEW_ACTION_LOG']);

		$actionLogHtml = '';
		
		$filterAction = $_POST['filterformsubmit'] ?? null;
		
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filter') {
			$filterRoleFromPOST = $_POST['filterrole'] ?? '';
			$filterBoardFromPOST = $_POST['filterboard'] ?? '';
			
			$filterIP = htmlspecialchars($_POST['filterip'] ?? '');
			$filterDateBefore = htmlspecialchars($_POST['filterdatebefore'] ?? '');
			$filterDateAfter = htmlspecialchars($_POST['filterdateafter'] ?? '');
			$filterName = htmlspecialchars($_POST['filtername'] ?? '');
			$filterBan = isset($_POST['bans']) ? 'checked' : '';
			$filterDelete = isset($_POST['deleted']) ? 'checked' : '';
			$filterRole = (is_array($filterRoleFromPOST) ? array_map('htmlspecialchars', $filterRoleFromPOST) : [htmlspecialchars($filterRoleFromPOST)]);
			$filterBoard = (is_array($filterBoardFromPOST) ? array_map('htmlspecialchars', $filterBoardFromPOST) : [htmlspecialchars($filterBoardFromPOST)]);
			
			setcookie('filterip', $filterIP, time() + (86400 * 30), "/");
			setcookie('filterdatebefore', $filterDateBefore, time() + (86400 * 30), "/");
			setcookie('filterdateafter', $filterDateAfter, time() + (86400 * 30), "/");
			setcookie('filtername', $filterName, time() + (86400 * 30), "/");
			setcookie('filterban', $filterBan, time() + (86400 * 30), "/");
			setcookie('filterdelete', $filterDelete, time() + (86400 * 30), "/");
			setcookie('filterrole', json_encode($filterRole), time() + (86400 * 30), "/");
			setcookie('filterboard', json_encode($filterBoard), time() + (86400 * 30), "/");

			redirect($this->config['PHP_SELF'].'?mode=actionlog');
		} else if($_SERVER['REQUEST_METHOD'] === 'POST' && $filterAction === 'filterclear') {
			setcookie('filterip', "", time() - 3600, "/");
			setcookie('filterdatebefore', "", time() - 3600, "/");
			setcookie('filterdateafter', "", time() - 3600, "/");
			setcookie('filtername', "", time() - 3600, "/");
			setcookie('filterban', "", time() - 3600, "/");
			setcookie('filterdelete', "", time() - 3600, "/");
			setcookie('filterrole', "", time() - 3600, "/");
			setcookie('filterboard', "", time() - 3600, "/");
			
			redirect($this->config['PHP_SELF'].'?mode=actionlog');
		}
		
		$filtersBoards = (isset($_COOKIE['filterboard'])) ? json_decode($_COOKIE['filterboard'], true) : [$this->board->getBoardUID(), GLOBAL_BOARD_UID];
		$filtersRoles = (isset($_COOKIE['filterrole'])) ? json_decode($_COOKIE['filterrole'], true) : array_values($this->config['roles']); 
		
		//filter list for the database
		$filters = [
			'ip_address' => $_COOKIE['filterip'] ?? null,
			'name' => $_COOKIE['filtername'] ?? null,
			'ban' => $_COOKIE['filterban'] ?? null,
			'deleted' => $_COOKIE['filterdelete'] ?? null,
			'role' => $filtersRoles ?? '',
			'board' => $filtersBoards ?? '',
			'date_before' => $_COOKIE['filterdatebefore'] ?? '',
			'date_after' => $_COOKIE['filterdateafter'] ?? '',
		];
		$tableEntries = '';
		$limit = $this->config['ACTIONLOG_MAX_PER_PAGE'];
		$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
		$page = ($page >= 0) ? $page : 1;
		$offset = $page * $limit;
		
		$this->globalHTML->drawModFilterForm($actionLogHtml, $this->board);
		
		$entriesFromDatabase = $this->actionLogger->getSpecifiedLogEntries($limit, $offset, $filters);
		$numberOfActionLogs = $this->actionLogger->getAmountOfLogEntries($filters);
	
		if(!$entriesFromDatabase) {
			$tableEntries .= 
				'<tr>
					<td colspan="7">
						<b class="error"> - No entries found in database -</b> 
					</td> 
				</tr>';
		
		} else {
			//generate table entry html
			foreach($entriesFromDatabase as $actionLogEntry) {
				$roleValue = $actionLogEntry->getRole();
				$roleEnum = \Kokonotsuba\Root\Constants\userRole::tryFrom($roleValue);

				$tableEntries .= "
				<tr>
					<td>{$actionLogEntry->getBoardTitle()}</td>
					<td>{$actionLogEntry->getBoardUID()}</td>
					<td>".htmlspecialchars($actionLogEntry->getName())."</td>
					<td>{$roleEnum->displayRoleName()}</td>
					<td>{$actionLogEntry->getIpAddress()}</td>
					<td>{$actionLogEntry->getLogAction()}</td>
					<td>{$actionLogEntry->getTimeAdded()}</td>
				 </tr>";
			}
		}
		
		$actionLogHtml .= "<div id=\"reloadTable\" class=\"centerText\">[<a href=\"{$this->config['PHP_SELF']}?mode=actionLog\">Reload table</a>]</div>
			<table class=\"postlists\" id=\"actionlogtable\">
				<thead>
					<tr>
						<th>Board title</th>
						<th>Board UID</th>
						<th>Name</th>
						<th>Role</th>
						<th>IP</th>
						<th>Action</th>
						<th>Time</th>
					</tr>
				</thead>
				<tbody>
					$tableEntries
				</tbody>
			</table>
		";

		$actionLogHtml .= $this->globalHTML->drawPager($limit, $numberOfActionLogs, $this->globalHTML->fullURL().$this->config['PHP_SELF'].'?mode=actionlog');
	
		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT' => $actionLogHtml], true);

		echo $htmlOutput;
	}
}