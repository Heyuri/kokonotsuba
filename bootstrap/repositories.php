<?php

/**
 * Variables injected from the calling bootstrap context (e.g. koko.php).
 *
 * @var \Kokonotsuba\database\databaseConnection $databaseConnection
 * @var array                                    $dbSettings
 * @var array                                    $tableNames
 * @var \Kokonotsuba\database\transactionManager $transactionManager
 * @var \Kokonotsuba\request\request             $request
 * @var \Kokonotsuba\containers\appContainer     $container
 */

// ───────────────────────────────────────
// Account and action log Bootstrap
// ───────────────────────────────────────

use Kokonotsuba\account\accountRepository;
use Kokonotsuba\account\accountService;
use Kokonotsuba\action_log\actionLoggerRepository;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\capcode_backend\capcodeRepository;
use Kokonotsuba\capcode_backend\capcodeService;
use Kokonotsuba\post\attachment\fileRepository;
use Kokonotsuba\post\attachment\fileService;
use Kokonotsuba\post\deletion\deletedPostsRepository;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\thread\postRedirectRepository;
use Kokonotsuba\thread\postRedirectService;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\post\postSearchRepository;
use Kokonotsuba\post\postSearchService;
use Kokonotsuba\post\postService;
use Kokonotsuba\post\deletion\postDeletionService;
use Kokonotsuba\quote_link\quoteLinkRepository;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;

$accountRepository = new accountRepository($databaseConnection, $tableNames['ACCOUNT_TABLE']);

$actionLoggerRepository = new actionLoggerRepository($databaseConnection, $tableNames['ACTIONLOG_TABLE'], $tableNames['BOARD_TABLE']);
$actionLoggerService = new actionLoggerService($actionLoggerRepository, $accountRepository, $request, $transactionManager); 

$accountService = new accountService($accountRepository, $actionLoggerService, $request);

// ───────────────────────────────────────
// Post/Thread Bootstrap
// ───────────────────────────────────────
$fileRepository = new fileRepository($databaseConnection, $tableNames['FILE_TABLE'], $tableNames['POST_TABLE'], $tableNames['DELETED_POSTS_TABLE']);
$fileService = new fileService($fileRepository);
$threadRepository = new threadRepository($databaseConnection, $tableNames['POST_TABLE'], $tableNames['THREAD_TABLE'], $tableNames['THREAD_THEMES_TABLE'], $tableNames['DELETED_POSTS_TABLE'], $tableNames['FILE_TABLE'], $tableNames['ACCOUNT_TABLE'], $tableNames['SOUDANE_TABLE'], $tableNames['NOTE_TABLE'], $tableNames['COUNTRY_FLAG_TABLE'], $tableNames['DISPLAY_IP_TABLE'], $tableNames['REPORT_TABLE']);
$postRepository = new postRepository($databaseConnection, $tableNames['POST_TABLE'], $tableNames['THREAD_TABLE'], $tableNames['DELETED_POSTS_TABLE'], $tableNames['FILE_TABLE'], $tableNames['SOUDANE_TABLE'], $tableNames['NOTE_TABLE'], $tableNames['ACCOUNT_TABLE'], $tableNames['COUNTRY_FLAG_TABLE'], $tableNames['DISPLAY_IP_TABLE'], $tableNames['REPORT_TABLE']);
$deletedPostsRepository = new deletedPostsRepository($databaseConnection, $tableNames['DELETED_POSTS_TABLE'], $tableNames['POST_TABLE'], $tableNames['ACCOUNT_TABLE'], $tableNames['FILE_TABLE'], $tableNames['THREAD_TABLE'], $tableNames['SOUDANE_TABLE'], $tableNames['NOTE_TABLE']);
$deletedPostsService = new deletedPostsService($transactionManager, $deletedPostsRepository, $fileService, $actionLoggerService, $postRepository, $threadRepository);
$postDeletionService = new postDeletionService($postRepository, $transactionManager, $threadRepository, $deletedPostsService, $request);
$postService = new postService($postRepository, $transactionManager, $threadRepository, $deletedPostsService, $request, $postDeletionService);
$threadService = new threadService($threadRepository, $postRepository, $postService, $transactionManager, $deletedPostsService, $fileService);
$quoteLinkRepository = new quoteLinkRepository($databaseConnection, $tableNames['QUOTE_LINK_TABLE'], $tableNames['POST_TABLE'], $tableNames['THREAD_TABLE'], $tableNames['DELETED_POSTS_TABLE']);
$quoteLinkService = new quoteLinkService($quoteLinkRepository, $postRepository);
$postSearchRepository = new postSearchRepository($databaseConnection, $tableNames['POST_TABLE'], $tableNames['THREAD_TABLE'], $tableNames['DELETED_POSTS_TABLE'], $tableNames['FILE_TABLE'], $tableNames['SOUDANE_TABLE'], $tableNames['NOTE_TABLE'], $tableNames['ACCOUNT_TABLE'], $tableNames['COUNTRY_FLAG_TABLE'], $tableNames['DISPLAY_IP_TABLE']);
$postSearchService = new postSearchService($postSearchRepository);
$postRedirectRepository = new postRedirectRepository($databaseConnection, $tableNames['THREAD_REDIRECT_TABLE'], $tableNames['THREAD_TABLE']);
$postRedirectService = new postRedirectService($postRedirectRepository, $threadService);
$capcodeRepository = new capcodeRepository($databaseConnection, $tableNames['CAPCODE_TABLE'], $tableNames['ACCOUNT_TABLE']);
$capcodeService = new capcodeService($capcodeRepository, $transactionManager);

// init user capcodes as well
$userCapcodes = $capcodeService->listCapcodes();

// ───────────────────────────────────────
// Register in container
// ───────────────────────────────────────
$container->set('accountRepository', $accountRepository);
$container->set('actionLoggerRepository', $actionLoggerRepository);
$container->set('actionLoggerService', $actionLoggerService);
$container->set('accountService', $accountService);
$container->set('fileRepository', $fileRepository);
$container->set('fileService', $fileService);
$container->set('threadRepository', $threadRepository);
$container->set('postRepository', $postRepository);
$container->set('deletedPostsRepository', $deletedPostsRepository);
$container->set('deletedPostsService', $deletedPostsService);
$container->set('postDeletionService', $postDeletionService);
$container->set('postService', $postService);
$container->set('threadService', $threadService);
$container->set('quoteLinkRepository', $quoteLinkRepository);
$container->set('quoteLinkService', $quoteLinkService);
$container->set('postSearchRepository', $postSearchRepository);
$container->set('postSearchService', $postSearchService);
$container->set('postRedirectRepository', $postRedirectRepository);
$container->set('postRedirectService', $postRedirectService);
$container->set('capcodeRepository', $capcodeRepository);
$container->set('capcodeService', $capcodeService);
$container->set('userCapcodes', $userCapcodes);