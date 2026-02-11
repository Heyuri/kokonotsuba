<?php



use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

// ───────────────────────────────────────
// Database Setup
// ───────────────────────────────────────
$dbSettings = getDatabaseSettings();

databaseConnection::createInstance($dbSettings);

$databaseConnection = databaseConnection::getInstance();

$transactionManager = new transactionManager($databaseConnection);
