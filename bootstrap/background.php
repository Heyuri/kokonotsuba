<?php

$appRoot = __DIR__ . '/../';

require $appRoot . 'autoload.php';
require_once $appRoot . 'code/Kokonotsuba/constants.php';
require $appRoot . 'paths.php';
require $appRoot . 'bootstrap/libraryIncludes.php';
require $appRoot . 'bootstrap/database.php';

/** @var \Kokonotsuba\database\databaseConnection $databaseConnection */
/** @var \Kokonotsuba\database\transactionManager $transactionManager */
/** @var array $dbSettings */
