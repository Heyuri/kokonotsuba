<?php

namespace Kokonotsuba\action_log;

/**
 * Every action type the log can hold: the built-in ones plus whatever modules registered.
 *
 * A module registers during initialize() and the entry shows up as another checkbox on the action
 * log filter form; tagging entries with it is then a matter of passing the same key to logAction().
 */
class actionTypeRegistry {
	/** @var array<string, array{key: string, label: string, group: string, default: bool}> */
	private array $extra = [];

	/**
	 * @param string $key     Stable identifier stored in the log row. Lowercase letters, digits,
	 *                        dots, underscores and hyphens only.
	 * @param string $label   Human-readable label for the filter form.
	 * @param string $group   Group key to file it under; unknown groups fall back to "tool".
	 * @param bool   $default Whether the form ticks it by default.
	 */
	public function register(string $key, string $label, string $group = 'tool', bool $default = true): void {
		$key = strtolower(trim($key));

		if ($key === '' || !preg_match('/^[a-z0-9._-]+$/', $key)) {
			throw new \InvalidArgumentException("Invalid action type key: {$key}");
		}

		if (actionType::tryFrom($key) !== null) {
			return; // a built-in already owns this key
		}

		$group = actionTypeGroup::tryFrom($group)?->value ?? actionTypeGroup::TOOL->value;

		$this->extra[$key] = ['key' => $key, 'label' => $label, 'group' => $group, 'default' => $default];
	}

	/**
	 * Built-ins first, then registered extras in registration order.
	 *
	 * @return list<array{key: string, label: string, group: string, default: bool}>
	 */
	public function all(): array {
		$entries = [];

		foreach (actionType::cases() as $case) {
			$entries[] = [
				'key' => $case->value,
				'label' => $case->label(),
				'group' => $case->group()->value,
				'default' => $case->isDefault(),
			];
		}

		return array_merge($entries, array_values($this->extra));
	}

	/**
	 * Entries bucketed by group, in group declaration order. Only non-empty groups are returned.
	 *
	 * @return array<string, array{label: string, entries: list<array{key: string, label: string, group: string, default: bool}>}>
	 */
	public function grouped(): array {
		$groups = [];

		foreach (actionTypeGroup::cases() as $group) {
			$groups[$group->value] = ['label' => $group->label(), 'entries' => []];
		}

		foreach ($this->all() as $entry) {
			$groups[$entry['group']]['entries'][] = $entry;
		}

		return array_filter($groups, fn(array $group): bool => $group['entries'] !== []);
	}

	/** @return list<string> Keys ticked on a fresh filter form. */
	public function defaultKeys(): array {
		return array_values(array_map(
			fn(array $entry): string => $entry['key'],
			array_filter($this->all(), fn(array $entry): bool => $entry['default'])
		));
	}

	/** @return list<string> Every known key. */
	public function allKeys(): array {
		return array_values(array_map(fn(array $entry): string => $entry['key'], $this->all()));
	}

	public function has(string $key): bool {
		return actionType::tryFrom($key) !== null || isset($this->extra[$key]);
	}

	/** Label for a key, falling back to the key itself for a type whose module is off. */
	public function labelFor(string $key): string {
		return actionType::tryFrom($key)?->label() ?? $this->extra[$key]['label'] ?? $key;
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
