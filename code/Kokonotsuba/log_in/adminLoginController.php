<?php

// handle logging in for staff


namespace Kokonotsuba\log_in;

use Kokonotsuba\account\accountRepository;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\error\softErrorHandler;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class adminLoginController {
	private const MAX_ATTEMPTS = 5;
	private const BASE_LOCKOUT_SECONDS = 30;

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

		// Brute force protection: check if locked out
		$attempts = $_SESSION['login_attempts'] ?? 0;
		$lockoutUntil = $_SESSION['login_lockout_until'] ?? 0;

		if ($attempts >= self::MAX_ATTEMPTS && time() < $lockoutUntil) {
			$remaining = $lockoutUntil - time();
			$this->softErrorHandler->errorAndExit("Too many failed login attempts. Please wait {$remaining} seconds.");
			return;
		}

		// Reset lockout if the cooldown has expired
		if ($attempts >= self::MAX_ATTEMPTS && time() >= $lockoutUntil) {
			$_SESSION['login_attempts'] = 0;
			$attempts = 0;
		}

		$account = $this->accountRepository->getAccountByUsername($username);
		
		if($account && $this->authenticationHandler->verifyPasswordHash($password, $account)) {
			// Successful login: reset attempt counter
			unset($_SESSION['login_attempts'], $_SESSION['login_lockout_until']);
			$this->loginSessionHandler->login($account);
			$this->accountRepository->updateLastLoginByID($account->getId());
		} else {
			$attempts++;
			$_SESSION['login_attempts'] = $attempts;

			if ($attempts >= self::MAX_ATTEMPTS) {
				// Exponential backoff: 30s, 60s, 120s, ...
				$multiplier = max(1, $attempts - self::MAX_ATTEMPTS + 1);
				$_SESSION['login_lockout_until'] = time() + (self::BASE_LOCKOUT_SECONDS * $multiplier);
			}

			$this->actionLoggerService->logAction("Failed attempted log-in for $username", GLOBAL_BOARD_UID);
			$this->softErrorHandler->errorAndExit("One of the details you filled was incorrect!");
		}
		
	}

	public function adminLogout(): void {
		$this->loginSessionHandler->logout();
	}

}