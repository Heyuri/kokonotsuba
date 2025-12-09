<?php
// ───────────────────────────────────────
// Board Bootstrap
// ───────────────────────────────────────
$boardPostNumbers = new boardPostNumbers($databaseConnection, $dbSettings['POST_NUMBER_TABLE']);

$boardPathRepository = new boardPathRepository($databaseConnection, $dbSettings['BOARD_PATH_CACHE_TABLE']);

$boardPathService = new boardPathService($boardPathRepository);

$boardRepository = new boardRepository($databaseConnection, $dbSettings['BOARD_TABLE']);

$boardDiContainer = new boardDiContainer(
	$postRepository, 
	$postService, 
	$actionLoggerService, 
	$threadRepository, 
	$threadService, 
	$quoteLinkService, 
	$boardPostNumbers, 
	$boardPathService, 
	$postSearchService,
	$postRedirectService,
	$deletedPostsService,
	$capcodeService,
	$userCapcodes,
	$fileService,
	$transactionManager
);

$boardService = new boardService($boardRepository, $boardDiContainer, $boardPathService);

$boardList = $boardService->getAllRegularBoards();
$visibleBoards = $boardService->getAllListedBoards();

// Globally accessible board array, it exists to avoid managing complicated dependencies and circular dependencies
// Defines and globals are to be avoided, but this is an exception
define('GLOBAL_BOARD_ARRAY', $boardService->getAllRegularBoards());