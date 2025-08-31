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
$attachmentRepository = new attachmentRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$attachmentService = new attachmentService($attachmentRepository);
$threadRepository = new threadRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$postRepository = new postRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$postService = new postService($postRepository, $transactionManager, $threadRepository, $attachmentService);
$threadService = new threadService($databaseConnection, $threadRepository, $postRepository, $postService, $attachmentService, $transactionManager, $dbSettings['THREAD_TABLE'], $dbSettings['POST_TABLE']);
$quoteLinkRepository = new quoteLinkRepository($databaseConnection, $dbSettings['QUOTE_LINK_TABLE'], $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$quoteLinkService = new quoteLinkService($quoteLinkRepository, $postRepository);
$postSearchRepository = new postSearchRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$postSearchService = new postSearchService($postSearchRepository);
$postRedirectRepository = new postRedirectRepository($databaseConnection, $dbSettings['THREAD_REDIRECT_TABLE'], $dbSettings['THREAD_TABLE']);
$postRedirectService = new postRedirectService($postRedirectRepository, $threadService);
$deletedPostsRepository = new deletedPostsRepository($databaseConnection, 'test', 'test');
$deletedPostsService = new deletedPostsService($transactionManager, $deletedPostsRepository, $attachmentService, $actionLoggerService, $postRepository);