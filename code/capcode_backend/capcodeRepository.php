<?php

class capcodeRepository {
	// Whitelisted columns
	private array $allowedColumns = ['tripcode', 'is_secure', 'date_added', 'added_by', 'color_hex', 'cap_text']; 

	public function __construct(
        private DatabaseConnection $databaseConnection,
		private string $capcodeTable,
		private string $accountTable
    ) {}

	private function getBaseSelectQuery(): string {
		// build the query
		return "SELECT cap.*, account.username AS added_by_username
			FROM {$this->capcodeTable} cap
			LEFT JOIN {$this->accountTable} account ON cap.added_by = account.id";
	}

	public function getById(int $id): ?array {
		// Build query using shared base SELECT
		$query = $this->getBaseSelectQuery() . " WHERE cap.id = :id ORDER BY cap.id ASC";

		// Fetch exactly one row
		$result = $this->databaseConnection->fetchOne($query, [':id' => (int)$id]);

		// Return the row or null if not found
		return $result ?: null;
	}

	public function getAll(): array {
		// Build query using shared base SELECT
		$query = $this->getBaseSelectQuery() . " ORDER BY cap.id ASC";

		// Fetch all rows
		return $this->databaseConnection->fetchAllAsArray($query);
	}

	public function create(
		string $tripcode, 
		int $isSecure, 
		int $addedBy, 
		string $colorHex, 
		string $capText
	): int {
		// Insert a new capcode record
		// All parameters are bound to prevent SQL injection
		$query = "
			INSERT INTO capcodes (tripcode, is_secure, added_by, color_hex, cap_text)
			VALUES (:tripcode, :is_secure, :added_by, :color_hex, :cap_text)
		";

		// Collect parameters in an associative array matching named placeholders
		$params = [
			'tripcode' => (string)$tripcode,
			'is_secure' => (int)$isSecure,
			'added_by' => (int)$addedBy,
			'color_hex' => (string)$colorHex,
			'cap_text' => (string)$capText
		];

		// Execute the insert query
		$this->databaseConnection->execute($query, $params);

		// Return the last inserted ID as an integer
		return (int)$this->databaseConnection->lastInsertId();
	}

	public function update(int $id, array $data): void {
		// Filter $data to include only whitelisted columns that are present
		$filteredData = array_intersect_key($data, array_flip($this->allowedColumns));

		if (empty($filteredData)) {
			// Nothing to update
			return;
		}

		// Build dynamic SET clause
		$setClauses = [];
		foreach (array_keys($filteredData) as $column) {
			$setClauses[] = "$column = :$column";
		}
		$setClause = implode(', ', $setClauses);

		$query = "
			UPDATE {$this->capcodeTable}
			SET $setClause
			WHERE id = :id
		";

		// Parameter bindings for the update statement
		$params = [];
		foreach ($filteredData as $key => $value) {
			$params[":$key"] = $value;
		}
		$params[':id'] = $id;

		// Execute
		$this->databaseConnection->execute($query, $params);
	}

	public function delete(int $id): bool {
		// Delete a capcode record based on its ID
		$query = "DELETE FROM {$this->capcodeTable} WHERE id = :id";

		// Execute delete operation and return success/failure
		return $this->databaseConnection->execute($query, ['id' => $id]);
	}

	public function getNextAutoIncrement(): ?int {
		// Retrieve the next AUTO_INCREMENT value for the capcodes table
		// This uses the helper method defined in DatabaseConnection
		return $this->databaseConnection->getNextAutoIncrement('capcodes');
	}
}
