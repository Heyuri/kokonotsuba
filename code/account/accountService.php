<?php

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class accountService {
	public function __construct(
        private readonly accountRepository $accountRepository, 
        private readonly actionLoggerService $actionLoggerService) {}
	
	public function handleAccountDelete(int $id) {
		$this->accountRepository->deleteAccountByID($id);	
	}

	public function handleAccountDemote(int $id) {
		$account = $this->accountRepository->getAccountByID($id);
		
		if($account->getRoleLevel()->value - 1 == \Kokonotsuba\Root\Constants\userRole::LEV_NONE->value) return; # == is for PHP7 compatibility, change to === in future for PHP8
		
		$this->accountRepository->demoteAccountByID($id);
	}

	public function handleAccountPromote(int $id) {
		$account = $this->accountRepository->getAccountByID($id);
	
		if($account->getRoleLevel()->value + 1 == \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value + 1) return; # == is for PHP7 compatibility, change to === in future for PHP8
	
		$this->accountRepository->promoteAccountByID($id);
	}

	public function handleAccountCreation(bool $isHashed, string $password, string $username, int $role) {
		// don't hash the password if its being passed as hashed from the request
		if(!$isHashed) {
			$passwordHash = password_hash($password, PASSWORD_DEFAULT);
		} else {
			$passwordHash = $password;
		}

		$this->accountRepository->addNewAccount($username, $role, $passwordHash);
		$this->actionLoggerService->logAction("Registered a new account ($username)", GLOBAL_BOARD_UID);
	}

	public function handleAccountPasswordReset(staffAccountFromSession $staffAccountFromSession, string $newAccountPasswordForReset) {
		$loginSessionHandler = new loginSessionHandler;
		$accountID = $staffAccountFromSession->getUID();
		
		// hash the password
		$passwordHash = password_hash($newAccountPasswordForReset, PASSWORD_DEFAULT);

		// update the account in database
		$this->accountRepository->updateAccountPasswordHashById($accountID, $passwordHash);

		//refresh session values
		$accountAfterPasswordUpdate = $this->accountRepository->getAccountById($accountID);
		$loginSessionHandler->login($accountAfterPasswordUpdate);

		$this->actionLoggerService->logAction("Reset password", GLOBAL_BOARD_UID);
	}
	
}