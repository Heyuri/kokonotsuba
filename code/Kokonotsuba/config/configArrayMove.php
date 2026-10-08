<?php

namespace Kokonotsuba\config;

/**
 * The up/down buttons on an array setting's rows, for a browser without JS.
 *
 * Each button is a submit carrying "inputKey|index|direction". The editor's JS moves rows in
 * place and never sends one; without it the click saves the form with that entry moved.
 */
final class configArrayMove {
	public const PARAMETER = 'configArrayMove';

	public const UP = 'up';
	public const DOWN = 'down';

	/**
	 * The value a row's button submits.
	 *
	 * @param string $inputKey  The field's form key.
	 * @param int    $index     Position of the row in the array.
	 * @param string $direction self::UP or self::DOWN.
	 * @return string
	 */
	public static function buttonValue(string $inputKey, int $index, string $direction): string {
		return $inputKey . '|' . $index . '|' . $direction;
	}

	/**
	 * The form key of the field a move names, or null when it names none.
	 *
	 * @param mixed $move The submitted button value.
	 * @return string|null
	 */
	public static function fieldOf(mixed $move): ?string {
		$parsed = self::parse($move);

		return $parsed === null ? null : $parsed[0];
	}

	/**
	 * The submitted config with the named entry moved one place.
	 *
	 * Anything that does not name a real entry, or would move one off either end, leaves the
	 * submission as it was.
	 *
	 * @param array $submitted inputKey => submitted value, an array setting's being its JSON.
	 * @param mixed $move      The submitted button value.
	 * @return array
	 */
	public static function apply(array $submitted, mixed $move): array {
		$parsed = self::parse($move);

		if ($parsed === null) {
			return $submitted;
		}

		[$inputKey, $index, $direction] = $parsed;
		$json = $submitted[$inputKey] ?? null;

		if (!is_string($json)) {
			return $submitted;
		}

		$decoded = json_decode($json, true);

		if (!is_array($decoded)) {
			return $submitted;
		}

		$keys = array_keys($decoded);
		$target = $direction === self::UP ? $index - 1 : $index + 1;

		if (!isset($keys[$index], $keys[$target])) {
			return $submitted;
		}

		[$keys[$index], $keys[$target]] = [$keys[$target], $keys[$index]];

		$moved = [];
		foreach ($keys as $key) {
			$moved[$key] = $decoded[$key];
		}

		// A map stays an object even when its keys happen to read as 0, 1, 2...
		$isMap = str_starts_with(ltrim($json), '{');
		$moved = $isMap ? $moved : array_values($moved);
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($isMap ? JSON_FORCE_OBJECT : 0);

		$submitted[$inputKey] = (string) json_encode($moved, $flags);

		return $submitted;
	}

	/** @return array{0: string, 1: int, 2: string}|null */
	private static function parse(mixed $move): ?array {
		if (!is_string($move) || !preg_match('/^([a-zA-Z0-9]+)\|(\d{1,9})\|(up|down)$/', $move, $m)) {
			return null;
		}

		return [$m[1], (int) $m[2], $m[3]];
	}
}
