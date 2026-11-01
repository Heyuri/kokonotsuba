<?php

namespace Kokonotsuba\kernel;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

/** What kernel::connectDatabase() hands back: the connection and what it was built from. */
final class databaseHandles {
	public function __construct(
		public readonly databaseConnection $databaseConnection,
		public readonly transactionManager $transactionManager,
		public readonly array $dbSettings,
		public readonly array $tableNames,
	) {}
}
