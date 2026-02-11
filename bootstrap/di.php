<?php

use Kokonotsuba\containers\routeDiContainer;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\board\boardPostNumbers;
use Kokonotsuba\cache\path_cache\boardPathService;
use Kokonotsuba\capcode_backend\capcodeService;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\post\attachment\fileService;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\thread\postRedirectService;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\post\postSearchService;
use Kokonotsuba\post\postService;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;

// ───────────────────────────────────────
// DI containers
// ───────────────────────────────────────
$routeDiContainer = new routeDiContainer(
	$board,
	$config,
	$moduleEngine,
	$templateEngine,
	$adminTemplateEngine,
	$overboard,
	$adminPageRenderer,
	$softErrorHandler,
	$boardRepository,
	$boardService,
	$postRepository,
	$postService,
	$threadRepository,
	$threadService,
	$accountRepository,
	$accountService,
	$actionLoggerRepository,
	$actionLoggerService,
	$adminLoginController,
	$staffAccountFromSession,
	$postValidator,
	$transactionManager,
	$postRedirectService,
	$databaseConnection,
	$boardPathService,
	$visibleBoards,
	$boardList,
	$quoteLinkRepository,
	$quoteLinkService,
	$deletedPostsService,
	$postPolicy,
	$threadApi,
	$boardApi,
	$fileService,
	$postRenderingPolicy,
);
