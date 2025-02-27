<?php
//This file contains functions for koko management mode and related features

function adminLogin($AccountIO, $globalHTML) {
	if(!$_POST['username']) return;
	if(!$_POST['password']) return;

	$authenticationHandler = new authenticationHandler;
	$loginSessionHandler = new loginSessionHandler;
	
	$account = $AccountIO->getAccountByUsername($_POST['username']);
	if(!$account) $globalHTML->error("One of the details you filled was incorrect!");

	
	$userEnteredPassword = $_POST['password'] ?? '';
	if($authenticationHandler->verifyPasswordHash($userEnteredPassword, $account)) {
		$loginSessionHandler->login($account);
		$AccountIO->updateLastLoginByID($account->getId());
	} else {
		$globalHTML->error("One of the details you filled was incorrect!");
	}
	
}

function getCurrentStorageSizeFromSelectedBoards(array $boards) {
	$FileIO = PMCLibrary::getFileIOInstance();
	$totalBoardsStorageSize = 0;

	foreach($boards as $board) {
		$totalBoardsStorageSize += $FileIO->getCurrentStorageSize($board);
	}
	return $totalBoardsStorageSize;
}

