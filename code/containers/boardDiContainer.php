<?php

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
		public readonly transactionManager $transactionManager
    ) {}
}