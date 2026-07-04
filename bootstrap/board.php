<?php

/**
 * Variables injected from the calling bootstrap context (e.g. koko.php).
 *
 * @var \Kokonotsuba\database\databaseConnection $databaseConnection
 * @var array                                    $dbSettings
 * @var \Kokonotsuba\containers\appContainer     $container
 */

use Kokonotsuba\board\boardPostNumbers;
use Kokonotsuba\board\boardRepository;
use Kokonotsuba\board\boardService;
use Kokonotsuba\cache\path_cache\boardPathRepository;
use Kokonotsuba\cache\path_cache\boardPathService;
use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configService;

// ───────────────────────────────────────
// Board Bootstrap
// ───────────────────────────────────────

$boardPostNumbers = new boardPostNumbers($databaseConnection, $dbSettings['POST_NUMBER_TABLE']);

$boardPathRepository = new boardPathRepository($databaseConnection, $dbSettings['BOARD_PATH_CACHE_TABLE']);

$boardPathService = new boardPathService($boardPathRepository);

$boardRepository = new boardRepository($databaseConnection, $dbSettings['BOARD_TABLE']);

$configRepository = new configRepository($databaseConnection, $dbSettings['BOARD_CONFIG_TABLE']);
$configService = new configService($configRepository);

// Register in container before boardService uses them via assembleBoard()
$container->set('boardPostNumbers', $boardPostNumbers);
$container->set('boardPathService', $boardPathService);
$container->set('boardRepository', $boardRepository);
$container->set('configRepository', $configRepository);
$container->set('configService', $configService);

$boardService = new boardService($boardRepository, $container, $boardPathService);
$container->set('boardService', $boardService);

$boardList = $boardService->getAllRegularBoards();
$visibleBoards = $boardService->getAllListedBoards();

// Globally accessible board array, it exists to avoid managing complicated dependencies and circular dependencies
// Defines and globals are to be avoided, but this is an exception
define('GLOBAL_BOARD_ARRAY', $boardService->getAllRegularBoards());

$container->set('boardList', $boardList);
$container->set('visibleBoards', $visibleBoards);