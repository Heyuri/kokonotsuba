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

		$actionLogUrl = $this->board->getBoardURL().$this->config['PHP_SELF'] . '?mode=actionLog';

		$none = \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value;
		$user = \Kokonotsuba\Root\Constants\userRole::LEV_USER->value;
		$janitor = \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR->value;
		$moderator = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR->value;
		$admin = \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value;

		$defaultRoleSelections = [$none, $user, $janitor, $moderator, $admin];

		$defaultFilters = [
			'ip_address' => '',
			'log_name' => '',
			'ban' => '',
			'deleted' => '',
			'role' => $defaultRoleSelections,
			'board' => $defaultSelectedBoards,
			'date_before' => '',
			'date_after' => '',
		];

		//filter list for the database
		$filtersFromRequest = [
			'ip_address' => $_GET['ip_address'] ?? '',
			'log_name' => $_GET['log_name'] ?? '',
			'ban' => $_GET['ban'] ?? '',
			'deleted' => $_GET['deleted'] ?? '',
			'role' => $_GET['role'] ?? $defaultFilters['role'],
			'board' => $_GET['board'] ?? $defaultFilters['board'],
			'date_before' => $_GET['date_before'] ?? '',
			'date_after' => $_GET['date_after'] ?? '',
		];

		if(is_string($filtersFromRequest['role'])) {
			$filtersFromRequest['role'] = explode(' ', $filtersFromRequest['role']);
		}

		if(is_string($filtersFromRequest['board'])) {
			$filtersFromRequest['board'] = explode(' ', $filtersFromRequest['board']);
		}

		// redirect to cleaned URI
		if (isset($_GET['filterSubmissionFlag'])) {
			$cleanedUrl = buildSmartQuery($actionLogUrl, $defaultFilters, $filtersFromRequest);

			// Redirect without the flag
			redirect($cleanedUrl);
			exit;
		}		

		$tableEntries = '';
		$limit = $this->config['ACTIONLOG_MAX_PER_PAGE'];
		$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
		$page = ($page >= 0) ? $page : 1;
		$offset = $page * $limit;
		
		$this->globalHTML->drawModFilterForm($actionLogHtml, $this->board, $filtersFromRequest);
		
		$entriesFromDatabase = $this->actionLogger->getSpecifiedLogEntries($limit, $offset, $filtersFromRequest);
		$numberOfActionLogs = $this->actionLogger->getAmountOfLogEntries($filtersFromRequest);
	
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