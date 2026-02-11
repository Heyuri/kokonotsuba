<?php

namespace Kokonotsuba\libraries;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\account\staffAccount;
use Kokonotsuba\account\accountRepository;
use Kokonotsuba\log_in\loginSessionHandler as Log_inLoginSessionHandler;
use Kokonotsuba\userRole;

//This file contains functions for koko management mode and related features
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
	
	return $roleLevel->isAtLeast(userRole::LEV_USER);
}

function getRoleLevelFromSession(): userRole {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();

	return $roleLevel;
}

function getIdFromSession(): ?int {
	$staffSession = new staffAccountFromSession;
	$accountUid = $staffSession->getUID();

	return $accountUid;
}

function updateAccountSession(accountRepository $accountRepository, Log_inLoginSessionHandler $loginSessionHandler): void {
	// don't bother if the user isn't logged in
	if(!isLoggedIn()) {
		return;
	}

	$staffSession = new staffAccountFromSession;

	$accountId = $staffSession->getUID();

	$account = $accountRepository->getAccountByID($accountId);

	// just destroy the session if the account no longer exists
	if(!$account || !($account instanceof staffAccount)) {
		$loginSessionHandler->logout();
	}

	// now update the session
	$loginSessionHandler->updateSessionData($account);
}