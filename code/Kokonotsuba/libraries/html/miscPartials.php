<?php

namespace Kokonotsuba\libraries\html;

use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Puchiko\strings\sanitizeStr;

function generateAdminLinkButtons(string $liveIndexFile, string $staticIndexFile, moduleEngine $moduleEngine, string $adminLinkHtml, request $request): string {
	$linksAboveBar =  '
		<ul id="adminNavBar">
			<li class="adminNavLink"><a href="'.$staticIndexFile.'?'.$request->getRequestTime().'">' . _T('admin_nav_return') . '</a></li>
			<li class="adminNavLink"><a href="'.$liveIndexFile.'?page=1">' . _T('admin_nav_live_frontend') . '</a></li>
			' . $adminLinkHtml;

	$moduleEngine->dispatch('LinksAboveBar', array(&$linksAboveBar));
	
    $linksAboveBar .= "</ul>";
	return $linksAboveBar;
}

/**
 * The nav bar for user-facing module pages (reports, blotter, ban notice, ...).
 *
 * The staff equivalent is generateAdminLinkButtons(); readers only need the way back to the
 * board, so this is just the Return link, carrying the same cache-busting query string.
 */
function generateUserNavBar(string $staticIndexFile, request $request): string {
	return '<div class="userNavBar">[<a href="' . htmlspecialchars($staticIndexFile) . '?' . $request->getRequestTime() . '">'
		. _T('return') . '</a>]</div>';
}

/**
 * The built-in staff nav destinations, as data, with anything this account's role can't reach
 * already dropped.
 *
 * Shared by the nav above admin pages and the sticky staff nav (module/staffNav) so the two
 * can't drift apart. Entries carry the same shape module entries do (see StaffNavListenerTrait);
 * most are primary destinations and stay at the top level, so a group is the exception.
 *
 * @param  bool $includeLiveFrontend Whether to lead with the link back to the live board.
 *              False where that link is already rendered separately.
 * @return array<int, array{key: string, label: string, url: string, title: string, group: string}>
 */
function getStaffNavCoreEntries(string $liveIndexFile, userRole $actionLogRole, bool $includeLiveFrontend = true): array {
	$roleLevel = getRoleLevelFromSession();

	$destinations = [
		['key' => 'liveFrontend', 'query' => ['page' => 1], 'label' => _T('admin_nav_live_frontend'), 'title' => '', 'role' => userRole::LEV_USER],
		['key' => 'actionLog', 'query' => ['mode' => 'actionLog'], 'label' => _T('admin_nav_action_log'), 'title' => _T('admin_nav_action_log_title'), 'role' => $actionLogRole],
		['key' => 'account', 'query' => ['mode' => 'account'], 'label' => _T('admin_nav_accounts'), 'title' => _T('admin_nav_accounts_title'), 'role' => userRole::LEV_USER],
		['key' => 'managePosts', 'query' => ['mode' => 'managePosts'], 'label' => _T('admin_nav_posts'), 'title' => _T('admin_nav_posts_title'), 'role' => userRole::LEV_JANITOR],
		['key' => 'rebuild', 'query' => ['mode' => 'rebuild'], 'label' => _T('admin_nav_rebuild'), 'title' => _T('admin_nav_rebuild_title'), 'role' => userRole::LEV_JANITOR, 'group' => 'rebuilding'],
		['key' => 'boards', 'query' => ['mode' => 'boards'], 'label' => _T('admin_nav_boards'), 'title' => _T('admin_nav_boards_title'), 'role' => userRole::LEV_ADMIN],
		['key' => 'globalConfig', 'query' => ['mode' => 'globalConfig'], 'label' => _T('admin_nav_global_config'), 'title' => _T('admin_nav_global_config_title'), 'role' => userRole::LEV_ADMIN],
	];

	$entries = [];
	foreach ($destinations as $destination) {
		if (!$includeLiveFrontend && $destination['key'] === 'liveFrontend') {
			continue;
		}

		if ($roleLevel->isLessThan($destination['role'])) {
			continue;
		}

		$entries[] = [
			'key' => $destination['key'],
			'label' => $destination['label'],
			'url' => $liveIndexFile . '?' . http_build_query($destination['query']),
			'title' => $destination['title'],
			'group' => $destination['group'] ?? '',
		];
	}

	return $entries;
}

/** One core nav entry as the list item the admin nav bar is built from. */
function renderStaffNavCoreEntry(array $entry): string {
	$titleHtml = ($entry['title'] ?? '') !== '' ? ' title="' . htmlspecialchars($entry['title']) . '"' : '';

	return '<li class="adminNavLink"><a' . $titleHtml . ' href="' . htmlspecialchars($entry['url']) . '">'
		. htmlspecialchars($entry['label']) . '</a></li>';
}

