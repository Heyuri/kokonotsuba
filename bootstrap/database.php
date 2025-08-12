<?php
// ───────────────────────────────────────
// Database Setup
// ───────────────────────────────────────
$dbSettings = getDatabaseSettings();

DatabaseConnection::createInstance($dbSettings);

$databaseConnection = DatabaseConnection::getInstance();

$transactionManager = new transactionManager($databaseConnection);
