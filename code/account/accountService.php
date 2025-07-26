<?php

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class accountService {
	public function __construct(
        private readonly accountRepository $accountRepository, 
        private readonly actionLoggerService $actionLoggerService) {}
	
	public function handleAccountDelete() {
		$id = $_GET['del'] ??  '';
		$this->accountRepository->deleteAccountByID($id);	
	}

	public function handleAccountDemote() {
		$id = $_GET['dem'] ?? -1;
		$account = $this->accountRepository->getAccountByID($id);
		
		if($account->getRoleLevel()->value - 1 == \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value) return; # == is for PHP7 compatibility, change to === in future for PHP8
		
		$this->accountRepository->demoteAccountByID($id);
	}

	public function handleAccountPromote() {
		$id = $_GET['up'] ??  '';
		$account = $this->accountRepository->getAccountByID($id);
	
		if($account->getRoleLevel()->value + 1 == \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value + 1) return; # == is for PHP7 compatibility, change to === in future for PHP8
	
		$this->accountRepository->promoteAccountByID($id);
	}

	public function handleAccountCreation() {
		$passwordHash = $_POST['passwd'] ?? '';
		$isHashed = $_POST['ishashed'] ?? '';
		$username = $_POST['usrname'] ?? '';
		$role = $_POST['role'] ?? '';
	
		if(!$isHashed) $passwordHash = password_hash($passwordHash, PASSWORD_DEFAULT);

		$this->accountRepository->addNewAccount($username, $role, $passwordHash);
		$this->actionLoggerService->logAction("Registered a new account ($username)", GLOBAL_BOARD_UID);
	}

	public function handleAccountPasswordReset() {
		$loginSessionHandler = new loginSessionHandler;
		$staffSession = new staffAccountFromSession;
		$accountID = $staffSession->getUID();
		$newAccountPassword = $_POST['new_account_password'] ?? -1;
		
		$this->accountRepository->updateAccountPasswordHashById($accountID, $newAccountPassword);

		//refresh session values
		$accountAfterPasswordUpdate = $this->accountRepository->getAccountById($accountID);
		$loginSessionHandler->login($accountAfterPasswordUpdate);

		$this->actionLoggerService->logAction("Reset password", GLOBAL_BOARD_UID);
	}
	
}