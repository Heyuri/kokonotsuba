<?php

namespace Kokonotsuba\account;

use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\log_in\loginSessionHandler;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/** Service for managing staff account lifecycle: creation, deletion, promotion, and password resets. */
class accountService {
	public function __construct(
        private readonly accountRepository $accountRepository, 
        private readonly actionLoggerService $actionLoggerService,
        private readonly request $request) {}
	
	/**
	 * Delete an account by its primary key.
	 *
	 * @param int $id Account primary key.
	 * @return void
	 */
	public function handleAccountDelete(int $id) {
		$this->accountRepository->deleteAccountByID($id);	
	}

	/**
	 * Demote an account by reducing its role level by 1.
	 * No-ops if the account is already at the lowest role level.
	 *
	 * @param int $id Account primary key.
	 * @return void
	 */
	public function handleAccountDemote(int $id) {
		$account = $this->accountRepository->getAccountByID($id);
		
		if($account->getRoleLevel()->value - 1 == userRole::LEV_NONE->value) return; # == is for PHP7 compatibility, change to === in future for PHP8
		
		$this->accountRepository->demoteAccountByID($id);
	}

	/**
	 * Promote an account by increasing its role level by 1.
	 * No-ops if the account is already at the highest role level.
	 *
	 * @param int $id Account primary key.
	 * @return void
	 */
	public function handleAccountPromote(int $id) {
		$account = $this->accountRepository->getAccountByID($id);
	
		if($account->getRoleLevel()->value + 1 == userRole::LEV_ADMIN->value + 1) return; # == is for PHP7 compatibility, change to === in future for PHP8
	
		$this->accountRepository->promoteAccountByID($id);
	}

	/**
	 * Create a new staff account.
	 *
	 * @param bool   $isHashed Whether $password is already bcrypt-hashed.
	 * @param string $password Plain-text password (or hash if $isHashed is true).
	 * @param string $username New account username.
	 * @param int    $role     Initial role level integer.
	 * @return void
	 */
	public function handleAccountCreation(bool $isHashed, string $password, string $username, int $role) {
		// don't hash the password if its being passed as hashed from the request
		if(!$isHashed) {
			$passwordHash = password_hash($password, PASSWORD_DEFAULT);
		} else {
			$passwordHash = $password;
		}

		$this->accountRepository->addNewAccount($username, $role, $passwordHash);
		$this->actionLoggerService->logAction("Registered a new account ($username)", GLOBAL_BOARD_UID);
	}

	/**
	 * Reset a staff member's own password and refresh the live session.
	 *
	 * @param staffAccountFromSession $staffAccountFromSession Session wrapper of the account resetting their password.
	 * @param string $newAccountPasswordForReset New plain-text password.
	 * @return void
	 */
	public function handleAccountPasswordReset(staffAccountFromSession $staffAccountFromSession, string $newAccountPasswordForReset) {
		$loginSessionHandler = new loginSessionHandler($this->request);
		$accountID = $staffAccountFromSession->getUID();
		
		// hash the password
		$passwordHash = password_hash($newAccountPasswordForReset, PASSWORD_DEFAULT);

		// update the account in database
		$this->accountRepository->updateAccountPasswordHashById($accountID, $passwordHash);

		//refresh session values
		$accountAfterPasswordUpdate = $this->accountRepository->getAccountById($accountID);
		$loginSessionHandler->login($accountAfterPasswordUpdate);

		$this->actionLoggerService->logAction("Reset password", GLOBAL_BOARD_UID);
	}

	/**
	 * Admin: reset another account's password by ID.
	 *
	 * @param int    $accountId  Target account primary key.
	 * @param string $newPassword New plain-text password.
	 * @return void
	 */
	public function handleAdminPasswordReset(int $accountId, string $newPassword): void {
		$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
		$this->accountRepository->updateAccountPasswordHashById($accountId, $passwordHash);
		$this->actionLoggerService->logAction("Admin reset password for account #$accountId", GLOBAL_BOARD_UID);
	}
	
}