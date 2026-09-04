<?php

// handle logging in for staff


namespace Kokonotsuba\log_in;

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\account\accountRepository;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\request\request;

use function Kokonotsuba\libraries\_T;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class adminLoginController {
	public function __construct(
		private readonly actionLoggerService $actionLoggerService, 
		private readonly accountRepository $accountRepository, 
		private readonly loginSessionHandler $loginSessionHandler, 
		private readonly authenticationHandler $authenticationHandler,
		private readonly softErrorHandler $softErrorHandler,
		private readonly loginAttemptService $loginAttemptService,
		private readonly request $request) {}

	public function adminLogin(string $username, string $password) {
        // neither the username or password should be empty
        if(empty($username)) return;
		if(empty($password)) return;

		$ip = (string)$this->request->userIp();

		// brute force protection: the ledger, not the session, decides whether this may proceed
		$lockoutSeconds = $this->loginAttemptService->getLockoutSeconds($username, $ip);

		if($lockoutSeconds > 0) {
			$this->softErrorHandler->errorAndExit(_T('login_throttled', $lockoutSeconds), 429);
			return;
		}

		$account = $this->accountRepository->getAccountByUsername($username);
		
		if($account && $this->authenticationHandler->verifyPasswordHash($password, $account)) {
			// the earlier failures stop counting toward a lockout, but stay on record to warn about
			$this->loginAttemptService->recordSuccess($username, $ip);

			// Set session
			$this->loginSessionHandler->login($account);

			// log it
			$this->actionLoggerService->logAction("Logged in", GLOBAL_BOARD_UID, actionType::ACCOUNT_LOGIN);

			// update last login time
			$this->accountRepository->updateLastLoginByID($account->getId());
		} else {
			$accountId = $account ? (int)$account->getId() : null;

			$this->loginAttemptService->recordFailure(
				$username,
				$accountId,
				$ip,
				$this->request->getUserAgent('')
			);

			$this->actionLoggerService->logAction("Failed attempted log-in for $username", GLOBAL_BOARD_UID, actionType::ACCOUNT_LOGIN_FAILED);
			$this->softErrorHandler->errorAndExit("One of the details you filled was incorrect!");
		}
		
	}

	public function adminLogout(): void {
		$this->loginSessionHandler->logout();
	}

}
