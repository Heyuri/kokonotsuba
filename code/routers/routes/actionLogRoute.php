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
		
		$defaultSelectedBoards = [$this->board->getBoardUID(), GLOBAL_BOARD_UID];

		//filter list for the database
		$filters = [
			'ip_address' => $_GET['ip_address'] ?? null,
			'name' => $_GET['filterName'] ?? null,
			'ban' => $_GET['ban'] ?? null,
			'deleted' => $_GET['deleted'] ?? null,
			'role' => $_GET['role'] ?? null,
			'board' => $_GET['board'] ?? $defaultSelectedBoards,
			'date_before' => $_GET['date_before'] ?? null,
			'date_after' => $_GET['date_after'] ?? null,
		];

		$tableEntries = '';
		$limit = $this->config['ACTIONLOG_MAX_PER_PAGE'];
		$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
		$page = ($page >= 0) ? $page : 1;
		$offset = $page * $limit;
		
		$this->globalHTML->drawModFilterForm($actionLogHtml, $this->board, $filters);
		
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
		
		$actionLogHtml .= "
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
	
		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $actionLogHtml], true);

		echo $htmlOutput;
	}
}