function drawAccountTable(string $liveIndexFile, array $accounts, string $csrfHiddenInput) {
	$dat = '';
	$accountsHTML = '';
	
	foreach ($accounts as $account) {
			$accountID = $account->getId();
			$accountUsername = htmlspecialchars($account->getUsername());
			$accountRoleLevel = $account->getRoleLevel();
			$accountNumberOfActions = $account->getNumberOfActions();
			$accountLastLogin = $account->getLastLogin() ?? '<i>Never</i>';
	
			$viewHTML = '[<a title="View account" href="' . htmlspecialchars($liveIndexFile) . '?mode=viewStaffAccount&amp;id=' . (int)$accountID . '">View</a>]';

			$accountsHTML .= '<tr> 
					<td class="colSelect"><input type="checkbox" name="del_ids[]" value="' . (int)$accountID . '"></td>
					<td class="colAccountID">' . (int)$accountID . '</td>
					<td class="colUsername">' . sanitizeStr($accountUsername) . ' </td>
					<td class="colRoleLevel">' . sanitizeStr($accountRoleLevel->displayRoleName()) . '</td>
					<td class="colNumberofActions">' . (int)$accountNumberOfActions . '</td>
					<td class="colLastLogin">' . $accountLastLogin . '</td>
					<td class="colActions">' . $viewHTML . '</td>
				</tr>';
	}
	$dat .= '
			<form method="POST" action="' . sanitizeStr($liveIndexFile) . '?mode=handleAccountAction">
			' . $csrfHiddenInput . '
			<div class="tableViewportWrapper">
			<table id="tableStaffList" class="postlists">
				<thead>
					<tr>
						<th class="colSelect"></th>
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
			</table>
			</div>
			<div class="buttonSection">
				<input type="submit" name="bulk_delete" value="Delete selected">
			</div>
			</form>';
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
		
			$actionHTML = '[<a title="View board" href="' . sanitizeStr($liveIndexFile) . '?mode=boards&view=' . sanitizeStr($boardUID) . '">View</a>] ';
			$boardsHTML .= '
				<tr> 
					<td>' . sanitizeStr($boardUID) . '</td>
					<td>' . sanitizeStr($boardIdentifier) . '</td>
					<td>' . sanitizeStr($boardTitle) . '</td>
					<td>' . sanitizeStr($boardDateAdded) . '</td>
					<td>' . $actionHTML . '</td>
				</tr>';
	}
	$dat .= '
			<div class="tableViewportWrapper">
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
			</table>
			</div>';
	return $dat;
}

function buildThreadNavButtons(array $threadList, int $threadInnerIterator): string {
	if (!$threadList || !isset($threadList[$threadInnerIterator])) return '';
	
	$upArrow = '';
	$downArrow = '';
	$postFormButton = '<a title="Go to post form" href="#postform">&#9632;</a>';
	
	// Up arrow (previous thread on this page)
	if ($threadInnerIterator > 0 && isset($threadList[$threadInnerIterator - 1])) {
		$aboveThread = $threadList[$threadInnerIterator - 1]->getThread();
		$upArrow = '<a title="Go to above thread" href="#t' . htmlspecialchars($aboveThread->getBoardUID()) . '_' . htmlspecialchars($aboveThread->getOpNumber()) . '">&#9650;</a>';
	}
	
	// Down arrow (next thread on this page)
	if ($threadInnerIterator < count($threadList) - 1 && isset($threadList[$threadInnerIterator + 1])) {
		$belowThread = $threadList[$threadInnerIterator + 1]->getThread();
		$downArrow = '<a title="Go to below thread" href="#t' . htmlspecialchars($belowThread->getBoardUID()) . '_' . htmlspecialchars($belowThread->getOpNumber()) . '">&#9660;</a>';
	}
	
	return $postFormButton . $upArrow . $downArrow;
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
	$username = sanitizeStr($staffSession->getUsername());
	$roleEnum = $staffSession->getRoleLevel();
	$roleName = sanitizeStr($roleEnum->displayRoleName());
	
	$html = '<div class="theading3"><h2>' . _T('admin_top') . '</h2></div>';
	
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

function getThreadTitle(string $boardUrl, string $boardTitle): string {
	return '<span class="overboardThreadBoardTitle"><a href="' . htmlspecialchars($boardUrl).'">' . $boardTitle . '</a></span>';
}
