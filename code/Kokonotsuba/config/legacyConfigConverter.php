<?php

namespace Kokonotsuba\config;

/**
 * Converts a pre-database board configuration into the overrides the config editor stores.
 *
 * Before the config editor, every board's settings lived in PHP files: global/globalBoardConfig.php
 * held the defaults and global/board-configs/board-{uid}.php `require`d it and reassigned whatever
 * that board wanted differently. Both produced one flat $config array.
 *
 * This turns such an array back into the dot-path => value overrides that configService persists,
 * dealing with the three ways the old files differ from today's schema:
 *
 *   1. Module settings were a single flat bag, $config['ModuleSettings']['RENZOKU3'], with no
 *      mention of the owning module. The schema now namespaces them (modules.antiFlood.RENZOKU3),
 *      so a legacy key is matched by its bare name (unambiguous: no two modules share one).
 *   2. Some settings changed shape - template names lost their '.tpl' suffix, VIDEO_EXT became a
 *      list rather than a pipe-separated string, ad slot sizes became "WIDTHxHEIGHT" strings.
 *   3. Old files carry keys the schema no longer knows (removed features, and immutable globals
 *      like STATIC_URL that are not board-editable). Those are dropped, not stored.
 *
 * Everything here is pure: file loading and database writes belong to the CLI script that calls it
 * (Utilities/migrateBoardConfigs-cli.php).
 */
class legacyConfigConverter {
	/** Legacy flat bag of module settings. */
	private const LEGACY_MODULE_BAG = 'ModuleSettings';

	/** @var array<string, string>|null Cached bare module key => schema dot-path. */
	private static ?array $moduleKeyMap = null;

	/**
	 * Map a bare legacy ModuleSettings key to its namespaced schema dot-path.
	 *
	 * @param string $bareKey e.g. 'RENZOKU3'.
	 * @return string|null e.g. 'modules.antiFlood.RENZOKU3', or null if no module declares it.
	 */
	public static function moduleDotpathFor(string $bareKey): ?string {
		if (self::$moduleKeyMap === null) {
			self::$moduleKeyMap = [];

			foreach (configSchema::getAllFields() as $dotpath => $meta) {
				if (!str_starts_with((string)$dotpath, 'modules.')) {
					continue;
				}

				$segments = explode('.', (string)$dotpath);
				self::$moduleKeyMap[end($segments)] = (string)$dotpath;
			}
		}

		return self::$moduleKeyMap[$bareKey] ?? null;
	}

	/**
	 * Reduce a legacy $config array to the overrides worth storing: every schema field whose legacy
	 * value differs from what the scope would inherit anyway.
	 *
	 * @param array $legacyConfig The flat $config array produced by an old config file.
	 * @param array $baseline     dot-path => value this scope inherits (schema defaults for the
	 *                            global scope; the global config's values for a board).
	 * @return array<string, mixed> dot-path => override value.
	 */
	public static function extractOverrides(array $legacyConfig, array $baseline): array {
		$overrides = [];

		// Walk the schema rather than the legacy array: that way a key the schema dropped is
		// ignored for free, and each value is converted knowing the type it has to end up as.
		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			$dotpath = (string)$dotpath;

			$legacyValue = self::legacyValueFor($legacyConfig, $dotpath);
			if ($legacyValue === null) {
				continue; // the old config never set this one
			}

			$value = self::convertValue($dotpath, $meta, $legacyValue);
			$inherited = $baseline[$dotpath] ?? $meta['default'];

			if (json_encode($value) !== json_encode($inherited)) {
				$overrides[$dotpath] = $value;
			}
		}

		return $overrides;
	}

	/**
	 * Find a schema field's value in a legacy config array, or null if it isn't set there.
	 *
	 * A module field is looked up in the legacy flat ModuleSettings bag by its bare name; every
	 * other field is a dot-path into the array exactly as it is today (ALWAYS_NOKO,
	 * ModuleList.catalog, ...).
	 *
	 * @param array  $legacyConfig Legacy config array.
	 * @param string $dotpath      Schema dot-path.
	 * @return mixed The legacy value, or null when absent.
	 */
	public static function legacyValueFor(array $legacyConfig, string $dotpath): mixed {
		if (str_starts_with($dotpath, 'modules.')) {
			$segments = explode('.', $dotpath);
			$bareKey = end($segments);

			return $legacyConfig[self::LEGACY_MODULE_BAG][$bareKey] ?? null;
		}

		$cursor = $legacyConfig;
		foreach (explode('.', $dotpath) as $segment) {
			if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
				return null;
			}
			$cursor = $cursor[$segment];
		}

		return $cursor;
	}

	/**
	 * Convert a legacy value into the shape and type the schema declares for that field.
	 *
	 * @param string $dotpath     Schema dot-path.
	 * @param array  $meta        Normalized schema metadata for the field.
	 * @param mixed  $legacyValue The value as the old config file had it.
	 * @return mixed The value in its current shape.
	 */
	public static function convertValue(string $dotpath, array $meta, mixed $legacyValue): mixed {
		// Ad slot sizes were ['top' => ['width' => 728, 'height' => 90], ...]; they are "728x90" now.
		if ($dotpath === 'modules.ads.ADS_SLOT_DIMENSIONS' && is_array($legacyValue)) {
			$slots = [];
			foreach ($legacyValue as $slot => $size) {
				if (is_array($size) && isset($size['width'], $size['height'])) {
					$slots[(string)$slot] = ((int)$size['width']) . 'x' . ((int)$size['height']);
				} elseif (is_scalar($size)) {
					$slots[(string)$slot] = (string)$size; // already converted
				}
			}
			return $slots;
		}

		// VIDEO_EXT was a pipe-separated string ('WEBM|MP4'); it is a list now. The old files spelled
		// the extensions in caps while the schema default is lower case, and the consumer lowercases
		// them before comparing anyway - so normalise, or every install would migrate an "override"
		// that differs from the default only in case.
		if ($dotpath === 'VIDEO_EXT' && is_string($legacyValue)) {
			return array_values(array_filter(
				array_map(
					static fn(string $ext): string => strtolower(trim($ext)),
					explode('|', $legacyValue)
				),
				static fn(string $ext): bool => $ext !== ''
			));
		}

		switch ($meta['type']) {
			case configSchema::TYPE_TEMPLATE:
				// Template names were file names ('kokoimg.tpl'); they are directory names now.
				$name = is_scalar($legacyValue) ? (string)$legacyValue : '';
				return preg_replace('/\.tpl$/i', '', $name) ?? $name;

			case configSchema::TYPE_BOOL:
				return (bool)$legacyValue;

			case configSchema::TYPE_INT:
				$int = (int)$legacyValue;
				$min = $meta['min'] ?? configSchema::DEFAULT_INT_MIN;
				return $min === null ? $int : max((int)$min, $int);

			case configSchema::TYPE_ARRAY:
				return is_array($legacyValue) ? $legacyValue : [];

			case configSchema::TYPE_STRING:
			case configSchema::TYPE_TEXT:
			default:
				return is_scalar($legacyValue) ? (string)$legacyValue : '';
		}
	}

	/**
	 * Reset the cached module key map. Only needed by tests that reload the schema.
	 *
	 * @return void
	 */
	public static function resetCache(): void {
		self::$moduleKeyMap = null;
	}
}
