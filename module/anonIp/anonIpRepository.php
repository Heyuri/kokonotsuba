<?php

namespace Kokonotsuba\Modules\anonIp;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/**
 * Repository for IP anonymization operations on the posts and action log tables.
 *
 * A row is considered already anonymized when its IP column is exactly
 * 16 lowercase hex characters (the 16-char SHA-512 truncation this module applies).
 */
class anonIpRepository extends baseRepository {

	public function __construct(
		databaseConnection $databaseConnection,
		string $postTable,
		private readonly string $actionLogTable,
		private readonly string $soudaneTable,
	) {
		parent::__construct($databaseConnection, $postTable);
		self::validateTableNames($actionLogTable, $soudaneTable);
	}

	/**
	 * Count posts whose `host` has not yet been anonymized and whose `root`
	 * timestamp is older than the given cutoff.
	 *
	 * @param string $cutoff  MySQL-formatted datetime string (Y-m-d H:i:s).
	 * @return int
	 */
	public function countToAnonymize(string $cutoff): int {
		$sql = "SELECT COUNT(*)
		        FROM {$this->table}
		        WHERE root < :cutoff
		        AND host NOT REGEXP '^[0-9a-f]{16}$'";

		return (int) $this->queryColumn($sql, [':cutoff' => $cutoff]);
	}

	/**
	 * Replace `host` with the first 16 hex characters of SHA-512(host) for
	 * all posts older than the given cutoff that have not already been anonymized.
	 *
	 * @param string $cutoff  MySQL-formatted datetime string (Y-m-d H:i:s).
	 * @return void
	 */
	public function anonymizeBefore(string $cutoff): void {
		$sql = "UPDATE {$this->table}
		        SET host = LEFT(SHA2(host, 512), 16)
		        WHERE root < :cutoff
		        AND host NOT REGEXP '^[0-9a-f]{16}$'";

		$this->query($sql, [':cutoff' => $cutoff]);
	}

	/**
	 * Count action log entries whose `ip_address` has not yet been anonymized
	 * and whose `time_added` timestamp is older than the given cutoff.
	 *
	 * @param string $cutoff  MySQL-formatted datetime string (Y-m-d H:i:s).
	 * @return int
	 */
	public function countActionLogToAnonymize(string $cutoff): int {
		$sql = "SELECT COUNT(*)
		        FROM {$this->actionLogTable}
		        WHERE time_added < :cutoff
		        AND ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		return (int) $this->queryColumn($sql, [':cutoff' => $cutoff]);
	}

	/**
	 * Replace `ip_address` with the first 16 hex characters of SHA-512(ip_address)
	 * for all action log entries older than the given cutoff that have not already
	 * been anonymized.
	 *
	 * @param string $cutoff  MySQL-formatted datetime string (Y-m-d H:i:s).
	 * @return void
	 */
	public function anonymizeActionLogBefore(string $cutoff): void {
		$sql = "UPDATE {$this->actionLogTable}
		        SET ip_address = LEFT(SHA2(ip_address, 512), 16)
		        WHERE time_added < :cutoff
		        AND ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		$this->query($sql, [':cutoff' => $cutoff]);
	}

	/**
	 * Count all posts whose `host` has not yet been anonymized, regardless of age.
	 *
	 * @return int
	 */
	public function countAllToAnonymize(): int {
		$sql = "SELECT COUNT(*)
		        FROM {$this->table}
		        WHERE host NOT REGEXP '^[0-9a-f]{16}$'";

		return (int) $this->queryColumn($sql);
	}

	/**
	 * Replace `host` with the first 16 hex characters of SHA-512(host) for
	 * every post that has not already been anonymized.
	 *
	 * @return void
	 */
	public function anonymizeAll(): void {
		$sql = "UPDATE {$this->table}
		        SET host = LEFT(SHA2(host, 512), 16)
		        WHERE host NOT REGEXP '^[0-9a-f]{16}$'";

		$this->query($sql);
	}

	/**
	 * Count all action log entries whose `ip_address` has not yet been anonymized,
	 * regardless of age.
	 *
	 * @return int
	 */
	public function countAllActionLogToAnonymize(): int {
		$sql = "SELECT COUNT(*)
		        FROM {$this->actionLogTable}
		        WHERE ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		return (int) $this->queryColumn($sql);
	}

	/**
	 * Replace `ip_address` with the first 16 hex characters of SHA-512(ip_address)
	 * for every action log entry that has not already been anonymized.
	 *
	 * @return void
	 */
	public function anonymizeAllActionLog(): void {
		$sql = "UPDATE {$this->actionLogTable}
		        SET ip_address = LEFT(SHA2(ip_address, 512), 16)
		        WHERE ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		$this->query($sql);
	}

	// -------------------------------------------------------------------------
	// Soudane votes table
	// -------------------------------------------------------------------------

	/**
	 * Count soudane vote rows whose `ip_address` has not yet been anonymized
	 * and whose `date_added` timestamp is older than the given cutoff.
	 *
	 * @param string $cutoff  MySQL-formatted datetime string (Y-m-d H:i:s).
	 * @return int
	 */
	public function countSoudaneToAnonymize(string $cutoff): int {
		$sql = "SELECT COUNT(*)
		        FROM {$this->soudaneTable}
		        WHERE date_added < :cutoff
		        AND ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		return (int) $this->queryColumn($sql, [':cutoff' => $cutoff]);
	}

	/**
	 * Replace `ip_address` with the first 16 hex characters of SHA-512(ip_address)
	 * for all soudane vote rows older than the given cutoff that have not already
	 * been anonymized.
	 *
	 * @param string $cutoff  MySQL-formatted datetime string (Y-m-d H:i:s).
	 * @return void
	 */
	public function anonymizeSoudaneBefore(string $cutoff): void {
		$sql = "UPDATE {$this->soudaneTable}
		        SET ip_address = LEFT(SHA2(ip_address, 512), 16)
		        WHERE date_added < :cutoff
		        AND ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		$this->query($sql, [':cutoff' => $cutoff]);
	}

	/**
	 * Count all soudane vote rows whose `ip_address` has not yet been anonymized,
	 * regardless of age.
	 *
	 * @return int
	 */
	public function countAllSoudaneToAnonymize(): int {
		$sql = "SELECT COUNT(*)
		        FROM {$this->soudaneTable}
		        WHERE ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		return (int) $this->queryColumn($sql);
	}

	/**
	 * Replace `ip_address` with the first 16 hex characters of SHA-512(ip_address)
	 * for every soudane vote row that has not already been anonymized.
	 *
	 * @return void
	 */
	public function anonymizeAllSoudane(): void {
		$sql = "UPDATE {$this->soudaneTable}
		        SET ip_address = LEFT(SHA2(ip_address, 512), 16)
		        WHERE ip_address NOT REGEXP '^[0-9a-f]{16}$'";

		$this->query($sql);
	}
}
