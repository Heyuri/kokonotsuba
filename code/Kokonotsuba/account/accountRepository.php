<?php

namespace Kokonotsuba\account;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\SqlExpression;

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
		$this->insert([
			'username' => $username,
			'role' => $role,
			'password_hash' => $passwordHash,
			'date_added' => SqlExpression::now(),
		]);
	}

	/**
	 * Update the last_login timestamp for the given account to now.
	 *
	 * @param int|string $id Account primary key.
	 * @return void
	 */
	public function updateLastLoginByID($id) {
		$this->updateWhere(['last_login' => SqlExpression::now()], 'id', $id);
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
	 * @return void
	 */
	public function promoteAccountByID($id) {
		$this->updateWhere(['role' => SqlExpression::increment('role')], 'id', $id);
	}
	
	/**
	 * Decrease the role level of an account by 1.
	 *
	 * @param int|string $id Account primary key.
	 * @return void
	 */
	public function demoteAccountByID($id) {
		$this->updateWhere(['role' => SqlExpression::decrement('role')], 'id', $id);
	}
	
	/**
	 * Increment the number_of_actions counter for the given account by 1.
	 *
	 * @param int|string $id Account primary key.
	 * @return void
	 */
	public function incrementAccountActionRecordByID($id) {
		$this->updateWhere(['number_of_actions' => SqlExpression::increment('number_of_actions')], 'id', $id);
	}
}