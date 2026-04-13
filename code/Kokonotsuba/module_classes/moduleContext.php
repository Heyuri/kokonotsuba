<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\board\board;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;

class moduleContext {
	public function __construct(
		public board $board,
		public templateEngine $templateEngine,
		public readonly array $config,
		public readonly pageRenderer $adminPageRenderer,
		public readonly moduleEngine $moduleEngine,
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
