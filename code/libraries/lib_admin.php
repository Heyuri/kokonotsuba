<?php
//This file contains functions for koko management mode and related features

function getCurrentStorageSizeFromSelectedBoards(array $boards) {
	$FileIO = PMCLibrary::getFileIOInstance();
	$totalBoardsStorageSize = 0;

	foreach($boards as $board) {
		$totalBoardsStorageSize += $FileIO->getCurrentStorageSize($board);
	}
	return $totalBoardsStorageSize;
}


/**
* Check if the account session role is at least a janitor
*/
function isActiveStaffSession(): bool {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();
	
	return $roleLevel->isStaff();
}

/**
 * Check if account session has a valid user role
 */

function isLoggedIn(): bool {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();
	
	return $roleLevel->isAtLeast(\Kokonotsuba\Root\Constants\userRole::LEV_USER);
}

function getRoleLevelFromSession(): \Kokonotsuba\Root\Constants\userRole {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();

	return $roleLevel;
}

function updateAccountSession(accountRepository $accountRepository, loginSessionHandler $loginSessionHandler): void {
	// don't bother if the user isn't logged in
	if(!isLoggedIn()) {
		return;
	}

	$staffSession = new staffAccountFromSession;

	$accountId = $staffSession->getUID();

	$account = $accountRepository->getAccountByID($accountId);

	// now update the session
	$loginSessionHandler->login($account);
}