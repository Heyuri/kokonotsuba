<?php

namespace Kokonotsuba\migrations;

use InvalidArgumentException;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\ValidatesIdentifiersTrait;

/**
 * Executes migration SQL, expanding {LOGICAL_TABLE} placeholders against the canonical table
 * map. Writes are suppressed and logged in dry-run mode; reads always hit the database.
 */
class sqlRunner {
	use ValidatesIdentifiersTrait;

	/** @var list<string> Statements a dry run would have executed. */
	private array $plan = [];

	/**
	 * @param array<string, string> $tableNames Logical key => real table name.
	 * @param callable(string):void $logger Receives each statement as it is run or planned.
	 */
	public function __construct(
		private readonly databaseConnection $databaseConnection,
		private readonly array $tableNames,
		private readonly bool $dryRun,
		private $logger
	) {}

	public function isDryRun(): bool {
		return $this->dryRun;
	}

	/** @return list<string> */
	public function getPlan(): array {
		return $this->plan;
	}

	/** Resolve a logical table key to its real, validated name. */
	public function table(string $key): string {
		if (!isset($this->tableNames[$key])) {
			throw new InvalidArgumentException("Unknown table key: {$key}. Add it to tables.php.");
		}

		$name = $this->tableNames[$key];
		self::validateTableName($name);

		return $name;
	}

	/** Replace every {LOGICAL_TABLE} placeholder with its real name. */
	public function expand(string $query): string {
		return preg_replace_callback(
			'/\{([A-Z][A-Z0-9_]*)\}/',
			fn (array $m): string => $this->table($m[1]),
			$query
		);
	}

	/** Run a writing statement, or record it when dry-running. */
	public function run(string $query, array $params = []): void {
		$expanded = $this->expand($query);
		$this->plan[] = $expanded;
		($this->logger)($expanded);

		if (!$this->dryRun) {
			$this->databaseConnection->execute($expanded, $params);
		}
	}

	public function fetchAll(string $query, array $params = []): array {
		return $this->databaseConnection->fetchAllAsArray($this->expand($query), $params);
	}

	public function fetchValue(string $query, array $params = []) {
		return $this->databaseConnection->fetchValue($this->expand($query), $params);
	}

	public function getConnection(): databaseConnection {
		return $this->databaseConnection;
	}
}
