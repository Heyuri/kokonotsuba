<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\board\board;
use Kokonotsuba\board\boardService;
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
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;

class moduleContext {
	public function __construct(
		public board $board,
		public templateEngine $templateEngine,
		public readonly array $config,
		public readonly postRepository $postRepository,
		public readonly postService $postService,
		public readonly threadRepository $threadRepository,
		public readonly threadService $threadService,
		public readonly pageRenderer $adminPageRenderer,
		public readonly moduleEngine $moduleEngine,
		public readonly boardService $boardService,
		public readonly postSearchService $postSearchService,
		public readonly quoteLinkService $quoteLinkService,
		public readonly actionLoggerService $actionLoggerService,
		public readonly postRedirectService $postRedirectService,
		public readonly deletedPostsService $deletedPostsService,
		public readonly fileService $fileService,
		public capcodeService $capcodeService,
		public array $userCapcodes, 
		public transactionManager $transactionManager,
		public postRenderingPolicy $postRenderingPolicy,
	) {}
}
