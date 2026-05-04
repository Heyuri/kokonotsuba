<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\board\board;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\request\request;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\html\drawActionLogFilterForm;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\createAssocArrayFromBoardArray;
use function Kokonotsuba\libraries\getFiltersFromRequest;
use function Puchiko\strings\buildSmartQuery;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class actionLogRoute {
	public function __construct(
		private board $board,
		private readonly array $config,
		private readonly actionLoggerService $actionLoggerService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly pageRenderer $adminPageRenderer,
		private readonly array $regularBoards,
		private readonly postDateFormatter $postDateFormatter,
		private readonly request $request
	) {}

	public function drawActionLog() {
		$this->softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_VIEW_ACTION_LOG']);

		$actionLogHtml = '';

		$tableEntries = '';
		$limit = $this->config['ACTIONLOG_MAX_PER_PAGE'];
		$page = (int) $this->request->getParameter('page', default: 1);
		$page = max(1, $page);
		$offset = ($page - 1) * $limit;

		// So we can see if the form is being submitted in the current request
		$isSubmission = $this->request->hasParameter('filterSubmissionFlag', 'GET');
		
		$actionLogUrl = $this->board->getBoardURL(true) . '?mode=actionLog';

		$defaultActionLogFilters = $this->initializeActionLogFilters();

		$filtersFromRequest = getFiltersFromRequest($actionLogUrl, $isSubmission, $defaultActionLogFilters, $this->request);

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
				$roleEnum = userRole::tryFrom($roleValue);

				$tableEntries .= "
				<tr>
					<td>" . $actionLogEntry->getBoardTitle() . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getBoardUID()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getName()) . "</td>
					<td>" . htmlspecialchars($roleEnum->displayRoleName()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getIpAddress()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getLogAction()) . "</td>
					<td>" . $this->postDateFormatter->formatFromDateString($actionLogEntry->getTimeAdded()) . "</td>
   			 	</tr>";
			}

		}
		
		$actionLogHtml .= "
			<div id=\"actionlogtableContainer\" class=\"tableViewportWrapper\">
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

		$actionLogPager = drawPager($limit, $numberOfActionLogs, $cleanUrl, $this->request);
		
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
		$none = userRole::LEV_NONE->value;
		$user = userRole::LEV_USER->value;
		$janitor = userRole::LEV_JANITOR->value;
		$moderator = userRole::LEV_MODERATOR->value;
		$admin = userRole::LEV_ADMIN->value;
	
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
