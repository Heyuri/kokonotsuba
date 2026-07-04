<?php

namespace Kokonotsuba\config;

/**
 * Loads and caches the board configuration schema.
 *
 * The schema is defined by the PHP files under global/configs/ (see getConfigSchemaDir()).
 * Each file `return`s an associative array describing a group of settings:
 *
 *   return [
 *       // optional human-readable group label; falls back to a humanized filename
 *       '_group' => 'Flooding & rate limits',
 *
 *       // one entry per editable setting, keyed by its config dot-path
 *       // (the same path understood by board::getConfigValue(), e.g. 'ModuleSettings.RENZOKU3')
 *       'ModuleSettings.RENZOKU3' => [
 *           'default' => 30,
 *           'type'    => 'int',                       // bool|int|string|text|array (optional; inferred from default)
 *           'label'   => 'Seconds between new threads',
 *           'desc'    => 'Minimum wait before a new thread can be started.',
 *       ],
 *   ];
 *
 * Only settings declared here are editable through the board config editor and stored as
 * per-board overrides. Truly-global, board-immutable values stay in global/globalconfig.php
 * and never appear in the schema.
 */
class configSchema {
	public const TYPE_BOOL   = 'bool';
	public const TYPE_INT    = 'int';
	public const TYPE_STRING = 'string';
	public const TYPE_TEXT   = 'text';
	public const TYPE_ARRAY  = 'array';

	/** @var array<string, array<string, array>>|null Cached groups: groupName => [dotpath => normalizedMeta]. */
	private static ?array $groups = null;

	/** @var array<string, string>|null Cached group labels: groupName => human label. */
	private static ?array $groupLabels = null;

	/** @var array<string, array>|null Cached flattened field map: dotpath => normalizedMeta. */
	private static ?array $fields = null;

	/**
	 * Load and cache the schema from disk. Idempotent within a request.
	 *
	 * @return void
	 */
	private static function load(): void {
		if (self::$groups !== null) {
			return;
		}

		self::$groups = [];
		self::$groupLabels = [];
		self::$fields = [];

		$dir = getConfigSchemaDir();

		if (!is_dir($dir)) {
			return;
		}

		$files = glob($dir . '*.php') ?: [];
		sort($files, SORT_STRING);

		foreach ($files as $file) {
			$groupName = basename($file, '.php');

			$definition = require $file;
			if (!is_array($definition)) {
				continue;
			}

			$label = $definition['_group'] ?? self::humanizeGroupName($groupName);
			unset($definition['_group']);

			$normalizedFields = [];
			foreach ($definition as $dotpath => $meta) {
				$normalized = self::normalizeField((string)$dotpath, $meta);
				$normalizedFields[$dotpath] = $normalized;
				self::$fields[$dotpath] = $normalized;
			}

			self::$groups[$groupName] = $normalizedFields;
			self::$groupLabels[$groupName] = $label;
		}
	}

	/**
	 * Normalize a raw field definition into a consistent shape.
	 *
	 * @param string $dotpath Config dot-path key.
	 * @param mixed  $meta    Raw metadata array from a schema file.
	 * @return array{default: mixed, type: string, label: string, desc: string}
	 */
	private static function normalizeField(string $dotpath, mixed $meta): array {
		$meta = is_array($meta) ? $meta : ['default' => $meta];

		$default = $meta['default'] ?? null;
		$type = $meta['type'] ?? self::inferType($default);

		return [
			'default' => $default,
			'type'    => $type,
			'label'   => $meta['label'] ?? $dotpath,
			'desc'    => $meta['desc'] ?? '',
		];
	}

	/**
	 * Infer an editor field type from a default value's PHP type.
	 *
	 * @param mixed $value Default value.
	 * @return string One of the TYPE_* constants.
	 */
	private static function inferType(mixed $value): string {
		return match (true) {
			is_bool($value)  => self::TYPE_BOOL,
			is_int($value)   => self::TYPE_INT,
			is_array($value) => self::TYPE_ARRAY,
			default          => self::TYPE_STRING,
		};
	}

	/**
	 * Turn a group filename into a human-readable label (e.g. "moduleOptions" -> "Module options").
	 *
	 * @param string $name Group filename without extension.
	 * @return string Humanized label.
	 */
	private static function humanizeGroupName(string $name): string {
		$spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $name);
		$spaced = str_replace(['_', '-'], ' ', (string)$spaced);
		return ucfirst(trim($spaced));
	}

	/**
	 * Return all groups and their fields.
	 *
	 * @return array<string, array<string, array>> groupName => [dotpath => meta].
	 */
	public static function getGroups(): array {
		self::load();
		return self::$groups;
	}

	/**
	 * Return the human-readable label for each group.
	 *
	 * @return array<string, string> groupName => label.
	 */
	public static function getGroupLabels(): array {
		self::load();
		return self::$groupLabels;
	}

	/**
	 * Return every declared field as a flat map keyed by dot-path.
	 *
	 * @return array<string, array> dotpath => meta.
	 */
	public static function getAllFields(): array {
		self::load();
		return self::$fields;
	}

	/**
	 * Return the default value for every declared field.
	 *
	 * @return array<string, mixed> dotpath => default value.
	 */
	public static function getDefaults(): array {
		self::load();
		$defaults = [];
		foreach (self::$fields as $dotpath => $meta) {
			$defaults[$dotpath] = $meta['default'];
		}
		return $defaults;
	}

	/**
	 * Look up the metadata for a single field.
	 *
	 * @param string $dotpath Config dot-path key.
	 * @return array|null Normalized metadata, or null if the field is not in the schema.
	 */
	public static function getFieldMeta(string $dotpath): ?array {
		self::load();
		return self::$fields[$dotpath] ?? null;
	}

	/**
	 * Whether the given dot-path is a declared (editable) schema field.
	 *
	 * @param string $dotpath Config dot-path key.
	 * @return bool True if declared.
	 */
	public static function hasField(string $dotpath): bool {
		self::load();
		return isset(self::$fields[$dotpath]);
	}

	/**
	 * Derive the camelCase HTML form input key for a field from its config dot-path.
	 *
	 * Config dot-paths use mixed conventions (e.g. 'ModuleSettings.RENZOKU3',
	 * 'REPLIES_PER_PAGE'); this produces a clean, dot-free camelCase identifier used as the
	 * form input name/id (e.g. 'moduleSettingsRenzoku3', 'repliesPerPage'). It is deterministic
	 * so the form renderer and the save handler compute the same key without a stored mapping.
	 *
	 * @param string $dotpath Config dot-path key.
	 * @return string camelCase input key.
	 */
	public static function inputKey(string $dotpath): string {
		$words = preg_split('/[^a-zA-Z0-9]+/', $dotpath, -1, PREG_SPLIT_NO_EMPTY) ?: [];

		$key = '';
		foreach ($words as $i => $word) {
			$word = strtolower($word);
			$key .= $i === 0 ? $word : ucfirst($word);
		}

		return $key;
	}
}
