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
		public mixed $FileIO,
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
		public DatabaseConnection $databaseConnection,
		public boardPathService $boardPathService,
		public attachmentService $attachmentService,
		public array $visibleBoards,
		public array $regularBoards,
		public quoteLinkRepository $quoteLinkRepository,
		public quoteLinkService $quoteLinkService,
		public deletedPostsService $deletedPostsService
	) {}
}
