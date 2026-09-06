<?php

namespace Kokonotsuba\config;

use InvalidArgumentException;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Resolves and persists configuration overrides.
 *
 * The effective config for a board is built in four layers, later layers winning:
 *   1. global/globalconfig.php  — board-immutable globals and computed defaults (never editable).
 *   2. configs/*.php            — the editable schema's default values (see configSchema).
 *   3. board_configs row GLOBAL_BOARD_UID — overrides applied to every board (the "global config"
 *      admin page). Stored exactly like a board's, under the reserved GLOBAL board's UID, so
 *      there's one table, one JSON shape and one code path for both scopes.
 *   4. board_configs row {board_uid} — this board's own overrides.
 *
 * Each scope stores only what differs from the layer beneath it: a board row holds only values
 * that differ from the global config, and the global row holds only values that differ from the
 * schema defaults. So a field set back to the value it inherits is dropped rather than stored,
 * and a later change to the global config still flows through to every board that didn't
 * deliberately diverge from it.
 */
class configService {
	/** @var array<int, array<string, mixed>> Overrides already read this request, by scope. */
	private array $overridesCache = [];

	public function __construct(
		private readonly configRepository $configRepository
	) {}

	/**
	 * Return a board's stored overrides, filtered to fields still present in the schema.
	 *
	 * @param int $boardUid Board UID.
	 * @return array<string, mixed> dot-path => override value.
	 */
	public function getOverrides(int $boardUid): array {
		if (isset($this->overridesCache[$boardUid])) {
			return $this->overridesCache[$boardUid];
		}

		$stored = $this->configRepository->getOverridesByBoardUid($boardUid);

		$overrides = [];
		foreach ($stored as $dotpath => $value) {
			if (configSchema::hasField((string)$dotpath)) {
				$overrides[$dotpath] = $value;
			}
		}

		return $this->overridesCache[$boardUid] = $overrides;
	}

	/**
	 * Build the default config array (immutable globals + schema defaults), with no
	 * per-board overrides applied. This is the board-agnostic baseline used at board
	 * creation time and as the base for getEffectiveConfig().
	 *
	 * @return array The default $config array, backward-compatible with the legacy cascade.
	 */
	public static function resolveDefaults(): array {
		// Layer 1: immutable globals + computed board defaults.
		$config = getGlobalConfig();
		if (!is_array($config)) {
			$config = [];
		}

		// Layer 2: schema defaults.
		foreach (configSchema::getDefaults() as $dotpath => $default) {
			self::setNested($config, (string)$dotpath, $default);
		}

		return $config;
	}

	/**
	 * Build the fully-merged effective config array for a board.
	 *
	 * @param int $boardUid Board UID. GLOBAL_BOARD_UID resolves to the global config itself
	 *                      (defaults + global overrides, with no board layer on top).
	 * @return array The complete $config array, backward-compatible with the legacy cascade.
	 */
	public function getEffectiveConfig(int $boardUid): array {
		// Layers 1 & 2: globals + schema defaults.
		$config = self::resolveDefaults();

		// Layer 3: the global config, applied to every board.
		foreach ($this->getOverrides(GLOBAL_BOARD_UID) as $dotpath => $value) {
			self::setNested($config, (string)$dotpath, $value);
		}

		// Layer 4: this board's own overrides.
		if ($boardUid !== GLOBAL_BOARD_UID) {
			foreach ($this->getOverrides($boardUid) as $dotpath => $value) {
				self::setNested($config, (string)$dotpath, $value);
			}
		}

		return $config;
	}

	/**
	 * The effective value of every schema field for a scope, as a flat dot-path map.
	 * These are the values the config editor prefills.
	 *
	 * @param int $boardUid Board UID, or GLOBAL_BOARD_UID for the global config.
	 * @return array<string, mixed> dot-path => effective value.
	 */
	public function getEffectiveValues(int $boardUid): array {
		// What the scope inherits, with its own overrides on top. For a board that's the global
		// config then the board's row; for the global config it's the schema defaults then the
		// global row.
		$values = $this->getInheritedValues($boardUid);

		foreach ($this->getOverrides($boardUid) as $dotpath => $value) {
			$values[$dotpath] = $value;
		}

		return $values;
	}

