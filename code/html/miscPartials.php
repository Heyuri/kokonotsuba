<?php

function generateAdminLinkButtons(string $liveIndexFile, string $staticIndexFile, moduleEngine $moduleEngine): string {
	$linksAboveBar =  '
		<ul id="adminNavBar">
			<li class="adminNavLink"><a href="'.$staticIndexFile.'?'.$_SERVER['REQUEST_TIME'].'">Return</a></li>
			<li class="adminNavLink"><a href="'.$liveIndexFile.'?page=0">Live frontend</a></li>
			<li class="adminNavLink"><a href="'.$liveIndexFile.'?mode=rebuild">Rebuild board</a></li>
			<li class="adminNavLink"><a href="'.$liveIndexFile.'?mode=managePosts">Manage posts</a></li>
			<li class="adminNavLink"><a href="'.$liveIndexFile.'?mode=actionLog">Action log</a></li>
			<li class="adminNavLink"><a href="'.$liveIndexFile.'?mode=account">Accounts</a></li>
			<li class="adminNavLink"><a href="'.$liveIndexFile.'?mode=boards">Boards</a></li>
			';

	$moduleEngine->dispatch('LinksAboveBar', array(&$linksAboveBar));
	
    $linksAboveBar .= "</ul>";
	return $linksAboveBar;
}

function drawAccountTable(string $liveIndexFile, array $accounts) {
	$dat = '';
	$accountsHTML = '';
	
	foreach ($accounts as $account) {
			$accountID = $account->getId();
			$accountUsername = $account->getUsername();
			$accountRoleLevel = $account->getRoleLevel();
			$accountNumberOfActions = $account->getNumberOfActions();
			$accountLastLogin = $account->getLastLogin() ?? '<i>Never</i>';
	
			$actionHTML = '[<a title="Delete account" href="' . $liveIndexFile . '?mode=handleAccountAction&del=' . $accountID . '">D</a>] ';
			if ($accountRoleLevel->value + 1 <= \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value) $actionHTML .= '[<a title="Promote account" href="' . $liveIndexFile . '?mode=handleAccountAction&up=' . $accountID. '">▲</a>]';
			if ($accountRoleLevel->value - 1 > \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value) $actionHTML .= '[<a title="Demote account" href="' . $liveIndexFile . '?mode=handleAccountAction&dem=' . $accountID . '">▼</a>]';			

			$accountsHTML .= '<tr> 
					<td class="colAccountID">' . $accountID . '</td>
					<td class="colUsername">' . $accountUsername . ' </td>
					<td class="colRoleLevel">' . $accountRoleLevel->displayRoleName() . '</td>
					<td class="colNumberofActions">' . $accountNumberOfActions . '</td>
					<td class="colLastLogin">' . $accountLastLogin . '</td>
					<td class="colActions">' . $actionHTML . '</td>
				</tr>';
	}
	$dat .= '
			<table id="tableStaffList" class="postlists">
				<thead>
					<tr>
						<th class="colAccountID">ID</th>
						<th class="colUsername">Username</th>
						<th class="colRoleLevel">Role</th>
						<th class="colNumberofActions">Total actions</th>
						<th class="colLastLogin">Last logged in</th>
						<th class="colActions">Actions</th>
					</tr>
				</thead>
				<tbody>
					' . $accountsHTML . '
				</tbody>
			</table>';
	return $dat;
}
	
function drawBoardTable(string $liveIndexFile, array $boards): string {
	$dat = '';
	$boardsHTML = '';

	foreach ($boards as $board) {
			$boardUID = $board->getBoardUID();
			$boardIdentifier = $board->getBoardIdentifier();
			$boardTitle = $board->getBoardTitle();
			$boardDateAdded = $board->getDateAdded();
		
			$actionHTML = '[<a title="View board" href="' . $liveIndexFile . '?mode=boards&view='.$boardUID.'">View</a>] ';
			$boardsHTML .= '
				<tr> 
					<td>' . $boardUID . '</td>
					<td>' . $boardIdentifier . '</td>
					<td>' . $boardTitle . '</td>
					<td>' . $boardDateAdded . '</td>
					<td>' . $actionHTML . '</td>
				</tr>';
	}
	$dat .= '
			<table class="postlists">
				<thead>
					<tr>
						<th>Board UID</th>
						<th>Board identifier</th>
						<th>Board title</th>
						<th>Date added</th>
						<th>View</th>
					</tr>
				</thead>
				<tbody>
					' . $boardsHTML . '
				</tbody>
			</table>';
	return $dat;
}

