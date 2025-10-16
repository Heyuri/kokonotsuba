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
	$attachmentService,
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

// Get the board that's currently being accessed by the request
$board = $boardService->getBoardFromBootstrapFile('./boardUID.ini');

// Globally accessible board array, it exists to avoid managing complicated dependencies and circular dependencies
// Defines and globals are to be avoided, but this is an exception
define('GLOBAL_BOARD_ARRAY', $boardService->getAllRegularBoards());

// ───────────────────────────────────────
// Validate Early
// ───────────────────────────────────────
if (!file_exists($board->getFullConfigPath()) || !file_exists($board->getBoardStoragePath())) {
	die("Invalid board setup");
}

$config = $board->loadBoardConfig();
