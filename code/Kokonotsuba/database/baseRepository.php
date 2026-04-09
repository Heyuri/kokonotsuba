<?php

namespace Kokonotsuba\database;

/** Base repository providing shared CRUD and query helpers for all concrete repositories. */
class baseRepository {
	use ValidatesIdentifiersTrait;

	/**
	 * @param databaseConnection $databaseConnection Active database connection.
	 * @param string $table Primary table name for this repository.
	 */
	public function __construct(
		protected databaseConnection $databaseConnection,
		protected readonly string $table
	) {
		self::validateTableName($table);
	}

	private function validateColumnName(string $name): void {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new \InvalidArgumentException("Invalid column name: {$name}");
		}
	}

	// ─── Simple CRUD helpers ───────────────────────────────────────

	/**
	 * Fetch a single row where the given column equals the given value.
	 *
	 * @param string $column Column name to match on.
	 * @param mixed  $value  Value to match.
	 * @param string $fetchClass Optional fully-qualified class name to hydrate the result into.
	 * @return mixed Associative array, hydrated object, or null if not found.
	 */
	protected function findBy(string $column, mixed $value, string $fetchClass = ''): mixed {
		$this->validateColumnName($column);
		$query = "SELECT * FROM {$this->table} WHERE {$column} = :value";
		$params = [':value' => $value];
		if ($fetchClass !== '') {
			return $this->databaseConnection->fetchAsClass($query, $params, $fetchClass) ?: null;
		}
		return $this->databaseConnection->fetchOne($query, $params) ?: null;
	}

	/**
	 * Fetch all rows where the given column equals the given value.
	 *
	 * @param string $column Column name to match on.
	 * @param mixed  $value  Value to match.
	 * @param string $fetchClass Optional class name to hydrate rows into.
	 * @return array Array of rows (associative arrays or hydrated objects).
	 */
	protected function findAllBy(string $column, mixed $value, string $fetchClass = ''): array {
		$this->validateColumnName($column);
		$query = "SELECT * FROM {$this->table} WHERE {$column} = :value";
		$params = [':value' => $value];
		if ($fetchClass !== '') {
			return $this->databaseConnection->fetchAllAsClass($query, $params, $fetchClass);
		}
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	/**
	 * Fetch all rows from the primary table, optionally ordered.
	 *
	 * @param string $orderBy   Column to order by, or empty for no ordering.
	 * @param string $direction Sort direction: 'ASC' or 'DESC'.
	 * @param string $fetchClass Optional class name to hydrate rows into.
	 * @return array Array of rows.
	 */
	protected function findAll(string $orderBy = '', string $direction = 'ASC', string $fetchClass = ''): array {
		$query = "SELECT * FROM {$this->table}";
		if ($orderBy !== '') {
			$this->validateColumnName($orderBy);
			$direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
			$query .= " ORDER BY {$orderBy} {$direction}";
		}
		if ($fetchClass !== '') {
			return $this->databaseConnection->fetchAllAsClass($query, [], $fetchClass);
		}
		return $this->databaseConnection->fetchAllAsArray($query);
	}

	/**
	 * Insert a single row into the primary table.
	 * Values may be scalars (bound as parameters) or SqlExpression instances (embedded as raw SQL).
	 *
	 * @param array $data Associative array of column => value|SqlExpression pairs to insert.
	 * @return void
	 */
	protected function insert(array $data): void {
		foreach (array_keys($data) as $key) {
			$this->validateColumnName($key);
		}
		$columns = implode(', ', array_keys($data));
		$valueParts = [];
		$params = [];
		foreach ($data as $key => $value) {
			if ($value instanceof SqlExpression) {
				$valueParts[] = $value->expression;
			} else {
				$valueParts[] = ":{$key}";
				$params[":{$key}"] = $value;
			}
		}
		$query = "INSERT INTO {$this->table} ({$columns}) VALUES (" . implode(', ', $valueParts) . ")";
		$this->databaseConnection->execute($query, $params);
	}

	/**
	 * Update columns in rows where the given column equals the given value.
	 * Values may be scalars (bound as parameters) or SqlExpression instances (embedded as raw SQL).
	 *
	 * @param array  $data   Associative array of column => value|SqlExpression pairs.
	 * @param string $column Column name used in the WHERE clause.
	 * @param mixed  $value  Value to match in the WHERE clause.
	 * @return void
	 */
	protected function updateWhere(array $data, string $column, mixed $value): void {
		if (empty($data)) return;
		$this->validateColumnName($column);
		$setClauses = [];
		$params = [];
		foreach ($data as $key => $val) {
			$this->validateColumnName($key);
			if ($val instanceof SqlExpression) {
				$setClauses[] = "{$key} = {$val->expression}";
			} else {
				$setClauses[] = "{$key} = :set_{$key}";
				$params[":set_{$key}"] = $val;
			}
		}
		$params[':where_value'] = $value;
		$query = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE {$column} = :where_value";
		$this->databaseConnection->execute($query, $params);
	}

	/**
	 * Delete all rows where the given column equals the given value.
	 *
	 * @param string $column Column name used in the WHERE clause.
	 * @param mixed  $value  Value to match.
	 * @return void
	 */
	protected function deleteWhere(string $column, mixed $value): void {
		$this->validateColumnName($column);
		$query = "DELETE FROM {$this->table} WHERE {$column} = :value";
		$this->databaseConnection->execute($query, [':value' => $value]);
	}

	/**
	 * Count rows in the primary table, optionally with a custom WHERE clause.
	 *
	 * @param string $where  Raw SQL WHERE expression (without the "WHERE" keyword), or empty for all rows.
	 * @param array  $params Bound parameters for the WHERE clause.
	 * @return int Number of matching rows.
	 */
	protected function count(string $where = '', array $params = []): int {
		$query = "SELECT COUNT(*) FROM {$this->table}";
		if ($where !== '') {
			$query .= " WHERE {$where}";
		}
		return (int) ($this->databaseConnection->fetchColumn($query, $params) ?? 0);
	}

	/**
	 * Check whether at least one row exists where the given column equals the given value.
	 *
	 * @param string $column Column name to check.
	 * @param mixed  $value  Value to match.
	 * @return bool True if a matching row exists, false otherwise.
	 */
	protected function exists(string $column, mixed $value): bool {
		$this->validateColumnName($column);
		$query = "SELECT 1 FROM {$this->table} WHERE {$column} = :value LIMIT 1";
		return (bool) $this->databaseConnection->fetchColumn($query, [':value' => $value]);
	}

	/**
	 * Return the auto-incremented ID generated by the most recent INSERT.
	 *
	 * @return mixed Last insert ID, or null if no insert has been performed.
	 */
	protected function lastInsertId(): mixed {
		return $this->databaseConnection->lastInsertId();
	}

	/**
	 * Return the next AUTO_INCREMENT value for the primary table without consuming it.
	 *
	 * @return int|null Next auto-increment value, or null if unavailable.
	 */
	protected function getNextAutoIncrement(): ?int {
		return $this->databaseConnection->getNextAutoIncrement($this->table);
	}

	// ─── Column-level helpers ──────────────────────────────────────

	/**
	 * Fetch a single column value from the first row matching a WHERE condition.
	 *
	 * @param string $selectColumn Column to return.
	 * @param string $whereColumn  Column to filter on.
	 * @param mixed  $value        Value to match.
	 * @return mixed Scalar value, or false if not found.
	 */
	protected function pluck(string $selectColumn, string $whereColumn, mixed $value): mixed {
		$this->validateColumnName($selectColumn);
		$this->validateColumnName($whereColumn);
		$query = "SELECT {$selectColumn} FROM {$this->table} WHERE {$whereColumn} = :value LIMIT 1";
		return $this->databaseConnection->fetchColumn($query, [':value' => $value]);
	}

	/**
	 * Fetch a flat array of a single column's values for all rows matching a WHERE condition.
	 *
	 * @param string $selectColumn Column to return.
	 * @param string $whereColumn  Column to filter on.
	 * @param mixed  $value        Value to match.
	 * @return array Flat array of column values.
	 */
	protected function pluckAll(string $selectColumn, string $whereColumn, mixed $value): array {
		$this->validateColumnName($selectColumn);
		$this->validateColumnName($whereColumn);
		$query = "SELECT {$selectColumn} FROM {$this->table} WHERE {$whereColumn} = :value";
		$rows = $this->databaseConnection->fetchAllAsIndexArray($query, [':value' => $value]);
		return $rows ? array_merge(...$rows) : [];
	}

	/**
	 * Fetch a flat array of a column's values where another column is IN a list of values.
	 *
	 * @param string $selectColumn Column to return.
	 * @param string $whereColumn  Column to filter on.
	 * @param array  $values       Values for the IN clause.
	 * @param bool   $distinct     Whether to return only unique values.
	 * @return array Flat array of column values.
	 */
	protected function pluckWhereIn(string $selectColumn, string $whereColumn, array $values, bool $distinct = false): array {
		if (empty($values)) return [];
		$this->validateColumnName($selectColumn);
		$this->validateColumnName($whereColumn);
		$select = $distinct ? "SELECT DISTINCT {$selectColumn}" : "SELECT {$selectColumn}";
		$in = $this->buildInClause($values);
		$query = "{$select} FROM {$this->table} WHERE {$whereColumn} IN {$in}";
		$rows = $this->databaseConnection->fetchAllAsIndexArray($query, array_values($values));
		return $rows ? array_merge(...$rows) : [];
	}

	/**
	 * Fetch all rows where the given column is IN a list of values.
	 *
	 * @param string $whereColumn Column to filter on.
	 * @param array  $values      Values for the IN clause.
	 * @param string $fetchClass  Optional class name to hydrate rows into.
	 * @return array Array of rows.
	 */
	protected function findAllWhereIn(string $whereColumn, array $values, string $fetchClass = ''): array {
		if (empty($values)) return [];
		$this->validateColumnName($whereColumn);
		$in = $this->buildInClause($values);
		$query = "SELECT * FROM {$this->table} WHERE {$whereColumn} IN {$in}";
		$params = array_values($values);
		if ($fetchClass !== '') {
			return $this->databaseConnection->fetchAllAsClass($query, $params, $fetchClass);
		}
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	// ─── Bulk mutation helpers ─────────────────────────────────────

	/**
	 * Delete all rows where the given column is IN a list of values.
	 *
	 * @param string $column Column to filter on.
	 * @param array  $values Values for the IN clause.
	 * @return void
	 */
	protected function deleteWhereIn(string $column, array $values): void {
		if (empty($values)) return;
		$this->validateColumnName($column);
		$in = $this->buildInClause($values);
		$query = "DELETE FROM {$this->table} WHERE {$column} IN {$in}";
		$this->databaseConnection->execute($query, array_values($values));
	}

	/**
	 * Update columns in rows where the given column is IN a list of values.
	 * Values may be scalars (bound as parameters) or SqlExpression instances (embedded as raw SQL).
	 *
	 * @param array  $data   Associative array of column => value|SqlExpression pairs.
	 * @param string $column Column name used in the WHERE IN clause.
	 * @param array  $values Values for the IN clause.
	 * @return void
	 */
	protected function updateWhereIn(array $data, string $column, array $values): void {
		if (empty($data) || empty($values)) return;
		$this->validateColumnName($column);
		$setClauses = [];
		$params = [];
		foreach ($data as $key => $val) {
			$this->validateColumnName($key);
			if ($val instanceof SqlExpression) {
				$setClauses[] = "{$key} = {$val->expression}";
			} else {
				$setClauses[] = "{$key} = :set_{$key}";
				$params[":set_{$key}"] = $val;
			}
		}
		$inPlaceholders = [];
		foreach (array_values($values) as $i => $v) {
			$key = ":in_{$i}";
			$inPlaceholders[] = $key;
			$params[$key] = $v;
		}
		$in = '(' . implode(', ', $inPlaceholders) . ')';
		$query = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE {$column} IN {$in}";
		$this->databaseConnection->execute($query, $params);
	}

	/**
	 * Count rows where the given column equals the given value.
	 *
	 * @param string $column Column to filter on.
	 * @param mixed  $value  Value to match.
	 * @return int Number of matching rows.
	 */
	protected function countBy(string $column, mixed $value): int {
		$this->validateColumnName($column);
		$query = "SELECT COUNT(*) FROM {$this->table} WHERE {$column} = :value";
		return (int) ($this->databaseConnection->fetchColumn($query, [':value' => $value]) ?? 0);
	}

	/**
	 * Build a positional placeholder IN clause for the given values.
	 *
	 * @param array $values Values to create placeholders for.
	 * @return string Parenthesised placeholder string, e.g. "(?, ?, ?)".
	 */
	private function buildInClause(array $values): string {
		if (empty($values)) return '(NULL)';
		return '(' . implode(', ', array_fill(0, count($values), '?')) . ')';
	}

	// ─── Query-building helpers ────────────────────────────────────

	/**
	 * Append a LIMIT/OFFSET clause to a query using named parameters.
	 *
	 * @param string   $query  SQL query string (modified by reference).
	 * @param array    $params Bound parameters array (modified by reference).
	 * @param int      $limit  Maximum number of rows to return.
	 * @param int      $offset Row offset (default 0).
	 * @return void
	 */
	protected function paginate(string &$query, array &$params, int $limit, int $offset = 0): void {
		$query .= " LIMIT :_limit OFFSET :_offset";
		$params[':_limit'] = $limit;
		$params[':_offset'] = $offset;
	}

	// ─── Manual query methods for advanced operations ──────────────

	/**
	 * Execute a raw SQL statement (INSERT / UPDATE / DELETE).
	 *
	 * @param string $sql    Raw SQL string with named or positional placeholders.
	 * @param array  $params Bound parameters.
	 * @return bool True on success.
	 */
	protected function query(string $sql, array $params = []): bool {
		return $this->databaseConnection->execute($sql, $params);
	}

	/**
	 * Fetch a single row as an associative array.
	 *
	 * @param string $sql    SELECT query with optional placeholders.
	 * @param array  $params Bound parameters.
	 * @return array|false Associative row array, or false if not found.
	 */
	protected function queryOne(string $sql, array $params = []): array|false {
		return $this->databaseConnection->fetchOne($sql, $params);
	}

	/**
	 * Fetch all rows as an array of associative arrays.
	 *
	 * @param string $sql    SELECT query with optional placeholders.
	 * @param array  $params Bound parameters.
	 * @return array Array of associative row arrays (empty array if no results).
	 */
	protected function queryAll(string $sql, array $params = []): array {
		return $this->databaseConnection->fetchAllAsArray($sql, $params);
	}

	/**
	 * Fetch all rows and hydrate each into an instance of the given class.
	 *
	 * @param string $sql       SELECT query.
	 * @param array  $params    Bound parameters.
	 * @param string $className Fully-qualified class name to hydrate rows into.
	 * @return array Array of hydrated objects.
	 */
	protected function queryAllAsClass(string $sql, array $params, string $className): array {
		return $this->databaseConnection->fetchAllAsClass($sql, $params, $className);
	}

	/**
	 * Fetch a single row and hydrate it into an instance of the given class.
	 *
	 * @param string $sql       SELECT query.
	 * @param array  $params    Bound parameters.
	 * @param string $className Fully-qualified class name to hydrate the row into.
	 * @return mixed Hydrated object, or false/null if not found.
	 */
	protected function queryAsClass(string $sql, array $params, string $className): mixed {
		return $this->databaseConnection->fetchAsClass($sql, $params, $className);
	}

	/**
	 * Fetch the value of a single column from the first matching row.
	 *
	 * @param string $sql         SELECT query.
	 * @param array  $params      Bound parameters.
	 * @param int    $columnIndex Zero-based column index to return.
	 * @return mixed Column value, or null if not found.
	 */
	protected function queryColumn(string $sql, array $params = [], int $columnIndex = 0): mixed {
		return $this->databaseConnection->fetchColumn($sql, $params, $columnIndex);
	}

	/**
	 * Fetch a single scalar value from the result set.
	 *
	 * @param string $sql         SELECT query.
	 * @param array  $params      Bound parameters.
	 * @param int    $columnIndex Zero-based column index to return.
	 * @return mixed Scalar value, or null if not found.
	 */
	protected function queryValue(string $sql, array $params = [], int $columnIndex = 0): mixed {
		return $this->databaseConnection->fetchValue($sql, $params, $columnIndex);
	}

	/**
	 * Fetch all rows as a numerically indexed array of index arrays.
	 *
	 * @param string $sql    SELECT query.
	 * @param array  $params Bound parameters.
	 * @return array Numerically indexed array of row arrays.
	 */
	protected function queryAllAsIndexArray(string $sql, array $params = []): array {
		return $this->databaseConnection->fetchAllAsIndexArray($sql, $params);
	}
}