function buildThreadNavButtons(array $threadList, int $threadInnerIterator, int $threadsPerPage): string {
	if (!$threadList || !isset($threadList[$threadInnerIterator]['thread'])) return '';
	
	$offset = intdiv($threadInnerIterator, $threadsPerPage) * $threadsPerPage;

	// Slice the list to only the current page range
	$threadList = array_slice($threadList, $offset, $threadsPerPage);
	$currentIndex = $threadInnerIterator % $threadsPerPage;
	
	$upArrow = '';
	$downArrow = '';
	$postFormButton = '<a title="Go to post form" href="#postform">&#9632;</a>';
	
	// Up arrow (previous thread)
	if ($currentIndex > 0 && isset($threadList[$currentIndex - 1]['thread'])) {
		$aboveThread = $threadList[$currentIndex - 1]['thread'];
		$upArrow = '<a title="Go to above thread" href="#t' . htmlspecialchars($aboveThread['boardUID']) . '_' . htmlspecialchars($aboveThread['post_op_number']) . '">&#9650;</a>';
	}
	
	// Down arrow (next thread)
	if ($currentIndex < count($threadList) - 1 && isset($threadList[$currentIndex + 1]['thread'])) {
		$belowThread = $threadList[$currentIndex + 1]['thread'];
		$downArrow = '<a title="Go to below thread" href="#t' . htmlspecialchars($belowThread['boardUID']) . '_' . htmlspecialchars($belowThread['post_op_number']) . '">&#9660;</a>';
	}
	
	return $postFormButton . $upArrow . $downArrow;
}	

function fullURL(): string {
	return '//'.$_SERVER['HTTP_HOST'];
}

function drawAdminLoginForm(string $adminUrl) {
	return "
		<form action=\"$adminUrl\" method=\"post\">
			<table class=\"formtable centerBlock\">
				<tbody>
					<tr>
						<td class='postblock'><label for=\"username\">Username</label></td>
						<td><input type=\"text\" name=\"username\" id=\"username\" value=\"\" class=\"inputtext\"></td>
					</tr>
					<tr>
						<td class='postblock'><label for=\"password\">Password</label></td>
						<td><input type=\"password\" name=\"password\" id=\"password\" value=\"\" class=\"inputtext\"></td>
					</tr>
				</tbody>
			</table>
			<button type=\"submit\" name=\"mode\" value=\"admin\">Login</button>
		</form>";
}
	
function drawAdminTheading(&$dat, $staffSession) {
	$username = htmlspecialchars($staffSession->getUsername(), ENT_QUOTES, 'UTF-8');
	$roleEnum = $staffSession->getRoleLevel();
	$roleName = htmlspecialchars($roleEnum->displayRoleName(), ENT_QUOTES, 'UTF-8');
	
	$loggedInInfo = '';
		
	if($roleEnum !== \Kokonotsuba\Root\Constants\userRole::LEV_NONE) {
		$loggedInInfo = "<div class=\"username\">Logged in as $username ($roleName)</div>";
	}
	
	$html = "<div class=\"theading3\"><h2>Administrator mode</h2>$loggedInInfo</div>";
	
	$dat .= $html;
	return $html;
}	

function drawPushPostForm(&$dat, $pushPostCharacterLimit, $url, $postNumber, $post_uid) {		
	$dat .= '<form id="push-post-form" method="POST" action="'.$url.'">
				<input type="hidden" name="">
				<input type="hidden" name="push-post-post-uid" value="'.htmlspecialchars($post_uid).'">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="push-post-post-num">Post Number</label></td>
							<td><span id="push-post-post-num">'. $postNumber.'</span></td>
						</tr>
						<tr>
							<td class="postblock"> <label for="push-post-username">Username</label> </td>
							<td> <input id="push-post-username" name="push-post-username" maxlength="'.$pushPostCharacterLimit.'"> </td>
						</tr>
						<tr>
							<td class="postblock"> <label for="push-post-comment">Comment</label> </td>
							<td> <textarea id="push-post-comment" name="push-post-comment" maxlength="'.$pushPostCharacterLimit.'"></textarea> </td>
						</tr>
						<tr>
							<td class="postblock"></td>
							<td><button type="submit" name="push-post-submit" value="push it!">Push post</button></td>
						</tr>
					</tbody>
				</table>
		</form>';
}

	
