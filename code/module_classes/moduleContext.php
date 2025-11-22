<?php

namespace Kokonotsuba\ModuleClasses;

use actionLoggerService;
use \board;
use boardService;
use capcodeService;
use deletedPostsService;
use fileService;
use \moduleEngine;
use \pageRenderer;
use postRedirectService;
use \postRepository;
use postSearchService;
use \postService;
use quoteLinkService;
use \templateEngine;
use \threadRepository;
use \threadService;
use transactionManager;

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
		public transactionManager $transactionManager
	) {}
}
