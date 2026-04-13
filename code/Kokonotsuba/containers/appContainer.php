<?php

namespace Kokonotsuba\containers;

class appContainer {
	private array $services = [];

	public function set(string $id, mixed $service): void {
		$this->services[$id] = $service;
	}

	public function get(string $id): mixed {
		if (!array_key_exists($id, $this->services)) {
			throw new \RuntimeException("Service '{$id}' not found in container.");
		}
		return $this->services[$id];
	}

	public function has(string $id): bool {
		return array_key_exists($id, $this->services);
	}
}
