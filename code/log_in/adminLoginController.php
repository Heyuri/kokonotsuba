<?php

// handle logging in for staff

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class adminLoginController {
	public function __construct(
		private readonly actionLoggerService $actionLoggerService, 
		private readonly accountRepository $accountRepository, 
		private readonly loginSessionHandler $loginSessionHandler, 
		private readonly authenticationHandler $authenticationHandler,
		private readonly softErrorHandler $softErrorHandler) {}

	public function adminLogin(string $username, string $password) {
        // neither the username or password should be empty
        if(empty($username)) return;
		if(empty($password)) return;

		
		$account = $this->accountRepository->getAccountByUsername($username);
		
		if($account && $this->authenticationHandler->verifyPasswordHash($password, $account)) {
			$this->loginSessionHandler->login($account);
			$this->accountRepository->updateLastLoginByID($account->getId());
		} else {
			$this->actionLoggerService->logAction("Failed attempted log-in for $username", GLOBAL_BOARD_UID);
			$this->softErrorHandler->errorAndExit("One of the details you filled was incorrect!");
		}
		
	}

	public function adminLogout(): void {
		$this->loginSessionHandler->logout();
	}

}