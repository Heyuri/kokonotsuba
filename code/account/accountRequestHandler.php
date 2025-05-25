<?php

// Handle account sessions for koko

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class accountRequestHandler {
	private readonly AccountIO $AccountIO;
	private actionLogger $actionLogger;
	
	public function __construct(AccountIO $AccountIO, actionLogger $actionLogger) { 
		$this->AccountIO = $AccountIO;
		$this->actionLogger = $actionLogger;
	}
	
	public function handleAccountDelete() {
		$id = $_GET['del'] ??  '';
		$this->AccountIO->deleteAccountByID($id);	
	}

	public function handleAccountDemote() {
		$id = $_GET['dem'] ?? -1;
		$account = $this->AccountIO->getAccountByID($id);
		
		if($account->getRoleLevel()->value - 1 == \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value) return; # == is for PHP7 compatibility, change to === in future for PHP8
		
		$this->AccountIO->demoteAccountByID($id);
	}

	public function handleAccountPromote() {
		$id = $_GET['up'] ??  '';
		$account = $this->AccountIO->getAccountByID($id);
	
		if($account->getRoleLevel()->value + 1 == \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value + 1) return; # == is for PHP7 compatibility, change to === in future for PHP8
	
		$this->AccountIO->promoteAccountByID($id);
	}

	public function handleAccountCreation() {
		$passwordHash = $_POST['passwd'] ?? '';
		$isHashed = $_POST['ishashed'] ?? '';
		$username = $_POST['usrname'] ?? '';
		$role = $_POST['role'] ?? '';
	
		if(!$isHashed) $passwordHash = password_hash($passwordHash, PASSWORD_DEFAULT);

		$this->AccountIO->addNewAccount($username, $role, $passwordHash);
		$this->actionLogger->logAction("Registered a new account ($username)", GLOBAL_BOARD_UID);
	}

	public function handleAccountPasswordReset() {
		$loginSessionHandler = new loginSessionHandler;
		$staffSession = new staffAccountFromSession;
		$accountID = $staffSession->getUID();
		$newAccountPassword = $_POST['new_account_password'] ?? -1;
		
		$this->AccountIO->updateAccountPasswordHashById($accountID, $newAccountPassword);

		//refresh session values
		$accountAfterPasswordUpdate = $this->AccountIO->getAccountById($accountID);
		$loginSessionHandler->login($accountAfterPasswordUpdate);

		$this->actionLogger->logAction("Reset password", GLOBAL_BOARD_UID);
	}
	
}


