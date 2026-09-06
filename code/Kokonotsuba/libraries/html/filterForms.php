<?php

namespace Kokonotsuba\libraries\html;

use Kokonotsuba\board\board;
use Kokonotsuba\action_log\actionTypeRegistry;
use Kokonotsuba\userRole;
use function Kokonotsuba\libraries\html\generateBoardListCheckBoxHTML;

/**
 * @param actionTypeRegistry $actionTypes Every event type the log can be filtered on.
 */
function drawActionLogFilterForm(string &$dat, board $board, array $allBoards, array $filters, actionTypeRegistry $actionTypes) {
	$filterIP = $filters['ip_address'];
	$filterDateBefore = $filters['date_before'];
	$filterDateAfter = $filters['date_after'];
	$filterName = $filters['log_name'];
	$filterAction = $filters['log_action'] ?? '';
	$filterId = $filters['id'] ?? '';
	$filterRole = is_array($filters['role']) ? $filters['role'] : [];
	$filterBoard = is_array($filters['board']) ? $filters['board'] : [];
	$filterTypes = is_array($filters['action_type'] ?? null) ? $filters['action_type'] : [];

	// one checkbox per role an account can hold
	$roleCheckboxHTML = '';
	foreach (userRole::accountRoles() as $role) {
		$checked = in_array($role->value, $filterRole) ? 'checked' : '';
		$roleCheckboxHTML .= '<li><label><input name="role[]" type="checkbox" value="' . $role->value . '" ' . $checked . '>' . htmlspecialchars($role->displayRoleName()) . '</label></li>';
	}

	$typeCheckboxHTML = generateActionTypeCheckBoxHTML($filterTypes, $actionTypes);

	$boardCheckboxHTML = generateBoardListCheckBoxHTML($filterBoard, $allBoards, false);
	$dat .= '
		<form class="detailsboxForm formtable" id="actionLogFilterForm" action="' . $board->getBoardURL(true) . '" method="get">
			<details id="filtercontainer" class="detailsbox">
				<summary>Filter action log</summary>
				<div class="detailsboxContent">
					<input type="hidden" name="mode" value="actionLog">
					<input type="hidden" name="filterSubmissionFlag" value="true">

					<table>
						<tbody>
							<tr>
								<td class="postblock"><label for="ip">IP address</label></td>
								<td><input class="inputtext" id="ip_address" name="ip_address" value="' . htmlspecialchars($filterIP) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="log_name">Name</label></td>
								<td><input class="inputtext" id="log_name" name="log_name" value="' . htmlspecialchars($filterName) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="log_action">Action text</label></td>
								<td><input class="inputtext" id="log_action" name="log_action" value="' . htmlspecialchars($filterAction) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="entryid">Entry ID</label></td>
								<td><input class="inputtext" type="number" min="1" id="entryid" name="id" value="' . htmlspecialchars((string)$filterId) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="date_after">From</label></td>
								<td><input class="inputtext" type="date" id="date_after" name="date_after" value="' . htmlspecialchars($filterDateAfter) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="date_before">To</label></td>
								<td><input class="inputtext" type="date" id="date_before" name="date_before" value="' . htmlspecialchars($filterDateBefore) . '"></td>
							</tr>
							<tr id="actiontyperow">
								<td class="postblock">Events <br> <div class="selectlinktextjs" id="actiontypeselectall">[<a>Select all</a>]</div></td>
								<td>
									' . $typeCheckboxHTML . '
								</td>
							</tr>
							<tr id="rolerow">
								<td class="postblock">Roles <br> <div class="selectlinktextjs" id="roleselectall">[<a>Select all</a>]</div></td>
								<td>
									<ul class="littlelist">
										' . $roleCheckboxHTML . '
									</ul>
								</td>
							</tr>
							<tr id="boardrow">
								<td class="postblock"><label for="filterboard">Boards</label><div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div></td>
								<td>
									<ul class="boardFilterList">
										' . $boardCheckboxHTML . '
									</ul>
								</td>
							</tr>
						</tbody>
					</table>
					<div class="buttonSection">
						<input type="submit" value="Filter">
						<input type="reset" value="Reset">
					</div>
				</div>
			</details>
		</form>
		';
	}

