<?php
// ───────────────────────────────────────
// Account and action log Bootstrap
// ───────────────────────────────────────
$accountRepository = new accountRepository($databaseConnection, $dbSettings['ACCOUNT_TABLE']);

$actionLoggerRepository = new actionLoggerRepository($databaseConnection, $dbSettings['ACTIONLOG_TABLE'], $dbSettings['BOARD_TABLE']);
$actionLoggerService = new actionLoggerService($actionLoggerRepository, $accountRepository); 

$accountService = new accountService($accountRepository, $actionLoggerService);

// ───────────────────────────────────────
// Post/Thread Bootstrap
// ───────────────────────────────────────
$fileRepository = new fileRepository($databaseConnection, $dbSettings['FILE_TABLE'], $dbSettings['POST_TABLE'], $dbSettings['DELETED_POSTS_TABLE']);
$fileService = new fileService($fileRepository);
$threadRepository = new threadRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE'], $dbSettings['THREAD_THEMES_TABLE'], $dbSettings['DELETED_POSTS_TABLE'], $dbSettings['FILE_TABLE']);
$postRepository = new postRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE'], $dbSettings['DELETED_POSTS_TABLE'], $dbSettings['FILE_TABLE']);
$deletedPostsRepository = new deletedPostsRepository($databaseConnection, $dbSettings['DELETED_POSTS_TABLE'], $dbSettings['POST_TABLE'], $dbSettings['ACCOUNT_TABLE'], $dbSettings['FILE_TABLE'], $dbSettings['THREAD_TABLE']);
$deletedPostsService = new deletedPostsService($transactionManager, $deletedPostsRepository, $fileService, $actionLoggerService, $postRepository, $threadRepository);
$postService = new postService($postRepository, $transactionManager, $threadRepository, $deletedPostsService);
$threadService = new threadService($threadRepository, $postRepository, $postService, $transactionManager, $deletedPostsService, $fileService);
$quoteLinkRepository = new quoteLinkRepository($databaseConnection, $dbSettings['QUOTE_LINK_TABLE'], $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE'], $dbSettings['DELETED_POSTS_TABLE']);
$quoteLinkService = new quoteLinkService($quoteLinkRepository, $postRepository);
$postSearchRepository = new postSearchRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE'], $dbSettings['DELETED_POSTS_TABLE'], $dbSettings['FILE_TABLE']);
$postSearchService = new postSearchService($postSearchRepository);
$postRedirectRepository = new postRedirectRepository($databaseConnection, $dbSettings['THREAD_REDIRECT_TABLE'], $dbSettings['THREAD_TABLE']);
$postRedirectService = new postRedirectService($postRedirectRepository, $threadService);
$capcodeRepository = new capcodeRepository($databaseConnection, $dbSettings['CAPCODE_TABLE'], $dbSettings['ACCOUNT_TABLE']);
$capcodeService = new capcodeService($capcodeRepository, $transactionManager);

// init user capcodes as well
$userCapcodes = $capcodeService->listCapcodes();