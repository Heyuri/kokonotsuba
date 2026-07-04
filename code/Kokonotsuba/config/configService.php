<?php

namespace Kokonotsuba\config;

use InvalidArgumentException;

/**
 * Resolves and persists per-board configuration.
 *
 * The effective config for a board is built in three layers, later layers winning:
 *   1. global/globalconfig.php  — board-immutable globals and computed defaults (never editable).
 *   2. global/configs/*.php     — the editable schema's default values (see configSchema).
 *   3. board_configs.conf_values — this board's stored overrides (only values differing from #2).
 *
 * Saving compares each submitted value against the schema default and stores only the
 * differences, so a field set back to its default is automatically "reset" (dropped).
 */
class configService {
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
		$stored = $this->configRepository->getOverridesByBoardUid($boardUid);

		$overrides = [];
		foreach ($stored as $dotpath => $value) {
			if (configSchema::hasField((string)$dotpath)) {
				$overrides[$dotpath] = $value;
			}
		}

		return $overrides;
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
	 * @param int $boardUid Board UID (0/GLOBAL_BOARD resolves to schema defaults only).
	 * @return array The complete $config array, backward-compatible with the legacy cascade.
	 */
	public function getEffectiveConfig(int $boardUid): array {
		// Layers 1 & 2: globals + schema defaults.
		$config = self::resolveDefaults();

		// Layer 3: this board's overrides.
		foreach ($this->getOverrides($boardUid) as $dotpath => $value) {
			self::setNested($config, (string)$dotpath, $value);
		}

		return $config;
	}

	/**
	 * Coerce, diff against defaults, and persist a submitted set of config values.
	 *
	 * @param int   $boardUid   Board UID.
	 * @param array $rawSubmitted Raw submitted map keyed by camelCase input key (see
	 *                            configSchema::inputKey()). Missing bool fields are treated as
	 *                            unchecked (false); other missing fields are left untouched.
	 * @return void
	 * @throws InvalidArgumentException If an array-typed field contains invalid JSON.
	 */
	public function saveOverrides(int $boardUid, array $rawSubmitted): void {
		$overrides = [];

		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			$type = $meta['type'];
			$default = $meta['default'];
			$inputKey = configSchema::inputKey((string)$dotpath);

			// Checkboxes only submit when checked; absence means false.
			if ($type === configSchema::TYPE_BOOL) {
				$value = array_key_exists($inputKey, $rawSubmitted)
					&& self::coerce($rawSubmitted[$inputKey], configSchema::TYPE_BOOL, (string)$dotpath);
			} else {
				// Leave unsubmitted non-bool fields alone (don't force them to default).
				if (!array_key_exists($inputKey, $rawSubmitted)) {
					continue;
				}
				$value = self::coerce($rawSubmitted[$inputKey], $type, (string)$dotpath);
			}

			// Store only values that differ from the schema default.
			if (!self::valuesEqual($value, $default)) {
				$overrides[$dotpath] = $value;
			}
		}

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
	 * @return mixed Coerced value.
	 * @throws InvalidArgumentException If an array field's JSON is invalid.
	 */
	private static function coerce(mixed $raw, string $type, string $dotpath): mixed {
		switch ($type) {
			case configSchema::TYPE_BOOL:
				if (is_bool($raw)) {
					return $raw;
				}
				return in_array(strtolower(trim((string)$raw)), ['1', 'on', 'true', 'yes'], true);

			case configSchema::TYPE_INT:
				return (int)trim((string)$raw);

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
