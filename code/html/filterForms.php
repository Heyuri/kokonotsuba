<?php

function drawActionLogFilterForm(string &$dat, board $board, array $allBoards, array $filters) {
	$filterIP = $filters['ip_address'];
	$filterDateBefore = $filters['date_before'];
	$filterDateAfter = $filters['date_after'];
	$filterName = $filters['log_name'];
	$filterBan = !empty($filters['ban']) ? 'checked' : '';
	$filterDelete = !empty($filters['deleted']) ? 'checked' : '';
	$filterRole = is_array($filters['role']) ? $filters['role'] : [];
	$filterBoard = is_array($filters['board']) ? $filters['board'] : [];

	// role levels
	$none = \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value;
	$user = \Kokonotsuba\Root\Constants\userRole::LEV_USER->value;
	$janitor = \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR->value;
	$moderator = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR->value;
	$admin = \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value;
	
	$boardCheckboxHTML = generateBoardListCheckBoxHTML($board, $filterBoard, $allBoards, false, true);
	$dat .= '
		<form class="detailsboxForm" id="actionLogFilterForm" action="' . $board->getBoardURL(true) . '" method="get">
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
								<td class="postblock"><label for="date_after">From</label></td>
								<td><input class="inputtext" type="date" id="date_after" name="date_after" value="' . htmlspecialchars($filterDateAfter) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="date_before">To</label></td>
								<td><input class="inputtext" type="date" id="date_before" name="date_before" value="' . htmlspecialchars($filterDateBefore) . '"></td>
							</tr>
							<tr>
								<td class="postblock">Actions</td>
								<td> 
									<label><input type="checkbox" id="ban" name="ban" ' . htmlspecialchars($filterBan) . '>Bans</label>  
									<label><input type="checkbox" id="deletions" name="deleted" ' . htmlspecialchars($filterDelete) . '>Deletions</label>
								</td>
							</tr>
							<tr id="rolerow">
								<td class="postblock">Roles <br> <div class="selectlinktextjs" id="roleselectall">[<a>Select all</a>]</div></td>
								<td>
									<ul class="littlelist">
										<li><label><input name="role[]" type="checkbox" value="' . $none . '" ' . (in_array($none, $filterRole) ? 'checked' : '') . '>None</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $user . '" ' . (in_array($user, $filterRole) ? 'checked' : '') . '>User</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $janitor . '" ' . (in_array($janitor, $filterRole) ? 'checked' : '') . '>Janitor</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $moderator . '" ' . (in_array($moderator, $filterRole) ? 'checked' : '') . '>Moderator</label></li>
										<li><label><input name="role[]" type="checkbox" value="' . $admin . '" ' . (in_array($admin, $filterRole) ? 'checked' : '') . '>Admin</label></li>
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
	
function drawManagePostsFilterForm(&$dat, board $board, array $filters, array $boards) {
	$filterIP = $filters['ip_address'];
	$filterName = $filters['post_name'];
	$filterTripcode = $filters['tripcode'];
	$filterCapcode = $filters['capcode'];
	$filterSubject = $filters['subject'];
	$filterComment = $filters['comment'];
	$filterBoard = $filters['board'];
	
	$boardCheckboxHTML = generateBoardListCheckBoxHTML($board, $filterBoard, $boards);
	$dat .= '
	<form class="detailsboxForm" action="' . $board->getBoardURL(true) . '" method="get">
		<details id="filtercontainer" class="detailsbox">
			<summary>Filter posts</summary>
			<div class="detailsboxContent">
				<input type="hidden" name="mode" value="managePosts">
				<input type="hidden" name="filterSubmissionFlag" value="true">

				<table id="adminPostFilterTable" class="centerBlock">
					<tbody>
						<tr>
							<td class="postblock"><label for="ip_address">IP address</label></td>
							<td><input class="inputtext" id="ip_address" name="ip_address" value="'.htmlspecialchars($filterIP).'"></td>
						</tr>
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
	
function drawOverboardFilterForm(&$dat, board $board, array $boards, array $allowedBoards) {
	$boardCheckboxHTML = generateBoardListCheckBoxHTML($board, $allowedBoards, $boards);

	$dat .= '
		<form class="detailsboxForm" id="overboardFilterForm" action="' . $board->getBoardURL(true) . '?mode=overboard" method="POST">
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