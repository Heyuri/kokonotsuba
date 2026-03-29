<?php

namespace Kokonotsuba\account;

use Kokonotsuba\userRole;

// Represent active staff session
class staffAccountFromSession {
	public function __construct() {}
	
	public function getUID(): ?int { 
		return $_SESSION['accountUID'] ?? null; 
	}

	public function getUsername(): string {
		return $_SESSION['username'] ?? "Nameless";
	}

	public function getLastActivity(): string {
		return $_SESSION['last_activity'] ?? "N/A";
	}

	public function getRoleLevel(): userRole {
		return userRole::tryFrom($_SESSION['role_level'] ?? 0) ?? userRole::LEV_NONE;
	}

	public function getUserAgent(): ?string {
		return $_SESSION['user_agent'] ?? null;
	}

}
