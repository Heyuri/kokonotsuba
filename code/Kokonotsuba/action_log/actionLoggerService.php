<?php

namespace Kokonotsuba\action_log;

use Kokonotsuba\account\accountRepository;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

/** Service for logging and retrieving staff administrative actions. */
class actionLoggerService {
	public function __construct(
		private readonly actionLoggerRepository $actionLoggerRepository,
		private readonly accountRepository $accountRepository,
		private readonly request $request,
		private readonly transactionManager $transactionManager
	) {}

	/**
	 * Fetch a paginated, optionally filtered slice of the action log.
	 *
	 * @param int    $amount  Maximum number of entries to return.
	 * @param int    $offset  Pagination offset.
	 * @param array  $filters Optional filter criteria.
	 * @param string $order   Column to order by (validated against an allowlist).
	 * @return loggedActionEntry[] Array of hydrated log entry objects.
	 */
	public function getSpecifiedLogEntries(int $amount = 0, int $offset = 0, array $filters = [], string $order = 'time_added'): array {
		$allowedOrderFields = ['time_added', 'user_id', 'action_type'];
		if (!in_array($order, $allowedOrderFields, true)) {
			$order = 'time_added';
		}

		$offset = max($offset, 0);

		return $this->actionLoggerRepository->fetchLogEntries($amount, $offset, $filters, $order);
	}

	/**
	 * Record an action performed by the currently logged-in staff member.
	 * Also increments the member's action counter when they are staff-level.
	 *
	 * @param string $actionString Human-readable description of the action.
	 * @param int    $board_uid    Board UID the action was performed on.
	 * @param bool $isAnon Flag whether to log the role + username of the user
	 * @return void
	 */
	public function logAction(string $actionString, int $board_uid, bool $isAnon = false): void {
		// grab staff session
		$staffSession = new staffAccountFromSession;
		
		// get the ip from request for logging
		$IPAddress = $this->request->userIp();

		// extract the name and role enum
		$name = $staffSession->getUsername();
		$roleEnum = $staffSession->getRoleLevel();

		// anonymize values, mostly useful for keeping staff anonymous for actions that are too identifiable
		if($isAnon) {
			// set the name to the default
			$name = "Nameless";

			// set the role to none
			$roleEnum = userRole::LEV_NONE;
		}

		// get the role value
		$role = $roleEnum->value;

		// write function for transaction
		$write = function () use ($staffSession, $roleEnum, $name, $role, $actionString, $IPAddress, $board_uid, $isAnon): void {
			if ($roleEnum->isStaff() && !$isAnon) {
				$this->accountRepository->incrementAccountActionRecordByID($staffSession->getUID());
			}

			$this->actionLoggerRepository->insertLogEntry($name, $role, $actionString, (string)$IPAddress, $board_uid);
		};

		// Left to themselves these are two autocommit writes, so two commits, and with
		// innodb_flush_log_at_trx_commit = 1 each commit is its own redo-log flush. On a board
		// rebuild - which logs one line and then does all its work - that pair has been profiled at
		// over 200ms, more than every SELECT in the rebuild put together. Batched, it costs one
		// flush instead of two.
		//
		// When the caller already has a transaction open (registRoute logs the post it is in the
		// middle of committing) the writes just join it. Committing here would end the caller's
		// transaction early, which is exactly the bug this branch avoids.
		if ($this->transactionManager->inTransaction()) {
			$write();
			return;
		}

		$this->transactionManager->run($write);
	}

	/**
	 * Count the total number of action log entries, optionally filtered.
	 *
	 * @param array $filters Optional filter criteria.
	 * @return int|null Entry count, or null if unavailable.
	 */
	public function getAmountOfLogEntries(array $filters): ?int {
		return $this->actionLoggerRepository->getAmountOfLogEntries($filters);
	}
}