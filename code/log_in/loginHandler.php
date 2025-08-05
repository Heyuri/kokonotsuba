<?php

// Handle account sessions for koko

class loginSessionHandler {
	private readonly int $loginTimeout; // in seconds

	public function __construct(int $loginTimeout = 1800) {
		$this->loginTimeout = $loginTimeout;
	}

	public function login(staffAccount $account): bool {
		$this->startSession();

		if (!method_exists($account, 'getId') || !method_exists($account, 'getUsername') || !method_exists($account, 'getRoleLevel')) {
			return false;
		}

		session_regenerate_id(true);

		$_SESSION['accountUID'] = $account->getId();
		$_SESSION['username'] = $account->getUsername();
		$_SESSION['role_level'] = $account->getRoleLevel()->value;
		$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
		$_SESSION['last_activity'] = time();

		return true;
	}

	public function isLoggedIn(): bool {
		$this->startSession();

		if (empty($_SESSION['accountUID'])) {
			return false;
		}

		$lastActivity = $_SESSION['last_activity'] ?? 0;
		if ((time() - $lastActivity) > $this->loginTimeout) {
			$this->logout();
			return false;
		}

		$currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
		if (($_SESSION['user_agent'] ?? '') !== $currentUserAgent) {
			$this->logout();
			return false;
		}

		$_SESSION['last_activity'] = time();
		return true;
	}

	public function logout(): void {
		$this->startSession();

		$_SESSION = [];
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params["path"],
				$params["domain"],
				$params["secure"],
				$params["httponly"]
			);
		}
		session_destroy();
	}

	private function startSession(): void {
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
	}

	public function updateSessionData(staffAccount $account): void {
		$this->startSession();

		// Only update fields that can change during a session, e.g. username, role level, last activity
		$_SESSION['username'] = $account->getUsername();
		$_SESSION['role_level'] = $account->getRoleLevel()->value;
		$_SESSION['last_activity'] = time();

		// Keep user_agent and accountUID intact, no session_regenerate_id() here
	}

}