	/**
	 * The values a scope inherits when it overrides nothing — what a saved value is diffed
	 * against, and what "Reset to defaults" returns the scope to.
	 *
	 * A board inherits the global config; the global config inherits the schema defaults.
	 *
	 * @param int $boardUid Board UID, or GLOBAL_BOARD_UID for the global config.
	 * @return array<string, mixed> dot-path => inherited value.
	 */
	public function getInheritedValues(int $boardUid): array {
		$values = configSchema::getDefaults();

		if ($boardUid !== GLOBAL_BOARD_UID) {
			foreach ($this->getOverrides(GLOBAL_BOARD_UID) as $dotpath => $value) {
				$values[$dotpath] = $value;
			}
		}

		return $values;
	}

	/**
	 * Coerce, diff against the inherited values, and persist a submitted set of config values.
	 *
	 * @param int   $boardUid   Board UID, or GLOBAL_BOARD_UID to save the global config.
	 * @param array $rawSubmitted Raw submitted map keyed by camelCase input key (see
	 *                            configSchema::inputKey()). Missing bool fields are treated as
	 *                            unchecked (false); other missing fields are left untouched.
	 * @return void
	 * @throws InvalidArgumentException If an array-typed field contains invalid JSON.
	 */
	public function saveOverrides(int $boardUid, array $rawSubmitted): void {
		$overrides = [];

		// What this scope inherits: the global config for a board, the schema defaults for the
		// global config itself. Diffing against this (rather than always against the schema
		// default) is what keeps a board from silently freezing in the global value it was merely
		// shown, which would cut it off from later global changes.
		$inherited = $this->getInheritedValues($boardUid);

		// The row is rewritten wholesale, so a field missing from the submission would be dropped
		// from it - i.e. silently reset. Keep whatever is already stored for such a field instead.
		$existing = $this->getOverrides($boardUid);

		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			$type = $meta['type'];
			$inheritedValue = $inherited[$dotpath] ?? $meta['default'];
			$inputKey = configSchema::inputKey((string)$dotpath);

			// Checkboxes only submit when checked; absence means false.
			if ($type === configSchema::TYPE_BOOL) {
				$value = array_key_exists($inputKey, $rawSubmitted)
					&& self::coerce($rawSubmitted[$inputKey], configSchema::TYPE_BOOL, (string)$dotpath);
			} else {
				// Leave unsubmitted non-bool fields alone (don't force them back to the inherited
				// value): a partial submission must not wipe settings it never mentioned.
				if (!array_key_exists($inputKey, $rawSubmitted)) {
					if (array_key_exists($dotpath, $existing)) {
						$overrides[$dotpath] = $existing[$dotpath];
					}
					continue;
				}
				$value = self::coerce($rawSubmitted[$inputKey], $type, (string)$dotpath, $meta);
			}

			// Store only values that differ from what this scope already inherits.
			if (!self::valuesEqual($value, $inheritedValue)) {
				$overrides[$dotpath] = $value;
			}
		}

