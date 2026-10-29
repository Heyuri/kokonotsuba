<?php

$appRoot = $backgroundAppRoot ?? __DIR__ . '/../';

// Always derive the autoloader path from this file's real location so the
// PSR-4 base dir is correct even when $appRoot comes from a web-server path
// (e.g. SCRIPT_FILENAME under PHP-FPM) that differs from __DIR__.
require_once __DIR__ . '/../autoload.php';
require_once $appRoot . 'code/Kokonotsuba/constants.php';
require_once $appRoot . 'paths.php';

\Kokonotsuba\kernel\kernel::connectDatabase(require $appRoot . 'databaseSettings.php');
