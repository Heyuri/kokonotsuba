<?php

namespace Kokonotsuba\action_log;

use Kokonotsuba\account\accountRepository;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\request\request;

/** Service for logging and retrieving staff administrative actions. */
class actionLoggerService {
	public function __construct(
		private readonly actionLoggerRepository $actionLoggerRepository,
		private readonly accountRepository $accountRepository,
		private readonly request $request
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
	 * @return void
	 */
	public function logAction(string $actionString, int $board_uid): void {
		$staffSession = new staffAccountFromSession;
		$IPAddress = new IPAddress($this->request->getRemoteAddr());

		$name = $staffSession->getUsername();
		$roleEnum = $staffSession->getRoleLevel();
		$role = $roleEnum->value;

		if ($roleEnum->isStaff()) {
			$this->accountRepository->incrementAccountActionRecordByID($staffSession->getUID());
		}

		$this->actionLoggerRepository->insertLogEntry($name, $role, $actionString, (string)$IPAddress, $board_uid);
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