		$this->persistOverrides($boardUid, $overrides);
	}

	/**
	 * Drop every stored override for a scope, reverting it to the values it inherits (the global
	 * config for a board; the schema defaults for the global config itself).
	 *
	 * @param int $boardUid Board UID, or GLOBAL_BOARD_UID for the global config.
	 * @return void
	 */
	public function resetOverrides(int $boardUid): void {
		unset($this->overridesCache[$boardUid]);
		$this->configRepository->deleteOverridesForBoardUid($boardUid);
	}

	/**
	 * Set a single setting for a scope, leaving every other stored override untouched.
	 *
	 * The raw value is coerced to the field's type (and an int clamped to its minimum), then
	 * diffed against what the scope inherits: a value equal to the inherited one is not stored as
	 * an override but clears any existing one, exactly as saving the whole form would. This is the
	 * single-field counterpart of saveOverrides(), for callers that edit one setting at a time
	 * (e.g. the CLI editor) rather than posting a form.
	 *
	 * @param int    $boardUid Board UID, or GLOBAL_BOARD_UID for the global config.
	 * @param string $dotpath  Schema dot-path of the setting.
	 * @param mixed  $rawValue Raw value (a string as it would arrive from a form, a bool, etc.).
	 * @return void
	 * @throws InvalidArgumentException If the field is not in the schema, or an array field's JSON
	 *                                  is invalid.
	 */
	public function setOverride(int $boardUid, string $dotpath, mixed $rawValue): void {
		$meta = configSchema::getFieldMeta($dotpath);
		if ($meta === null) {
			throw new InvalidArgumentException("Unknown setting: '{$dotpath}'.");
		}

		$value = self::coerce($rawValue, $meta['type'], $dotpath, $meta);
		$inherited = $this->getInheritedValues($boardUid)[$dotpath] ?? $meta['default'];

		$overrides = $this->getOverrides($boardUid);

		if (self::valuesEqual($value, $inherited)) {
			// Equal to what it already inherits: not an override. Clear any stored one.
			unset($overrides[$dotpath]);
		} else {
			$overrides[$dotpath] = $value;
		}

		$this->persistOverrides($boardUid, $overrides);
	}

	/**
	 * Clear a single setting's override for a scope, reverting just that setting to the value it
	 * inherits. Other stored overrides are left untouched. A no-op if it wasn't overridden.
	 *
	 * @param int    $boardUid Board UID, or GLOBAL_BOARD_UID for the global config.
	 * @param string $dotpath  Schema dot-path of the setting.
	 * @return void
	 */
	public function unsetOverride(int $boardUid, string $dotpath): void {
		$overrides = $this->getOverrides($boardUid);

		if (!array_key_exists($dotpath, $overrides)) {
			return;
		}

		unset($overrides[$dotpath]);
		$this->persistOverrides($boardUid, $overrides);
	}

	/**
	 * Write a scope's full override set, deleting the row when it is empty (so an emptied scope
	 * leaves no row rather than an empty JSON object).
	 *
	 * @param int   $boardUid  Board UID.
	 * @param array $overrides dot-path => value.
	 * @return void
	 */
	private function persistOverrides(int $boardUid, array $overrides): void {
		unset($this->overridesCache[$boardUid]);
		if (empty($overrides)) {
			$this->configRepository->deleteOverridesForBoardUid($boardUid);
		} else {
			$this->configRepository->saveOverridesForBoardUid($boardUid, $overrides);
		}
	}

	/**
	 * Coerce a raw submitted form value into the PHP type implied by the field's schema type.
	 *
	 * @param mixed  $raw     Raw value from the request.
	 * @param string $type    One of the configSchema::TYPE_* constants.
	 * @param string $dotpath Field dot-path (used in error messages).
	 * @param array  $meta    Normalized schema metadata (supplies an int field's 'min' bound).
	 * @return mixed Coerced value.
	 * @throws InvalidArgumentException If an array field's JSON is invalid.
	 */
	private static function coerce(mixed $raw, string $type, string $dotpath, array $meta = []): mixed {
		switch ($type) {
			case configSchema::TYPE_BOOL:
				if (is_bool($raw)) {
					return $raw;
				}
				return in_array(strtolower(trim((string)$raw)), ['1', 'on', 'true', 'yes'], true);

			case configSchema::TYPE_INT:
				$int = (int)trim((string)$raw);
				// The input's min attribute is only a client-side hint, so clamp here too.
				$min = $meta['min'] ?? configSchema::DEFAULT_INT_MIN;
				return $min === null ? $int : max((int)$min, $int);

			case configSchema::TYPE_ARRAY:
				$text = trim((string)$raw);
				if ($text === '') {
					return [];
				}
				$decoded = json_decode($text, true);
				if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
					throw new InvalidArgumentException(
						"Invalid JSON for setting '{$dotpath}': " . json_last_error_msg()
					);
				}
				return $decoded;

			case configSchema::TYPE_STRING:
			case configSchema::TYPE_TEXT:
			default:
				return (string)$raw;
		}
	}

	/**
	 * Compare a coerced value against a default for override-diffing purposes.
	 *
	 * @param mixed $a First value.
	 * @param mixed $b Second value.
	 * @return bool True if the two values are considered equal.
	 */
	private static function valuesEqual(mixed $a, mixed $b): bool {
		if (is_array($a) || is_array($b)) {
			return json_encode($a) === json_encode($b);
		}
		return $a === $b;
	}

	/**
	 * Set a value at a dot-path within a nested array, creating intermediate arrays as needed.
	 *
	 * @param array  $config  Config array (modified in place).
	 * @param string $dotpath Dot-notation key (e.g. 'ModuleSettings.RENZOKU3').
	 * @param mixed  $value   Value to assign.
	 * @return void
	 */
	private static function setNested(array &$config, string $dotpath, mixed $value): void {
		$segments = explode('.', $dotpath);
		$cursor =& $config;

		foreach ($segments as $i => $segment) {
			if ($i === array_key_last($segments)) {
				$cursor[$segment] = $value;
				return;
			}
			if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
				$cursor[$segment] = [];
			}
			$cursor =& $cursor[$segment];
		}
	}
}
