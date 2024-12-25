<?php
//This file contains functions for koko management mode and related features

function adminLogin($AccountIO, $globalHTML) {
	if(!$_POST['username']) return;
	if(!$_POST['password']) return;

	$authenticationHandler = new authenticationHandler;
	$loginSessionHandler = new loginSessionHandler;
	
	$account = $AccountIO->getAccountByUsername($_POST['username']);
	if(!$account) $globalHTML->error("One of the details you filled was incorrect!");

	
	$userEnteredPassword = $_POST['password'];
	if($authenticationHandler->verifyPasswordHash($userEnteredPassword, $account)) {
		$loginSessionHandler->login($account);
		$AccountIO->updateLastLoginByID($account->getId());
	} else {
		$globalHTML->error("One of the details you filled was incorrect!");
	}
	
}

function handleAccountDelete($AccountIO) {
	$id = isset($_GET['del']) ? $_GET['del'] : '';
	$AccountIO->deleteAccountByID($id);	
}

function handleAccountDemote($AccountIO) {
	$id = $_GET['dem'] ?? -1;
	$account = $AccountIO->getAccountByID($id);
	
	if($account->getRoleLevel() - 1 === 0) return;
	
	$AccountIO->demoteAccountByID($id);
}

function handleAccountPromote($AccountIO) {
	$id = isset($_GET['up']) ? $_GET['up'] : '';
	$account = $AccountIO->getAccountByID($id);
	
	if($account->getRoleLevel() + 1 === 5) return;
	
	$AccountIO->promoteAccountByID($id);
}

function handleAccountCreation($AccountIO, $staffSession, $actionLogger, $board) {
	$passwordHash = $_POST['passwd'] ?? '';
	$isHashed = $_POST['ishashed'] ?? '';
	$username = $_POST['usrname'] ?? '';
	$role = $_POST['role'] ?? '';
	
	if(!$isHashed) $passwordHash = password_hash($passwordHash, PASSWORD_DEFAULT);

	$AccountIO->addNewAccount($username, $role, $passwordHash);
	$actionLogger->logAction("Registered a new account ($username)", $board->getBoardUID());
}

function handleAccountPasswordReset($AccountIO) {
	$staffSession = new staffAccountFromSession;
	$accountID = $_POST['id'] ?? -1;
	$newAccountPassword = $_POST['new_account_password'] ?? -1;
	$account = $AccountIO->getAccountById($accountID);
	
	$currentPasswordHash = $account->getPasswordHash();
	$currentUserPasswordHashFromSession = $staffSession->getHashedPassword();
	
	if($currentPasswordHash !== $currentUserPasswordHashFromSession) throw new Exception("You cannot change the password of a different account!");
	
	$AccountIO->updateAccountPasswordHashById($accountID, $newAccountPassword);
	$actionLogger->logAction("Reset password", $board->getBoardUID());
}

function getCurrentStorageSizeFromSelectedBoards(array $boards) {
	$FileIO = PMCLibrary::getInstance();
	$totalBoardsStorageSize = 0;

	foreach($boards as $boards) {
		$totalBoardsStorangeSize += $FileIO->getCurrentStorageSize($board);
	}
	return $totalBoardsStorageSize;
}

