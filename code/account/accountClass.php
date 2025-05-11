<?php
//class for representing staff and registered user accounts
class staffAccount {
	public $username, $role, $password_hash, $id, $number_of_actions, $date_added, $last_login;
	
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
