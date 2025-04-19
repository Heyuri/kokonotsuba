<?php

// handle logging in for staff

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

class adminLoginController {
	private readonly actionLogger $actionLogger;
	private readonly AccountIO $AccountIO;
	private readonly globalHTML $globalHTML;

	private readonly loginSessionHandler $loginSessionHandler;
	private readonly authenticationHandler $authenticationHandler;

	public function __construct(actionLogger $actionLogger, AccountIO $AccountIO, globalHTML $globalHTML, loginSessionHandler $loginSessionHandler, authenticationHandler $authenticationHandler) {
		$this->actionLogger = $actionLogger;
		$this->AccountIO = $AccountIO;
		$this->globalHTML = $globalHTML;
		
		$this->loginSessionHandler = $loginSessionHandler;
		$this->authenticationHandler = $authenticationHandler;
	}

	public function adminLogin(string $username, string $password) {
        // neither the username or password should be empty
        if(empty($username)) return;
		if(empty($password)) return;

		
		$account = $this->AccountIO->getAccountByUsername($username);
		
		if($account && $this->authenticationHandler->verifyPasswordHash($password, $account)) {
			$this->loginSessionHandler->login($account);
			$this->AccountIO->updateLastLoginByID($account->getId());
		} else {
			$this->actionLogger->logAction("Failed attempted log-in for $username", GLOBAL_BOARD_UID);
			$this->globalHTML->error("One of the details you filled was incorrect!");
		}
		
	}

	public function adminLogout(): void {
		$this->loginSessionHandler->logout();
	}

}