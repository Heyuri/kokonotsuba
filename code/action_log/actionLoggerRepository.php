<?php

class actionLoggerRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $actionLogTable,
		private readonly string $boardTable
	) {}

	public function getAmountOfLogEntries($filters = []): int {
		$query = "SELECT COUNT(*) FROM {$this->actionLogTable} WHERE 1";
		$params = [];
		bindActionLogFiltersParameters($params, $query, $filters);
		$count = $this->databaseConnection->fetchColumn($query, $params);
		return $count ?? 0;
	}
	
	public function getFullActionLog(): array {
		$query = "SELECT * FROM {$this->actionLogTable} ORDER BY time_added";
		$logs = $this->databaseConnection->fetchAllAsClass($query, [], 'loggedActionEntry');
		
		return $logs ?? [];
	}
	
	public function fetchLogEntries(int $amount, int $offset, array $filters, string $order): array {
		if ($amount == 0) return [];

		$query = "SELECT * FROM {$this->actionLogTable} WHERE 1";
		$params = [];

		bindActionLogFiltersParameters($params, $query, $filters);

		$query .= " ORDER BY {$order} DESC";
		$query .= " LIMIT {$amount} OFFSET {$offset}";

		return $this->databaseConnection->fetchAllAsClass($query, $params, 'loggedActionEntry') ?? [];
	}

	public function insertLogEntry(string $name, string $role, string $action, string $ipAddress, int $board_uid): void {
		$query = "INSERT INTO {$this->actionLogTable} (name, role, log_action, ip_address, board_uid, board_title)
				  VALUES (:name, :role, :log_action, :ip_address, :board_uid,
				  (SELECT board_title FROM {$this->boardTable} WHERE board_uid = :board_uid LIMIT 1))";

		$params = [
			':name' => $name,
			':role' => $role,
			':log_action' => $action,
			':ip_address' => $ipAddress,
			':board_uid' => $board_uid,
		];

		$this->databaseConnection->execute($query, $params);
	}
}