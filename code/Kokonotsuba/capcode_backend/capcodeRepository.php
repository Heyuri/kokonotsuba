<?php

namespace Kokonotsuba\capcode_backend;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for capcode (trip-based role badge) records. */
class capcodeRepository extends baseRepository {
	// Whitelisted columns
	private array $allowedColumns = ['tripcode', 'is_secure', 'date_added', 'added_by', 'color_hex', 'cap_text']; 

	public function __construct(
        databaseConnection $databaseConnection,
		string $capcodeTable,
		private string $accountTable
    ) {
		parent::__construct($databaseConnection, $capcodeTable);
		self::validateTableNames($accountTable);
	}

	private function getBaseSelectQuery(): string {
		return "SELECT cap.*, account.username AS added_by_username
			FROM {$this->table} cap
			LEFT JOIN {$this->accountTable} account ON cap.added_by = account.id";
	}

	/**
	 * Fetch a single capcode record by its primary key, including the adder's username.
	 *
	 * @param int $id Capcode primary key.
	 * @return array|null Associative row, or null if not found.
	 */
	public function getById(int $id): ?array {
		$query = $this->getBaseSelectQuery() . " WHERE cap.id = :id ORDER BY cap.id ASC";
		$result = $this->queryOne($query, [':id' => (int)$id]);
		return $result ?: null;
	}

	/**
	 * Fetch all capcode records ordered by ID ascending, including the adder's username.
	 *
	 * @return array Array of associative rows.
	 */
	public function getAll(): array {
		$query = $this->getBaseSelectQuery() . " ORDER BY cap.id ASC";
		return $this->queryAll($query);
	}

	/**
	 * Insert a new capcode record and return its new primary key.
	 *
	 * @param string $tripcode   Tripcode string this capcode applies to.
	 * @param int    $isSecure   1 if the tripcode is a secure tripcode, 0 otherwise.
	 * @param int    $addedBy    Account ID of the staff member creating the record.
	 * @param string $colorHex  Display colour in hex format (e.g. '#ff0000').
	 * @param string $capText    Badge text shown next to the post name.
	 * @return int Newly inserted primary key.
	 */
	public function create(
		string $tripcode, 
		int $isSecure, 
		int $addedBy, 
		string $colorHex, 
		string $capText
	): int {
		$this->insert([
			'tripcode' => (string)$tripcode,
			'is_secure' => (int)$isSecure,
			'added_by' => (int)$addedBy,
			'color_hex' => (string)$colorHex,
			'cap_text' => (string)$capText,
		]);

		return (int)$this->lastInsertId();
	}

	/**
	 * Update whitelisted columns on an existing capcode record.
	 *
	 * @param int   $id   Capcode primary key.
	 * @param array $data Map of allowed column names to new values.
	 * @return void
	 */
	public function update(int $id, array $data): void {
		// Filter $data to include only whitelisted columns that are present
		$filteredData = array_intersect_key($data, array_flip($this->allowedColumns));

		if (empty($filteredData)) {
			return;
		}

		$this->updateWhere($filteredData, 'id', $id);
	}

	/**
	 * Delete a capcode record by its primary key.
	 *
	 * @param int $id Capcode primary key.
	 * @return bool Always true.
	 */
	public function delete(int $id): bool {
		$this->deleteWhere('id', $id);
		return true;
	}

	/**
	 * Return the next AUTO_INCREMENT value for the capcode table.
	 *
	 * @return int|null Next auto-increment value, or null if unavailable.
	 */
	public function getNextAutoIncrement(): ?int {
		return parent::getNextAutoIncrement();
	}
}