/**
 * Event type checkboxes, laid out under their group headings.
 *
 * An entry is ticked when the filter names it; an entirely empty filter means the caller has not
 * narrowed anything, so everything is ticked.
 */
function generateActionTypeCheckBoxHTML(array $selected, actionTypeRegistry $actionTypes): string {
	$html = '';

	foreach ($actionTypes->grouped() as $groupKey => $group) {
		$items = '';

		foreach ($group['entries'] as $entry) {
			$checked = (empty($selected) || in_array($entry['key'], $selected, true)) ? 'checked' : '';
			$items .= '<li><label><input name="action_type[]" type="checkbox" value="' . htmlspecialchars($entry['key']) . '" ' . $checked . '>' . htmlspecialchars($entry['label']) . '</label></li>';
		}

		$rowId = 'actiontypegroup-' . $groupKey;

		$html .= '<div class="actionTypeGroup" id="' . htmlspecialchars($rowId) . '">'
			. '<div class="actionTypeGroupLabel">' . htmlspecialchars($group['label'])
			. ' <span class="selectlinktextjs actionTypeGroupToggle" data-target="' . htmlspecialchars($rowId) . '">[<a>Select all</a>]</span></div>'
			. '<ul class="littlelist">' . $items . '</ul></div>';
	}

	return '<div class="actionTypeGroups">' . $html . '</div>';
}

/**
 * The post filter form shared by every staff page that lists posts from more than one board.
 *
 * @param string $formAction    Where the form submits - the page drawing it.
 * @param array  $hiddenFields  name => value pairs identifying that page (mode, load, ...).
 * @param string $summaryLabel  Text on the collapsed <summary>.
 */
function drawManagePostsFilterForm(string &$dat, string $formAction, array $hiddenFields, array $filters, bool $canViewIp, array $boards, string $summaryLabel = 'Filter posts') {
	$filterIP = $filters['ip_address'];
	$filterTokenHash = $filters['visitor_token_hash'] ?? '';
	$filterName = $filters['post_name'];
	$filterTripcode = $filters['tripcode'];
	$filterCapcode = $filters['capcode'];
	$filterSubject = $filters['subject'];
	$filterComment = $filters['comment'];
	$filterBoard = $filters['board'];
	
	$boardCheckboxHTML = generateBoardListCheckBoxHTML($filterBoard, $boards);

	$hiddenFieldHTML = '';
	foreach ($hiddenFields as $hiddenName => $hiddenValue) {
		$hiddenFieldHTML .= '<input type="hidden" name="' . htmlspecialchars($hiddenName) . '" value="' . htmlspecialchars($hiddenValue) . '">';
	}

	$dat .= '
	<form class="detailsboxForm formtable" action="' . htmlspecialchars($formAction) . '" method="get">
		<details id="filtercontainer" class="detailsbox">
			<summary>' . htmlspecialchars($summaryLabel) . '</summary>
			<div class="detailsboxContent">
				' . $hiddenFieldHTML . '
				<input type="hidden" name="filterSubmissionFlag" value="true">

				<table id="adminPostFilterTable" class="centerBlock">
					<tbody>
						' . ($canViewIp ? '<tr>
							<td class="postblock"><label for="ip_address">IP address</label></td>
							<td><input class="inputtext" id="ip_address" name="ip_address" value="'.htmlspecialchars($filterIP).'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="visitor_token_hash">Browser</label></td>
							<td><input class="inputtext" id="visitor_token_hash" name="visitor_token_hash" value="'.htmlspecialchars($filterTokenHash).'"></td>
						</tr>' : '') . '
						<tr>
							<td class="postblock"><label for="post_name">Name</label></td>
							<td><input class="inputtext" id="post_name" name="post_name" value="'.htmlspecialchars($filterName).'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="tripcode">Tripcode</label></td>
							<td><input class="inputtext" id="tripcode" name="tripcode" value="'.htmlspecialchars($filterTripcode).'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="capcode">Capcode</label></td>
							<td><input class="inputtext" id="capcode" name="capcode" value="'.htmlspecialchars($filterCapcode).'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="subject">Subject</label></td>
							<td><input class="inputtext" id="subject" name="subject" value="'.htmlspecialchars($filterSubject).'"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="comment">Comment</label></td>
							<td><input class="inputtext" id="comment" name="comment" value="'.htmlspecialchars($filterComment).'"></td>
						</tr>
						<tr id="boardrow">
							<td class="postblock">
								<label for="filterboard">Boards</label>
								<div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div>
							</td>
							<td>
								<ul id="managePostsBoardFilterList" class="boardFilterList">
									'.$boardCheckboxHTML.'
								</ul>
							</td>
						</tr>
					</tbody>
				</table>
				<div class="buttonSection">
					<button type="submit" name="filterformsubmit" value="filter">Filter</button>
					<input type="reset" value="Reset">
				</div>
			</div>
		</details>
	</form>';
}

