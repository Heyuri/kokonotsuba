<?php
// Represent active staff session
class staffAccountFromSession {
	private $uid, $username, $lastActivity, $roleLevel, $userAgent, $hash;
	
	public function __construct() {
		$this->uid = $_SESSION['accountUID'] ?? null;
		$this->username = $_SESSION['username'] ?? null;
		$this->lastActivity = $_SESSION['last_activity'] ?? null;
		$this->roleLevel = $_SESSION['role_level'] ?? null;
		$this->number_of_actions = $_SESSION['number_of_actions'] ?? null;
		$this->userAgent = $_SESSION['user_agent'] ?? null;
		$this->hash = $_SESSION['hash'] ?? null;
	}
	
	public function getUID() { return $this->uid ?? "No UID"; }
	public function getUsername() { return $this->username ?? "Nameless"; }
	public function getLastActivity() { return $this->lastActivity ?? "N/A"; }
	public function getRoleLevel() { return $this->roleLevel ?? 0; }
	public function getNumberOfActions() { return $this->number_of_actions ?? 0; }
	public function getUserAgent() { return $this->userAgent ?? ''; }
	public function getHashedPassword() { return $this->hash ?? ''; }
}
