<?php

class accountRepository {
	public function __construct(
        private DatabaseConnection $databaseConnection,
        private readonly string $accountTable,
	) {}

	public function getAllAccounts() {
		$query = "SELECT * FROM {$this->accountTable} ORDER BY role DESC";
		return $this->databaseConnection->fetchAllAsClass($query, [], 'staffAccount');
	}

	public function getAccountByID($id) {
		$query = "SELECT * FROM {$this->accountTable} WHERE id = :id";
		$params = [':id' => $id];
		return $this->databaseConnection->fetchAsClass($query, $params, 'staffAccount');
	}

	public function getAccountByUsername($name) {
		$query = "SELECT * FROM {$this->accountTable} WHERE username = :username";
		$params = [':username' => $name];
		return $this->databaseConnection->fetchAsClass($query, $params, 'staffAccount');
	}

	public function deleteAccountByID($id) {
		$query = "DELETE FROM {$this->accountTable} WHERE id = :id";
		$params = [':id' => $id];
		return $this->databaseConnection->execute($query, $params);
	}

	public function addNewAccount($username, $role, $passwordHash) {
		$query = "INSERT INTO {$this->accountTable} (username, role, password_hash, date_added) 
				  VALUES (:username, :role, :password_hash, CURRENT_TIMESTAMP)";
		$params = [
			':username' => $username,
			':role' => $role,
			':password_hash' => $passwordHash
		];
		return $this->databaseConnection->execute($query, $params);
	}

	public function updateLastLoginByID($id) {
		$query = "UPDATE {$this->accountTable} SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
		$params = [':id' => $id];
		return $this->databaseConnection->execute($query, $params);
	}
	
	public function updateAccountPasswordHashById($id, $passwordHash) {
		$query = "UPDATE {$this->accountTable} SET password_hash = :hash WHERE id = :id";
		$params = [
			':id' => $id,
			':hash' => $passwordHash
		];
		return $this->databaseConnection->execute($query, $params);
	}
	
	public function promoteAccountByID($id) {
		$query = "UPDATE {$this->accountTable} SET role = role + 1 WHERE id = :id";
		$params = [':id' => $id];
		return $this->databaseConnection->execute($query, $params);
	}
	
	public function demoteAccountByID($id) {
		$query = "UPDATE {$this->accountTable} SET role = role - 1 WHERE id = :id";
		$params = [':id' => $id];
		return $this->databaseConnection->execute($query, $params);
	}
	
	public function incrementAccountActionRecordByID($id) {
		$query = "UPDATE {$this->accountTable} SET number_of_actions = number_of_actions + 1 WHERE id = :id";
		$params = [':id' => $id];
		return $this->databaseConnection->execute($query, $params);
	}
}