function drawDeletedPostsFilterForm(string &$dat, string $formAction, array $filters, bool $canViewIp, string $hiddenPageName = '') {
	$filterDeletedByType = $filters['deleted_by_type'] ?? '';
	$filterPostType = $filters['post_type'] ?? '';
	$filterIP = $filters['ip_address'] ?? '';
	$filterStaffUsername = $filters['staff_username'] ?? '';

	$dat .= '
	<form class="detailsboxForm formtable" action="' . htmlspecialchars($formAction) . '" method="get">
		<details id="filtercontainer" class="detailsbox">
			<summary>Filter deleted posts</summary>
			<div class="detailsboxContent">
				<input type="hidden" name="mode" value="module">
				<input type="hidden" name="load" value="deletedPosts">
				<input type="hidden" name="moduleMode" value="admin">
				<input type="hidden" name="filterSubmissionFlag" value="true">
				' . ($hiddenPageName ? '<input type="hidden" name="pageName" value="' . htmlspecialchars($hiddenPageName) . '">' : '') . '

				<table class="centerBlock">
					<tbody>
						<tr>
							<td class="postblock"><label for="deleted_by_type">Deleted by</label></td>
							<td>
								<select class="inputtext" id="deleted_by_type" name="deleted_by_type">
									<option value=""' . ($filterDeletedByType === '' ? ' selected' : '') . '>All</option>
									<option value="staff"' . ($filterDeletedByType === 'staff' ? ' selected' : '') . '>Staff</option>
									<option value="user"' . ($filterDeletedByType === 'user' ? ' selected' : '') . '>User (self-deletion)</option>
								</select>
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="post_type">Post type</label></td>
							<td>
								<select class="inputtext" id="post_type" name="post_type">
									<option value=""' . ($filterPostType === '' ? ' selected' : '') . '>All</option>
									<option value="op"' . ($filterPostType === 'op' ? ' selected' : '') . '>Thread (OP)</option>
									<option value="reply"' . ($filterPostType === 'reply' ? ' selected' : '') . '>Reply</option>
								</select>
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="staff_username">Staff username</label></td>
							<td><input class="inputtext" id="staff_username" name="staff_username" value="' . htmlspecialchars($filterStaffUsername) . '"></td>
						</tr>
						' . ($canViewIp ? '<tr>
							<td class="postblock"><label for="ip_address">IP address</label></td>
							<td><input class="inputtext" id="ip_address" name="ip_address" value="' . htmlspecialchars($filterIP) . '"></td>
						</tr>' : '') . '
					</tbody>
				</table>
				<div class="buttonSection">
					<button type="submit" name="filterformsubmit" value="filter">Filter</button>
					<input type="reset" value="Reset">
				</div>
			</div>
		</details>
	</form>';
}
	
function drawOverboardFilterForm(&$dat, board $board, array $boards, array $allowedBoards) {
	$boardCheckboxHTML = generateBoardListCheckBoxHTML($allowedBoards, $boards);

	$dat .= '
		<form class="detailsboxForm formtable" id="overboardFilterForm" action="' . $board->getBoardURL(true) . '?mode=overboard" method="POST">
			<details id="filtercontainer" class="detailsbox">
				<summary>Filter boards</summary>
				<div class="detailsboxContent">
					<ul id="overboardFilterList" class="boardFilterList">
						'.$boardCheckboxHTML.'
					</ul>
					<div class="selectlinktextjs" id="overboardselectall">[<a>Select all</a>]</div>
					<div class="buttonSection">
						<button type="submit" name="filterformsubmit" value="filter">Filter</button> <input type="reset" value="Reset">
					</div>
				</div>
			</details>
		</form>
	';
}