<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\board\board;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;

class moduleContext {
	/**
	 * @param array<string, string> $tableNames Canonical logical key => real table name, from tables.php.
	 */
	public function __construct(
		public board $board,
		public templateEngine $templateEngine,
		public readonly array $config,
		public readonly pageRenderer $adminPageRenderer,
		public readonly moduleEngine $moduleEngine,
		public postDateFormatter $postDateFormatter,
		private readonly appContainer $container,
		public readonly array $tableNames,
	) {}

	public function getContainer(): appContainer {
		return $this->container;
	}

	/**
	 * The real name of one table, by its logical key. Modules building a repository should take
	 * their table names from here rather than reading the database settings.
	 */
	public function getTableName(string $key): string {
		if (!isset($this->tableNames[$key])) {
			throw new \InvalidArgumentException("Unknown table key: {$key}. Add it to tables.php.");
		}

		return $this->tableNames[$key];
	}

	/** @return array<string, string> */
	public function getTableNames(): array {
		return $this->tableNames;
	}

	public function __get(string $name): mixed {
		return $this->container->get($name);
	}
}
