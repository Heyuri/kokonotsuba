<?php
// Represent active staff session
class staffAccountFromSession {
	private $uid, $username, $lastActivity, $roleLevel, $userAgent;
	
	public function __construct() {
		$this->uid = $_SESSION['accountUID'] ?? null;
		$this->username = $_SESSION['username'] ?? null;
		$this->lastActivity = $_SESSION['last_activity'] ?? null;
		$this->roleLevel = $_SESSION['role_level'] ?? 0;
		$this->userAgent = $_SESSION['user_agent'] ?? null;
	}
	
	public function getUID(): ?int { 
		return $this->uid; 
	}

	public function getUsername(): string {
		return $this->username ?? "Nameless";
	}

	public function getLastActivity(): string {
		return $this->lastActivity ?? "N/A";
	}

	public function getRoleLevel(): \Kokonotsuba\Root\Constants\userRole {
		return \Kokonotsuba\Root\Constants\userRole::tryFrom($this->roleLevel);
	}

	public function getUserAgent(): ?string {
		return $this->userAgent;
	}

}
