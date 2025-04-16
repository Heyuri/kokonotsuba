<?php
// Handle account sessions for koko
class accountRequestHandler {
	private $config;
	
	public function __construct($board) { 
		$this->config = $board->loadBoardConfig();
	}
	
	public function handleAccountDelete() {
		$AccountIO = AccountIO::getInstance();
	
		$id = $_GET['del'] ??  '';
		$AccountIO->deleteAccountByID($id);	
	}

	public function handleAccountDemote() {
		$AccountIO = AccountIO::getInstance();
	
		$id = $_GET['dem'] ?? -1;
		$account = $AccountIO->getAccountByID($id);
		
		if($account->getRoleLevel() - 1 == $this->config['roles']['LEV_NONE']) return; # == is for PHP7 compatibility, change to === in future for PHP8
		
		$AccountIO->demoteAccountByID($id);
	}

	public function handleAccountPromote() {
		$AccountIO = AccountIO::getInstance();
	
		$id = $_GET['up'] ??  '';
		$account = $AccountIO->getAccountByID($id);
	
		if($account->getRoleLevel() + 1 == $this->config['roles']['LEV_ADMIN'] + 1) return; # == is for PHP7 compatibility, change to === in future for PHP8
	
		$AccountIO->promoteAccountByID($id);
	}

	public function handleAccountCreation($board) {
		$staffSession = new staffAccountFromSession;
		$actionLogger = actionLogger::getInstance();
		$AccountIO = AccountIO::getInstance();

		$passwordHash = $_POST['passwd'] ?? '';
		$isHashed = $_POST['ishashed'] ?? '';
		$username = $_POST['usrname'] ?? '';
		$role = $_POST['role'] ?? '';
	
		if(!$isHashed) $passwordHash = password_hash($passwordHash, PASSWORD_DEFAULT);

		$AccountIO->addNewAccount($username, $role, $passwordHash);
		$actionLogger->logAction("Registered a new account ($username)", $board->getBoardUID());
	}

	public function handleAccountPasswordReset($board) {
		$actionLogger = actionLogger::getInstance();
		$AccountIO = AccountIO::getInstance();
		
		$loginSessionHandler = new loginSessionHandler;
		$staffSession = new staffAccountFromSession;
		$accountID = $_POST['id'] ?? -1;
		$newAccountPassword = $_POST['new_account_password'] ?? -1;
		$account = $AccountIO->getAccountById($accountID);
		
		$currentPasswordHash = $account->getPasswordHash();
		$currentUserPasswordHashFromSession = $staffSession->getHashedPassword();
		
		if($currentPasswordHash !== $currentUserPasswordHashFromSession) throw new Exception("You cannot change the password of a different account!");

		$AccountIO->updateAccountPasswordHashById($accountID, $newAccountPassword);

		//refresh session values
		$accountAfterPasswordUpdate = $AccountIO->getAccountById($accountID);
		$loginSessionHandler->login($accountAfterPasswordUpdate);

		$actionLogger->logAction("Reset password", $board->getBoardUID());
	}
	
}


