<?php

namespace Kokonotsuba\account;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for staff account records. */
class accountRepository extends baseRepository {
	public function __construct(
        databaseConnection $databaseConnection,
        string $accountTable,
	) {
		parent::__construct($databaseConnection, $accountTable);
	}

	/**
	 * Fetch all staff accounts ordered by role level descending.
	 *
	 * @return staffAccount[] Array of staffAccount objects.
	 */
	public function getAllAccounts() {
		return $this->findAll('role', 'DESC', '\Kokonotsuba\account\staffAccount');
	}

	/**
	 * Fetch a staff account by its primary key.
	 *
	 * @param int|string $id Account primary key.
	 * @return staffAccount|null
	 */
	public function getAccountByID($id) {
		return $this->findBy('id', $id, '\Kokonotsuba\account\staffAccount');
	}

	/**
	 * Fetch a staff account by username.
	 *
	 * @param string $name Username to look up.
	 * @return staffAccount|null
	 */
	public function getAccountByUsername($name) {
		return $this->findBy('username', $name, '\Kokonotsuba\account\staffAccount');
	}

	/**
	 * Delete a staff account by primary key.
	 *
	 * @param int|string $id Account primary key.
	 * @return void
	 */
	public function deleteAccountByID($id) {
		$this->deleteWhere('id', $id);
	}

	/**
	 * Insert a new staff account record.
	 *
	 * @param string $username     Account username.
	 * @param int    $role         Role level integer.
	 * @param string $passwordHash Bcrypt password hash.
	 * @return bool True on success.
	 */
	public function addNewAccount($username, $role, $passwordHash) {
		$query = "INSERT INTO {$this->table} (username, role, password_hash, date_added) 
				  VALUES (:username, :role, :password_hash, CURRENT_TIMESTAMP)";
		$params = [
			':username' => $username,
			':role' => $role,
			':password_hash' => $passwordHash
		];
		return $this->query($query, $params);
	}

	/**
	 * Update the last_login timestamp for the given account to now.
	 *
	 * @param int|string $id Account primary key.
	 * @return bool True on success.
	 */
	public function updateLastLoginByID($id) {
		return $this->query("UPDATE {$this->table} SET last_login = CURRENT_TIMESTAMP WHERE id = :id", [':id' => $id]);
	}
	
	/**
	 * Replace the password hash for the given account.
	 *
	 * @param int|string $id           Account primary key.
	 * @param string     $passwordHash New bcrypt password hash.
	 * @return void
	 */
	public function updateAccountPasswordHashById($id, $passwordHash) {
		$this->updateWhere(['password_hash' => $passwordHash], 'id', $id);
	}
	
	/**
	 * Increase the role level of an account by 1.
	 *
	 * @param int|string $id Account primary key.
	 * @return bool True on success.
	 */
	public function promoteAccountByID($id) {
		return $this->query("UPDATE {$this->table} SET role = role + 1 WHERE id = :id", [':id' => $id]);
	}
	
	/**
	 * Decrease the role level of an account by 1.
	 *
	 * @param int|string $id Account primary key.
	 * @return bool True on success.
	 */
	public function demoteAccountByID($id) {
		return $this->query("UPDATE {$this->table} SET role = role - 1 WHERE id = :id", [':id' => $id]);
	}
	
	/**
	 * Increment the number_of_actions counter for the given account by 1.
	 *
	 * @param int|string $id Account primary key.
	 * @return bool True on success.
	 */
	public function incrementAccountActionRecordByID($id) {
		return $this->query("UPDATE {$this->table} SET number_of_actions = number_of_actions + 1 WHERE id = :id", [':id' => $id]);
	}
}