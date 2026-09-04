<?php

namespace Kokonotsuba\account;

use Kokonotsuba\action_log\actionType;
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
	 * Demote an account to the next role down the assignable ladder.
	 * No-ops if the account is already at the lowest assignable role.
	 *
	 * @param int $id Account primary key.
	 * @return void
	 */
	public function handleAccountDemote(int $id) {
		$account = $this->accountRepository->getAccountByID($id);
		if(!$account) return;

		$demotedRole = $account->getRoleLevel()->demoted();
		if($demotedRole === null) return;

		$this->accountRepository->updateAccountRoleByID($id, $demotedRole->value);
	}

	/**
	 * Promote an account to the next role up the assignable ladder.
	 * No-ops if the account is already at the highest assignable role.
	 *
	 * @param int $id Account primary key.
	 * @return void
	 */
	public function handleAccountPromote(int $id) {
		$account = $this->accountRepository->getAccountByID($id);
		if(!$account) return;

		$promotedRole = $account->getRoleLevel()->promoted();
		if($promotedRole === null) return;

		$this->accountRepository->updateAccountRoleByID($id, $promotedRole->value);
	}

	/**
	 * Create a new staff account.
	 *
	 * @param bool   $isHashed Whether $password is already bcrypt-hashed.
	 * @param string $password Plain-text password (or hash if $isHashed is true).
	 * @param string $username New account username.
	 * @param int    $role     Initial role level integer.
	 * @throws \InvalidArgumentException If the role is not one an account can be given.
	 * @return void
	 */
	public function handleAccountCreation(bool $isHashed, string $password, string $username, int $role) {
		// role comes straight off the form - only accept a real, assignable role level
		$roleLevel = userRole::fromStored($role);
		if(!$roleLevel->isAssignable()) {
			throw new \InvalidArgumentException("Invalid role level for a new account: $role");
		}

		// don't hash the password if its being passed as hashed from the request
		if(!$isHashed) {
			$passwordHash = password_hash($password, PASSWORD_DEFAULT);
		} else {
			$passwordHash = $password;
		}

		$this->accountRepository->addNewAccount($username, $roleLevel->value, $passwordHash);
		$this->actionLoggerService->logAction("Registered a new account ($username)", GLOBAL_BOARD_UID, actionType::ACCOUNT_CREATE);
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

		$this->actionLoggerService->logAction("Reset password", GLOBAL_BOARD_UID, actionType::ACCOUNT_PASSWORD);
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
		$this->actionLoggerService->logAction("Admin reset password for account #$accountId", GLOBAL_BOARD_UID, actionType::ACCOUNT_PASSWORD);
	}
	
}