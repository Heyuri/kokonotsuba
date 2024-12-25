<?php
/**
 * Account API
 *
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 */

class AccountIO {
	private $config, $dbSettings, $accountTable, $databaseConnection;
	private static $instance = null;

	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			self::$instance = new self($dbSettings);
		}
		return self::$instance;
	}
	
	public static function getInstance() {
		return self::$instance;
	}

	private function __construct($dbSettings) {
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
		$this->accountTable = $dbSettings['ACCOUNT_TABLE'];
	}

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
	
	public function updateAccountPasswordHashById($id, $password) {
		if(!$password) throw new Exception("Password was somehow left blank when resetting it.");
	
		$query = "UPDATE {$this->accountTable} SET password_hash = :hash WHERE id = :id";
		$params = [
			':id' => $id,
			':hash' => password_hash($password, PASSWORD_DEFAULT),
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
	
}

