<?php

namespace Kokonotsuba\config;

use Throwable;

/**
 * The install-time global values, kept in global/siteSettings.php.
 *
 * globalconfig.php is tracked by git and carries the defaults for every install; the handful of
 * values that are specific to one site (its URLs, its salts) live in this generated file instead,
 * so updating the code never conflicts with them. The file is optional: without it globalconfig's
 * own values stand, which is what an install predating it has.
 *
 * Only the keys below are honoured. Anything else in the file is ignored rather than being given
 * the power to redefine, say, the role/permission map.
 */
final class siteSettings {
	public const KEYS = [
		'WEBSITE_URL',
		'HOME',
		'STATIC_URL',
		'STATIC_PATH',
		'TRIPSALT',
		'IDSEED',
		'USE_CDN',
		'CDN_DIR',
		'CDN_URL',
		'PIXMICAT_LANGUAGE',
	];

	/**
	 * Values that address a directory or URL prefix and so must end in a slash.
	 *
	 * HOME is not one of them: it is a link target, and 'index.html' is a valid value for it.
	 */
	private const SLASH_TERMINATED = ['WEBSITE_URL', 'STATIC_URL', 'STATIC_PATH', 'CDN_DIR', 'CDN_URL'];

	/** @var array<string, array<string, mixed>> */
	private static array $cache = [];

	/**
	 * Load and normalise the overrides.
	 *
	 * @return array<string, mixed> Empty when the file is absent or unusable.
	 */
	public static function load(string $path): array {
		if (isset(self::$cache[$path])) {
			return self::$cache[$path];
		}

		if (!is_file($path) || !is_readable($path)) {
			return self::$cache[$path] = [];
		}

		try {
			$values = include $path;
		} catch (Throwable $e) {
			error_log('siteSettings: could not load '.$path.': '.$e->getMessage());

			return self::$cache[$path] = [];
		}

		if (!is_array($values)) {
			error_log('siteSettings: '.$path.' did not return an array.');

			return self::$cache[$path] = [];
		}

		return self::$cache[$path] = self::normalize($values);
	}

	/**
	 * Keep the known keys, drop empty ones, and put the trailing slashes back.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public static function normalize(array $values): array {
		$normalized = [];

		foreach (self::KEYS as $key) {
			if (!array_key_exists($key, $values)) {
				continue;
			}

			$value = $values[$key];

			if ($key === 'USE_CDN') {
				$normalized[$key] = (bool)$value;
				continue;
			}

			if (!is_scalar($value)) {
				continue;
			}

			$value = trim((string)$value);
			// An unset value in the generated file means "leave globalconfig's default alone".
			if ($value === '') {
				continue;
			}

			if (in_array($key, self::SLASH_TERMINATED, true)) {
				$value = rtrim($value, '/').'/';
			}

			$normalized[$key] = $value;
		}

		return $normalized;
	}

	/** Forget the cached read, for tests and for the installer writing the file mid-request. */
	public static function forget(?string $path = null): void {
		if ($path === null) {
			self::$cache = [];

			return;
		}

		unset(self::$cache[$path]);
	}
}
