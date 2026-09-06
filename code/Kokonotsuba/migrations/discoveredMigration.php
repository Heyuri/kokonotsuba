<?php

namespace Kokonotsuba\migrations;

use RuntimeException;

/** A migration file found on disk, not yet loaded. */
final class discoveredMigration {
	private ?migration $instance = null;

	public function __construct(
		public readonly string $namespace,
		public readonly string $version,
		public readonly string $name,
		public readonly string $path
	) {}

	/** Filenames are {YYYYMMDD}_{HHMMSS}_{name}.php; anything else is not a migration. */
	public static function fromPath(string $namespace, string $path): ?self {
		if (!preg_match('/^(\d{8}_\d{6})_(.+)\.php$/', basename($path), $matches)) {
			return null;
		}

		return new self($namespace, $matches[1], $matches[2], $path);
	}

	public function id(): string {
		return "{$this->namespace}/{$this->version}_{$this->name}";
	}

	public function checksum(): string {
		return hash_file('sha256', $this->path);
	}

	public function load(): migration {
		if ($this->instance !== null) {
			return $this->instance;
		}

		$returned = require $this->path;
		if (!$returned instanceof migration) {
			throw new RuntimeException("{$this->path} must return a migration instance.");
		}

		return $this->instance = $returned;
	}
}
