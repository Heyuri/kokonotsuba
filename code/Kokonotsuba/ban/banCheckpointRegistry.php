<?php

namespace Kokonotsuba\ban;

/**
 * Every checkpoint a ban can name: the built-in ones plus whatever modules registered.
 *
 * A module registers during initialize() and the entry shows up as another checkbox on the ban
 * form; enforcing it is then a matter of calling banService::assertNotBanned() with the same key.
 */
class banCheckpointRegistry {
	/** @var array<string, array{key: string, label: string, default: bool}> */
	private array $extra = [];

	/**
	 * @param string $key     Stable identifier stored in the ban row. Lowercase letters, digits,
	 *                        underscores and hyphens only.
	 * @param string $label   Human-readable label for the ban form.
	 * @param bool   $default Whether the form ticks it by default.
	 */
	public function register(string $key, string $label, bool $default = false): void {
		$key = strtolower(trim($key));

		if ($key === '' || !preg_match('/^[a-z0-9_-]+$/', $key)) {
			throw new \InvalidArgumentException("Invalid ban checkpoint key: {$key}");
		}

		if (banCheckpoint::tryFrom($key) !== null) {
			return; // a built-in already owns this key
		}

		$this->extra[$key] = ['key' => $key, 'label' => $label, 'default' => $default];
	}

	/**
	 * Built-ins first, then registered extras in registration order.
	 *
	 * @return list<array{key: string, label: string, default: bool}>
	 */
	public function all(): array {
		$entries = [];

		foreach (banCheckpoint::cases() as $case) {
			$entries[] = ['key' => $case->value, 'label' => $case->label(), 'default' => $case->isDefault()];
		}

		return array_merge($entries, array_values($this->extra));
	}

	/** @return list<string> Keys ticked by default on a fresh ban form. */
	public function defaultKeys(): array {
		return array_values(array_map(
			fn(array $entry): string => $entry['key'],
			array_filter($this->all(), fn(array $entry): bool => $entry['default'])
		));
	}

	public function has(string $key): bool {
		return banCheckpoint::tryFrom($key) !== null || isset($this->extra[$key]);
	}

	/** Label for a key, falling back to the key itself for a checkpoint whose module is off. */
	public function labelFor(string $key): string {
		$case = banCheckpoint::tryFrom($key);

		if ($case !== null) {
			return $case->label();
		}

		return $this->extra[$key]['label'] ?? $key;
	}

	/**
	 * Drop anything the registry doesn't know about and de-duplicate.
	 *
	 * @param list<string> $keys
	 * @return list<string>
	 */
	public function filterKnown(array $keys): array {
		$known = [];

		foreach ($keys as $key) {
			$key = strtolower(trim((string) $key));

			if ($key !== '' && $this->has($key) && !in_array($key, $known, true)) {
				$known[] = $key;
			}
		}

		return $known;
	}
}
