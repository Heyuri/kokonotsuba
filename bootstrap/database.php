<?php



use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

// ───────────────────────────────────────
// Database Setup
// ───────────────────────────────────────
$dbSettings ??= getDatabaseSettings();

// Table names are fixed by tables.php, not by the credentials file.
$tableNames ??= getTableNames();

databaseConnection::createInstance($dbSettings);

$databaseConnection = databaseConnection::getInstance();

$transactionManager = new transactionManager($databaseConnection);
