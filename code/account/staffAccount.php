<?php
//class for representing staff and registered user accounts
class staffAccount {
	public readonly string $username;
	public readonly int $role;
	public readonly string $password_hash;
	public readonly int $id;
	public readonly int $number_of_actions;
	public readonly string $date_added; 
	public readonly ?string $last_login;
	
	public function getUsername(): string { 
		return $this->username; 
	}
	
	public function getRoleLevel(): \Kokonotsuba\Root\Constants\userRole { 
		return \Kokonotsuba\Root\Constants\userRole::tryFrom($this->role); 
	}
	
	public function getPasswordHash() { 
		return $this->password_hash; 
	}
	
	public function getId() { 
		return $this->id; 
	}
	
	public function getLastLogin() { 
		return $this->last_login; 
	}

	public function getNumberOfActions() {
		return $this->number_of_actions;
	}
}
