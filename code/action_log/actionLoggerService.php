<?php

class actionLoggerService {
	public function __construct(
		private readonly actionLoggerRepository $actionLoggerRepository,
		private readonly accountRepository $accountRepository
	) {}

	public function getSpecifiedLogEntries(int $amount = 0, int $offset = 0, array $filters = [], string $order = 'time_added'): array {
		$allowedOrderFields = ['time_added', 'user_id', 'action_type'];
		if (!in_array($order, $allowedOrderFields, true)) {
			$order = 'time_added';
		}

		$offset = max($offset, 0);

		return $this->actionLoggerRepository->fetchLogEntries($amount, $offset, $filters, $order);
	}

	public function logAction(string $actionString, int $board_uid): void {
		$staffSession = new staffAccountFromSession;
		$IPAddress = new IPAddress;

		$name = $staffSession->getUsername();
		$roleEnum = $staffSession->getRoleLevel();
		$role = $roleEnum->value;

		if ($roleEnum->isStaff()) {
			$this->accountRepository->incrementAccountActionRecordByID($staffSession->getUID());
		}

		$this->actionLoggerRepository->insertLogEntry($name, $role, $actionString, (string)$IPAddress, $board_uid);
	}

	public function getAmountOfLogEntries(array $filters): ?int {
		return $this->actionLoggerRepository->getAmountOfLogEntries($filters);
	}
}