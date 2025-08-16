<?php

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class actionLogRoute {
	public function __construct(
		private board $board,
		private readonly array $config,
		private readonly actionLoggerService $actionLoggerService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly pageRenderer $adminPageRenderer,
		private readonly boardService $boardService,
		private readonly array $regularBoards
	) {}

	public function drawActionLog() {
		$this->softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_VIEW_ACTION_LOG']);

		$actionLogHtml = '';

		$tableEntries = '';
		$limit = $this->config['ACTIONLOG_MAX_PER_PAGE'];
		$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
		$page = ($page >= 0) ? $page : 1;
		$offset = $page * $limit;

		// So we can see if the form is being submitted in the current request
		$isSubmission = isset($_GET['filterSubmissionFlag']);
		
		$actionLogUrl = $this->board->getBoardURL(true) . '?mode=actionLog';

		$defaultActionLogFilters = $this->initializeActionLogFilters();

		$filtersFromRequest = getFiltersFromRequest($actionLogUrl, $isSubmission, $defaultActionLogFilters);

		$cleanUrl = buildSmartQuery($actionLogUrl, $defaultActionLogFilters, $filtersFromRequest, true);

		// get the associate array for the checkbox generator
		$arrayForFilter = createAssocArrayFromBoardArray($this->regularBoards);

		// Add the global board
		$arrayForFilter[] = [
			'board_title' => "Global",
			'board_uid' => GLOBAL_BOARD_UID
		];

		// draw action log entry filter form
		drawActionLogFilterForm($actionLogHtml, $this->board, $arrayForFilter, $filtersFromRequest);
		
		$entriesFromDatabase = $this->actionLoggerService->getSpecifiedLogEntries($limit, $offset, $filtersFromRequest);
		$numberOfActionLogs = $this->actionLoggerService->getAmountOfLogEntries($filtersFromRequest);
	
		if(!$entriesFromDatabase) {
			$tableEntries .= 
				'<tr>
					<td colspan="7">
						<b class="error"> - No entries found in database -</b> 
					</td> 
				</tr>';
		
		} else {
			//generate table entry html
			foreach ($entriesFromDatabase as $actionLogEntry) {
				$roleValue = $actionLogEntry->getRole();
				$roleEnum = \Kokonotsuba\Root\Constants\userRole::tryFrom($roleValue);

				$tableEntries .= "
				<tr>
					<td>" . $actionLogEntry->getBoardTitle() . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getBoardUID()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getName()) . "</td>
					<td>" . htmlspecialchars($roleEnum->displayRoleName()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getIpAddress()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getLogAction()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getTimeAdded()) . "</td>
   			 	</tr>";
			}

		}
		
		$actionLogHtml .= "
			<div id=\"actionlogtableContainer\">
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
			</div>
		";

		$actionLogPager = drawPager($limit, $numberOfActionLogs, $cleanUrl);
		
		$templateValues = [
			'{$PAGE_CONTENT}' => $actionLogHtml,
			'{$PAGER}' => $actionLogPager
		];
		
		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $templateValues, true);

		echo $htmlOutput;
	}

	private function initializeActionLogFilters(): array {
		// Default board selection: current board and global board
		$defaultSelectedBoards = [$this->board->getBoardUID(), GLOBAL_BOARD_UID];
	
		// Define user roles (these constants should exist in your application)
		$none = \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value;
		$user = \Kokonotsuba\Root\Constants\userRole::LEV_USER->value;
		$janitor = \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR->value;
		$moderator = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR->value;
		$admin = \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value;
	
		// Default roles selection
		$defaultRoleSelections = [$none, $user, $janitor, $moderator, $admin];
	
		return [
			'ip_address' => '',
			'log_name' => '',
			'ban' => '',
			'deleted' => '',
			'role' => $defaultRoleSelections,
			'board' => $defaultSelectedBoards,
			'date_before' => '',
			'date_after' => '',
		];
	}

}
