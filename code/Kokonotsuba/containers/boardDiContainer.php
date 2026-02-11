<?php

namespace Kokonotsuba\containers;

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

class boardDiContainer {
    public function __construct(
		public readonly postRepository $postRepository,
        public readonly postService $postService,
		public readonly actionLoggerService $actionLoggerService,
		public readonly threadRepository $threadRepository,
		public readonly threadService $threadService,
        public readonly quoteLinkService $quoteLinkService,
		public readonly boardPostNumbers $boardPostNumbers,
		public readonly boardPathService $boardPathService,
		public readonly postSearchService $postSearchService,
		public readonly postRedirectService $postRedirectService,
		public readonly deletedPostsService $deletedPostsService,
		public capcodeService $capcodeService,
		public array $userCapcodes,
		public readonly fileService $fileService,
		public readonly transactionManager $transactionManager,
		public postRenderingPolicy $postRenderingPolicy,
    ) {}
}