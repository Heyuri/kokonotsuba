<?php

namespace Kokonotsuba\install;

/**
 * Checks every directory the install touches and, where access is missing, produces the exact
 * command that grants it.
 *
 * Ownership in the fix commands is the user PHP itself runs as: whatever the shell user owns,
 * the board is only ever served by the web server's process.
 */
final class pathRequirements {
	public const GROUP = 'Directories & permissions';

	public function __construct(
		private readonly string $appRoot,
		private readonly processIdentity $identity
	) {}

	public static function forAppRoot(string $appRoot, ?processIdentity $identity = null): self {
		return new self(rtrim($appRoot, '/'), $identity ?? processIdentity::current());
	}

	/**
	 * The directory map, in the order it is shown.
	 *
	 * @return list<pathRequirement>
	 */
	public function requirements(): array {
		return [
			new pathRequirement($this->appRoot, '.', 'the install writes databaseSettings.php here', true),
			new pathRequirement($this->appRoot.'/global', 'global/', 'site settings, error log, blotter and global message', true),
			new pathRequirement($this->appRoot.'/global/board-storages', 'global/board-storages/', 'per-board storage directories', true, true),
			new pathRequirement($this->appRoot.'/boards', 'boards/', 'the board directories themselves', true, true),
			new pathRequirement($this->appRoot.'/static', 'static/', 'css, js and images served to browsers', false),
			new pathRequirement($this->appRoot.'/code', 'code/', 'the application itself', false),
			new pathRequirement($this->appRoot.'/module', 'module/', 'modules', false),
			new pathRequirement($this->appRoot.'/configs', 'configs/', 'board configuration schema', false),
			new pathRequirement($this->appRoot.'/templates', 'templates/', 'page templates', false),
			new pathRequirement($this->appRoot.'/migrations', 'migrations/', 'database migrations', false),
		];
	}

	/** @return list<checkResult> */
	public function check(): array {
		return array_map(fn (pathRequirement $requirement): checkResult => $this->checkOne($requirement), $this->requirements());
	}

	public function checkOne(pathRequirement $requirement): checkResult {
		$path = $requirement->path;
		$label = $requirement->label;
		$access = $requirement->needsWrite ? 'read + write' : 'read';

		if (!file_exists($path)) {
			if ($requirement->createIfMissing && is_dir(dirname($path)) && is_writable(dirname($path))) {
				return checkResult::ok(self::GROUP, $label, 'Missing; the installer will create it.');
			}

			return checkResult::fail(
				self::GROUP,
				$label,
				"Does not exist. Needed for {$requirement->purpose}.",
				$this->createCommand($requirement)
			);
		}

		if (!is_dir($path)) {
			return checkResult::fail(self::GROUP, $label, 'Exists but is not a directory.');
		}

		if (!is_readable($path)) {
			return checkResult::fail(
				self::GROUP,
				$label,
				"Not readable by ".$this->identity->user.". Needed for {$requirement->purpose}.",
				$this->permissionCommand($requirement)
			);
		}

		if ($requirement->needsWrite && !is_writable($path)) {
			return checkResult::fail(
				self::GROUP,
				$label,
				"Not writable by ".$this->identity->user.". Needed for {$requirement->purpose}.",
				$this->permissionCommand($requirement)
			);
		}

		return checkResult::ok(self::GROUP, $label, $this->describe($path).' — '.$access.' ok');
	}

	/** mkdir + ownership, for a directory that is not there at all. */
	public function createCommand(pathRequirement $requirement): string {
		return 'sudo mkdir -p '.escapeshellarg($requirement->path)
			.' && '.$this->permissionCommand($requirement);
	}

	/** chown to the web server identity, then the mode that grants the access this path needs. */
	public function permissionCommand(pathRequirement $requirement): string {
		$mode = $requirement->needsWrite ? '770' : '750';

		return 'sudo chown -R '.$this->identity->ownership().' '.escapeshellarg($requirement->path)
			.' && sudo chmod -R '.$mode.' '.escapeshellarg($requirement->path);
	}

	/** "drwxrwx--- 0770 www-data:www-data" for the report line. */
	private function describe(string $path): string {
		$perms = @fileperms($path);
		if ($perms === false) {
			return 'unknown mode';
		}

		$owner = self::nameOfUser(@fileowner($path));
		$group = self::nameOfGroup(@filegroup($path));

		return sprintf('%04o %s:%s', $perms & 0777, $owner, $group);
	}

	private static function nameOfUser(int|false $uid): string {
		if ($uid === false) {
			return '?';
		}
		if (function_exists('posix_getpwuid')) {
			$entry = posix_getpwuid($uid);
			if (is_array($entry) && isset($entry['name'])) {
				return (string)$entry['name'];
			}
		}

		return (string)$uid;
	}

	private static function nameOfGroup(int|false $gid): string {
		if ($gid === false) {
			return '?';
		}
		if (function_exists('posix_getgrgid')) {
			$entry = posix_getgrgid($gid);
			if (is_array($entry) && isset($entry['name'])) {
				return (string)$entry['name'];
			}
		}

		return (string)$gid;
	}
}
