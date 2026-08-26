<?php

namespace Kokonotsuba\install;

/**
 * The user and group PHP is running as, used to build the chown/chmod commands shown when a
 * directory is not writable.
 *
 * Names come from posix_* when the extension is present; without it the numeric ids are used,
 * which chown accepts just as well.
 */
final class processIdentity {
	public function __construct(
		public readonly string $user,
		public readonly string $group
	) {}

	public static function current(): self {
		return new self(self::resolveUser(), self::resolveGroup());
	}

	/** "user:group", the argument chown takes. */
	public function ownership(): string {
		return $this->user.':'.$this->group;
	}

	private static function resolveUser(): string {
		if (function_exists('posix_geteuid')) {
			$uid = posix_geteuid();
			$entry = function_exists('posix_getpwuid') ? posix_getpwuid($uid) : false;

			return is_array($entry) && isset($entry['name']) ? (string)$entry['name'] : (string)$uid;
		}

		$name = get_current_user();

		return $name !== '' ? $name : 'www-data';
	}

	private static function resolveGroup(): string {
		if (function_exists('posix_getegid')) {
			$gid = posix_getegid();
			$entry = function_exists('posix_getgrgid') ? posix_getgrgid($gid) : false;

			return is_array($entry) && isset($entry['name']) ? (string)$entry['name'] : (string)$gid;
		}

		return self::resolveUser();
	}
}
