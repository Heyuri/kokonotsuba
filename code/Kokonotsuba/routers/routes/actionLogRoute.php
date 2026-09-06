<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\action_log\actionLogPostLinks;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\action_log\actionTypeRegistry;
use Kokonotsuba\board\board;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\postRepository;
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
	/** Columns the table headers may sort on, matching the repository's allowlist. */
	private const SORTABLE_COLUMNS = ['id', 'time_added', 'action_type', 'name', 'role', 'board_uid', 'board_title'];

	public function __construct(
		private board $board,
		private readonly array $config,
		private readonly actionLoggerService $actionLoggerService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly pageRenderer $adminPageRenderer,
		private readonly array $regularBoards,
		private readonly postDateFormatter $postDateFormatter,
		private readonly request $request,
		private readonly postRepository $postRepository
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

		$typeRegistry = $this->actionLoggerService->getTypeRegistry();
		$references = $this->actionLoggerService->getReferences();

		$defaultActionLogFilters = $this->initializeActionLogFilters($typeRegistry);

		$filtersFromRequest = getFiltersFromRequest($actionLogUrl, $isSubmission, $defaultActionLogFilters, $this->request, ['action_type']);

		$cleanUrl = buildSmartQuery($actionLogUrl, $defaultActionLogFilters, $filtersFromRequest, true);

		[$sortColumn, $sortDirection] = $this->resolveSort();

		// the sort lives outside the filter set, so it has to be carried on the pager links by hand
		$cleanUrl .= (str_contains($cleanUrl, '?') ? '&' : '?') . 'sort=' . rawurlencode($sortColumn) . '&dir=' . $sortDirection;

		// get the associate array for the checkbox generator
		$arrayForFilter = createAssocArrayFromBoardArray($this->regularBoards);

		// Add the global board
		$arrayForFilter[] = [
			'board_title' => "Global",
			'board_uid' => GLOBAL_BOARD_UID
		];

		// draw action log entry filter form
		drawActionLogFilterForm($actionLogHtml, $this->board, $arrayForFilter, $filtersFromRequest, $typeRegistry);
		
		$entriesFromDatabase = $this->actionLoggerService->getSpecifiedLogEntries($limit, $offset, $filtersFromRequest, $sortColumn, $sortDirection);
		$numberOfActionLogs = $this->actionLoggerService->getAmountOfLogEntries($filtersFromRequest);

		// the post numbers on this page, resolved together rather than one link at a time
		(new actionLogPostLinks($this->postRepository, $this->regularBoards))
			->register($references, $entriesFromDatabase ?: []);
	
		if(!$entriesFromDatabase) {
			$tableEntries .= 
				'<tr>
					<td colspan="8">
						<b class="error"> - No entries found in database -</b> 
					</td> 
				</tr>';
		
		} else {
			//generate table entry html
			foreach ($entriesFromDatabase as $actionLogEntry) {
				$roleEnum = userRole::fromStored($actionLogEntry->getRole());

				$tableEntries .= "
				<tr>
					<td>" . $actionLogEntry->getBoardTitle() . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getBoardUID()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getName()) . "</td>
					<td>" . htmlspecialchars($roleEnum->displayRoleName()) . "</td>
					<td>" . htmlspecialchars($actionLogEntry->getIpAddress()) . "</td>
					<td>" . htmlspecialchars($typeRegistry->labelFor($actionLogEntry->getActionType())) . "</td>
					<td>" . $references->toHtml($actionLogEntry->getLogAction(), $actionLogEntry->getBoardUID()) . "</td>
					<td>" . $this->postDateFormatter->formatFromDateString($actionLogEntry->getTimeAdded()) . "</td>
   			 	</tr>";
			}

		}
		
		$actionLogHtml .= "
			<div id=\"actionlogtableContainer\" class=\"tableViewportWrapper\">
				<table class=\"postlists\" id=\"actionlogtable\">
					<thead>
						<tr>
							" . $this->sortableHeader('Board title', 'board_title', $sortColumn, $sortDirection, $cleanUrl) . "
							<th>Board UID</th>
							" . $this->sortableHeader('Name', 'name', $sortColumn, $sortDirection, $cleanUrl) . "
							" . $this->sortableHeader('Role', 'role', $sortColumn, $sortDirection, $cleanUrl) . "
							<th>IP</th>
							" . $this->sortableHeader('Event', 'action_type', $sortColumn, $sortDirection, $cleanUrl) . "
							<th>Action</th>
							" . $this->sortableHeader('Time', 'time_added', $sortColumn, $sortDirection, $cleanUrl) . "
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

	/** @return array{0: string, 1: string} Sort column and direction from the request. */
	private function resolveSort(): array {
		$column = (string) $this->request->getParameter('sort', default: 'time_added');

		if (!in_array($column, self::SORTABLE_COLUMNS, true)) {
			$column = 'time_added';
		}

		$direction = strtoupper((string) $this->request->getParameter('dir', default: 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

		return [$column, $direction];
	}

	/** A header cell that links to the same page sorted by its column, flipping when already active. */
	private function sortableHeader(string $label, string $column, string $sortColumn, string $sortDirection, string $baseUrl): string {
		$isActive = $sortColumn === $column;
		$nextDirection = ($isActive && $sortDirection === 'DESC') ? 'ASC' : 'DESC';

		// resolveSort() put sort/dir on the URL already, so replace rather than append
		$url = preg_replace('/([?&])(sort|dir)=[^&]*/', '', $baseUrl);
		$url = rtrim($url, '?&');
		$url .= (str_contains($url, '?') ? '&' : '?') . 'sort=' . rawurlencode($column) . '&dir=' . $nextDirection;

		$arrow = $isActive ? ($sortDirection === 'DESC' ? ' &#9662;' : ' &#9652;') : '';

		return '<th class="sortableColumn' . ($isActive ? ' sortedColumn' : '') . '"><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . $arrow . '</a></th>';
	}

	private function initializeActionLogFilters(actionTypeRegistry $typeRegistry): array {
		// Default board selection: current board and global board
		$defaultSelectedBoards = [$this->board->getBoardUID(), GLOBAL_BOARD_UID];
	
		// Default roles selection - every role an account can hold
		$defaultRoleSelections = array_map(
			fn(userRole $role): int => $role->value,
			userRole::accountRoles()
		);
	
		return [
			'id' => '',
			'ip_address' => '',
			'log_name' => '',
			'log_action' => '',
			'action_type' => $typeRegistry->defaultKeys(),
			'role' => $defaultRoleSelections,
			'board' => $defaultSelectedBoards,
			'date_before' => '',
			'date_after' => '',
		];
	}

}
