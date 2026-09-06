<?php

namespace Kokonotsuba\install;

/**
 * PHP version, extension, binary and php.ini checks.
 *
 * Every probe is injectable so the whole thing can be exercised without touching the host it
 * runs on.
 */
final class systemRequirements {
	public const GROUP = 'PHP & system';

	/**
	 * The PHP minor series this release is tested against, both ends inclusive.
	 *
	 * Series, not full versions: '8.5' covers every 8.5.x. Moving the range is these two lines
	 * and nothing else - the boundaries compared against and the wording reported are derived
	 * from them, and the tests are written against them rather than against literal versions.
	 */
	public const TESTED_PHP_MIN = '8.3';
	public const TESTED_PHP_MAX = '8.5';

	/**
	 * Below this the installer refuses to run instead of warning.
	 *
	 * Deliberately older than TESTED_PHP_MIN: a version we no longer test on is not one we have
	 * broken, so it warns and installs rather than being locked out.
	 */
	public const MIN_PHP_VERSION = '8.1.0';

	/** Extensions without which the board cannot run. */
	private const REQUIRED_EXTENSIONS = [
		'mbstring' => 'multibyte text handling',
		'pdo' => 'database access',
		'pdo_mysql' => 'the MariaDB/MySQL driver',
		'gd' => 'thumbnailing',
		'bcmath' => 'post number arithmetic',
		'json' => 'config and API payloads',
	];

	/** Extensions that only cost a feature when missing. */
	private const OPTIONAL_EXTENSIONS = [
		'fileinfo' => 'upload MIME sniffing falls back to the extension',
		'posix' => 'the installer cannot name the user PHP runs as in its fix commands',
		'curl' => 'the installer falls back to file_get_contents for its web-exposure check',
	];

	/** External binaries, each responsible for one feature. */
	private const BINARIES = [
		'ffmpeg' => 'video thumbnails and dimensions',
		'exiftool' => 'stripping GPS metadata from uploads',
	];

	/** @var callable(string): bool */
	private $extensionProbe;

	/** @var callable(string): bool */
	private $binaryProbe;

	/** @var callable(string): (string|false) */
	private $iniProbe;

	public function __construct(
		private readonly string $phpVersion = PHP_VERSION,
		?callable $extensionProbe = null,
		?callable $binaryProbe = null,
		?callable $iniProbe = null
	) {
		$this->extensionProbe = $extensionProbe ?? static fn (string $name): bool => extension_loaded($name);
		$this->binaryProbe = $binaryProbe ?? static fn (string $name): bool => self::binaryExists($name);
		$this->iniProbe = $iniProbe ?? static fn (string $key) => ini_get($key);
	}

	/** @return list<checkResult> */
	public function check(): array {
		return array_merge(
			[$this->checkPhpVersion()],
			$this->checkExtensions(),
			$this->checkBinaries(),
			$this->checkIniSettings()
		);
	}

	private function checkPhpVersion(): checkResult {
		$label = 'PHP '.$this->phpVersion;

		if (version_compare($this->phpVersion, self::MIN_PHP_VERSION, '<')) {
			return checkResult::fail(
				self::GROUP,
				$label,
				'Kokonotsuba needs PHP '.self::MIN_PHP_VERSION.' or newer.'
			);
		}

		if (version_compare($this->phpVersion, self::TESTED_PHP_MIN.'.0', '<')) {
			return checkResult::warn(
				self::GROUP,
				$label,
				'Older than the tested range ('.self::testedRangeLabel().'). It should still work;'
					.' nothing is verified below '.self::TESTED_PHP_MIN.'.'
			);
		}

		if (version_compare($this->phpVersion, self::untestedFromVersion(), '>=')) {
			return checkResult::warn(
				self::GROUP,
				$label,
				'Newer than the tested range ('.self::testedRangeLabel().'). It may work;'
					.' nothing is verified above '.self::TESTED_PHP_MAX.'.'
			);
		}

		return checkResult::ok(self::GROUP, $label, 'Within the tested range ('.self::testedRangeLabel().').');
	}

	/**
	 * The first version past the tested range: the series after TESTED_PHP_MAX.
	 *
	 * A bare series compares as lower than any release in it ('8.5' < '8.5.7'), so the ceiling
	 * has to be the next series' .0 rather than the max itself.
	 */
	public static function untestedFromVersion(): string {
		[$major, $minor] = array_pad(explode('.', self::TESTED_PHP_MAX), 2, '0');

		return $major.'.'.((int)$minor + 1).'.0';
	}

	/** The tested range as it is written in the report, e.g. "8.3 to 8.5". */
	public static function testedRangeLabel(): string {
		return self::TESTED_PHP_MIN.' to '.self::TESTED_PHP_MAX;
	}

	/** @return list<checkResult> */
	private function checkExtensions(): array {
		$results = [];

		foreach (self::REQUIRED_EXTENSIONS as $extension => $purpose) {
			$results[] = ($this->extensionProbe)($extension)
				? checkResult::ok(self::GROUP, "Extension {$extension}", $purpose)
				: checkResult::fail(
					self::GROUP,
					"Extension {$extension}",
					"Required for {$purpose}.",
					'sudo apt install php-'.self::packageSuffix($extension).' && sudo systemctl restart '.self::fpmServiceName($this->phpVersion)
				);
		}

		foreach (self::OPTIONAL_EXTENSIONS as $extension => $consequence) {
			$results[] = ($this->extensionProbe)($extension)
				? checkResult::ok(self::GROUP, "Extension {$extension}", 'Present.')
				: checkResult::warn(self::GROUP, "Extension {$extension}", "Not loaded: {$consequence}.");
		}

		return $results;
	}

