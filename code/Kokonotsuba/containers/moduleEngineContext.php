<?php

namespace Kokonotsuba\containers;

use Kokonotsuba\board\board;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\template\templateEngine;

class moduleEngineContext {
    public function __construct(
        public readonly array $config,
        public readonly ?string $liveIndexFile,
        public readonly ?array $moduleList,
        public templateEngine $templateEngine,
        public board $board,
        public postDateFormatter $postDateFormatter,
        private readonly appContainer $container,
    ) {}

    public function getContainer(): appContainer {
        return $this->container;
    }

    public function __get(string $name): mixed {
        return $this->container->get($name);
    }
}