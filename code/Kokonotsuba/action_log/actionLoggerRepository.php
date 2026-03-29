<?php

namespace Kokonotsuba\action_log;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\bindActionLogFiltersParameters;

/** Repository for the staff action log table. */
class actionLoggerRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $actionLogTable,
		private readonly string $boardTable
	) {
		parent::__construct($databaseConnection, $actionLogTable);
	}

	/**
	 * Count the total number of log entries, optionally filtered.
	 *
	 * @param array $filters Optional filter criteria.
	 * @return int Number of matching entries.
	 */
	public function getAmountOfLogEntries($filters = []): int {
		$query = "SELECT COUNT(*) FROM {$this->table} WHERE 1";
		$params = [];
		bindActionLogFiltersParameters($params, $query, $filters);
		$count = $this->queryColumn($query, $params);
		return $count ?? 0;
	}
	
	/**
	 * Fetch the entire action log ordered by time ascending.
	 *
	 * @return loggedActionEntry[] All log entries as hydrated objects.
	 */
	public function getFullActionLog(): array {
		return $this->findAll('time_added', 'ASC', '\Kokonotsuba\action_log\loggedActionEntry');
	}
	
	/**
	 * Fetch a paginated, optionally filtered slice of the action log.
	 *
	 * @param int    $amount  Maximum number of entries to return.
	 * @param int    $offset  Row offset for pagination.
	 * @param array  $filters Optional filter criteria.
	 * @param string $order   Column to order by (validated against an allowlist).
	 * @return loggedActionEntry[] Array of hydrated log entry objects.
	 */
	public function fetchLogEntries(int $amount, int $offset, array $filters, string $order): array {
		if ($amount == 0) return [];

		// Whitelist allowed ORDER BY fields
		$allowedOrderFields = ['time_added', 'user_id', 'action_type', 'name', 'role', 'board_uid'];
		if (!in_array($order, $allowedOrderFields, true)) {
			$order = 'time_added';
		}

		$query = "SELECT * FROM {$this->table} WHERE 1";
		$params = [];

		bindActionLogFiltersParameters($params, $query, $filters);

		$query .= " ORDER BY {$order} DESC";
		$query .= " LIMIT :_limit OFFSET :_offset";
		$params[':_limit'] = $amount;
		$params[':_offset'] = $offset;

		return $this->queryAllAsClass($query, $params, '\Kokonotsuba\action_log\loggedActionEntry') ?? [];
	}

	/**
	 * Insert a new action log entry.
	 *
	 * @param string $name      Username of the acting staff member.
	 * @param string $role      Role level string of the acting member.
	 * @param string $action    Human-readable description of the action performed.
	 * @param string $ipAddress IP address of the acting staff member.
	 * @param int    $board_uid Board UID the action was performed on.
	 * @return void
	 */
	public function insertLogEntry(string $name, string $role, string $action, string $ipAddress, int $board_uid): void {
		$query = "INSERT INTO {$this->table} (name, role, log_action, ip_address, board_uid, board_title)
				  VALUES (:name, :role, :log_action, :ip_address, :board_uid,
				  (SELECT board_title FROM {$this->boardTable} WHERE board_uid = :board_uid LIMIT 1))";

		$params = [
			':name' => $name,
			':role' => $role,
			':log_action' => $action,
			':ip_address' => $ipAddress,
			':board_uid' => $board_uid,
		];

		$this->query($query, $params);
	}
}