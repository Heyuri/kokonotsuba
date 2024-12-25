<?php
// Handle account sessions for koko
class loginSessionHandler {
	public function login($account) {
		if ($account) {
			session_regenerate_id(true);
			$_SESSION['accountUID'] = $account->getId();
			$_SESSION['username'] = $account->getUsername();
			$_SESSION['last_activity'] = time();
			$_SESSION['role_level'] = $account->getRoleLevel();
			$_SESSION['hash'] = $account->getPasswordHash();
			$_SESSION['number_of_actions'] = $account->getNumberOfActions();
			$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
			
			session_write_close();
			return true;
		}
		return false;
	}

	public function isLoggedIn() {
		if (!isset($_SESSION['accountUID'])) return false;

		$timeout = 1800;
		if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
			$this->logout();
			return false;
		}
		$_SESSION['last_activity'] = time();

		if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
			$this->logout();
			return false;
		}

		return true;
	}

	public function logout() {
		session_unset();
		session_destroy();
		setcookie(session_name(), '', time() - 3600, '/');
	}
}

