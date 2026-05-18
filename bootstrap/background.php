<?php

$appRoot = $backgroundAppRoot ?? __DIR__ . '/../';

// Always derive the autoloader path from this file's real location so the
// PSR-4 base dir is correct even when $appRoot comes from a web-server path
// (e.g. SCRIPT_FILENAME under PHP-FPM) that differs from __DIR__.
require_once __DIR__ . '/../autoload.php';
require_once $appRoot . 'code/Kokonotsuba/constants.php';
require $appRoot . 'paths.php';
require $appRoot . 'bootstrap/libraryIncludes.php';
$dbSettings = require $appRoot . 'databaseSettings.php';
require $appRoot . 'bootstrap/database.php';

/** @var \Kokonotsuba\database\databaseConnection $databaseConnection */
/** @var \Kokonotsuba\database\transactionManager $transactionManager */
/** @var array $dbSettings */
