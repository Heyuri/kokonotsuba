<?php

class moduleEngineContext {
    public function __construct(
        public readonly array $config,
        public readonly ?string $liveIndexFile,
        public readonly ?array $moduleList,
        public readonly postRepository $postRepository,
        public readonly postService $postService,
        public readonly threadRepository $threadRepository,
        public readonly threadService $threadService,
        public readonly postSearchService $postSearchService,
        public readonly quoteLinkService $quoteLinkService,
        public readonly boardService $boardService,
        public readonly attachmentService $attachmentService,
        public readonly actionLoggerService $actionLoggerService,
        public readonly postRedirectService $postRedirectService,
        public transactionManager $transactionManager,
        public ?templateEngine $templateEngine,
        public board $board,
    ) {}
}