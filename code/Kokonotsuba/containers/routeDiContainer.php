<?php

/**
 * Dependency Injection Container for Mode Handler routes
 *
 * This class acts as a container to manage and provide dependencies
 * required for handling various mode operations in the application.
 *
 * It uses PHP 8.0+ Constructor Property Promotion to automatically
 * assign dependencies to public properties upon instantiation.
 *
 * All services must be passed to the constructor when creating an instance.
 */

namespace Kokonotsuba\containers;

use Kokonotsuba\action_log\actionLoggerRepository;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\account\accountRepository;
use Kokonotsuba\account\accountService;
use Kokonotsuba\api\boardApi;
use Kokonotsuba\api\threadApi;
use Kokonotsuba\board\board;
use Kokonotsuba\board\boardRepository;
use Kokonotsuba\board\boardService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\log_in\adminLoginController;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\overboard;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\post\attachment\fileService;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\thread\postRedirectService;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\post\postService;
use Kokonotsuba\post\postValidator;
use Kokonotsuba\quote_link\quoteLinkRepository;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\cache\path_cache\boardPathService;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\post\helper\postDateFormatter;

class routeDiContainer {
	public function __construct(
		public board $board,
		public array $config,
		public moduleEngine $moduleEngine,
		public templateEngine $templateEngine,
		public templateEngine $adminTemplateEngine,
		public overboard $overboard,
		public pageRenderer $adminPageRenderer,
		public softErrorHandler $softErrorHandler,
		public boardRepository $boardRepository,
		public boardService $boardService,
		public postRepository $postRepository,
		public postService $postService,
		public threadRepository $threadRepository,
		public threadService $threadService,
		public accountRepository $accountRepository,
		public accountService $accountService,
		public actionLoggerRepository $actionLoggerRepository,
		public actionLoggerService $actionLoggerService,
		public adminLoginController $adminLoginController,
		public staffAccountFromSession $staffAccountFromSession,
		public postValidator $postValidator,
		public transactionManager $transactionManager,
		public postRedirectService $postRedirectService,
		public databaseConnection $databaseConnection,
		public cookieService $cookieService,
		public boardPathService $boardPathService,
		public array $visibleBoards,
		public array $regularBoards,
		public quoteLinkRepository $quoteLinkRepository,
		public quoteLinkService $quoteLinkService,
		public deletedPostsService $deletedPostsService,
		public postPolicy $postPolicy,
		public threadApi $threadApi,
		public boardApi $boardApi,
		public fileService $fileService,
		public postRenderingPolicy $postRenderingPolicy,
		public postDateFormatter $postDateFormatter,
		public ?int $currentUserId
	) {}
}