	/** @return list<checkResult> */
	private function checkBinaries(): array {
		$results = [];

		foreach (self::BINARIES as $binary => $purpose) {
			$results[] = ($this->binaryProbe)($binary)
				? checkResult::ok(self::GROUP, "Command {$binary}", $purpose)
				: checkResult::warn(
					self::GROUP,
					"Command {$binary}",
					"Not on PATH. Without it: {$purpose} will not work.",
					'sudo apt install '.($binary === 'exiftool' ? 'libimage-exiftool-perl' : $binary)
				);
		}

		return $results;
	}

	/** @return list<checkResult> */
	private function checkIniSettings(): array {
		$results = [];

		$fileUploads = (string)(($this->iniProbe)('file_uploads') ?: '');
		$results[] = ($fileUploads === '1' || strtolower($fileUploads) === 'on')
			? checkResult::ok(self::GROUP, 'file_uploads', 'Enabled.')
			: checkResult::fail(
				self::GROUP,
				'file_uploads',
				'Disabled in php.ini, so nothing can be posted with an attachment.',
				'sudo sed -i "s/^file_uploads.*/file_uploads = On/" $(php -i | grep "Loaded Configuration File" | cut -d" " -f5)'
			);

		$uploadMax = self::parseByteSize((string)(($this->iniProbe)('upload_max_filesize') ?: '0'));
		$postMax = self::parseByteSize((string)(($this->iniProbe)('post_max_size') ?: '0'));

		// post_max_size caps the whole request body, so an upload limit above it is unreachable.
		if ($postMax > 0 && $uploadMax > $postMax) {
			$results[] = checkResult::warn(
				self::GROUP,
				'upload_max_filesize / post_max_size',
				self::formatBytes($uploadMax).' / '.self::formatBytes($postMax)
					.' — post_max_size is the real ceiling, so uploads stop at the smaller value.'
			);
		} else {
			$results[] = checkResult::ok(
				self::GROUP,
				'upload_max_filesize / post_max_size',
				self::formatBytes($uploadMax).' / '.self::formatBytes($postMax)
			);
		}

		$memoryLimit = (string)(($this->iniProbe)('memory_limit') ?: '');
		$memoryBytes = self::parseByteSize($memoryLimit);

		// -1 parses to 0 here and means unlimited, which is fine.
		if ($memoryLimit !== '-1' && $memoryBytes > 0 && $memoryBytes < 128 * 1024 * 1024) {
			$results[] = checkResult::warn(
				self::GROUP,
				'memory_limit',
				$memoryLimit.' — thumbnailing large images can exceed this. 128M or more is recommended.'
			);
		} else {
			$results[] = checkResult::ok(self::GROUP, 'memory_limit', $memoryLimit === '-1' ? 'unlimited' : $memoryLimit);
		}

		return $results;
	}

	/** Parse a php.ini size ("8M", "512K", "1G", "1024") into bytes. */
	public static function parseByteSize(string $value): int {
		$value = trim($value);
		if ($value === '' || !preg_match('/^(\d+(?:\.\d+)?)\s*([kmgt]?)b?$/i', $value, $matches)) {
			return 0;
		}

		$number = (float)$matches[1];
		$multiplier = match (strtolower($matches[2])) {
			'k' => 1024,
			'm' => 1024 ** 2,
			'g' => 1024 ** 3,
			't' => 1024 ** 4,
			default => 1,
		};

		return (int)($number * $multiplier);
	}

	private static function formatBytes(int $bytes): string {
		if ($bytes <= 0) {
			return 'unset';
		}
		if ($bytes >= 1024 ** 3) {
			return round($bytes / (1024 ** 3), 1).'G';
		}
		if ($bytes >= 1024 ** 2) {
			return round($bytes / (1024 ** 2)).'M';
		}

		return round($bytes / 1024).'K';
	}

	/** "php8.3-fpm": Debian names the service after the PHP minor version. */
	public static function fpmServiceName(string $phpVersion = PHP_VERSION): string {
		$parts = explode('.', $phpVersion);

		return 'php'.($parts[0] ?? '8').'.'.($parts[1] ?? '3').'-fpm';
	}

	/** php-mbstring, php-mysql, … — the Debian package name for an extension. */
	private static function packageSuffix(string $extension): string {
		return match ($extension) {
			'pdo', 'pdo_mysql' => 'mysql',
			'json' => 'json',
			default => $extension,
		};
	}

	/** Whether a binary is on PATH, when the SAPI is allowed to ask at all. */
	private static function binaryExists(string $binary): bool {
		if (!function_exists('exec')) {
			return false;
		}

		$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
		if (in_array('exec', $disabled, true)) {
			return false;
		}

		$output = [];
		$status = 1;
		@exec('command -v '.escapeshellarg($binary).' 2>/dev/null', $output, $status);

		return $status === 0 && $output !== [];
	}
}
