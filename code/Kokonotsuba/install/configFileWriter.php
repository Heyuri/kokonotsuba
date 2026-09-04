<?php

namespace Kokonotsuba\install;

use RuntimeException;

/**
 * Writes the two generated config files (databaseSettings.php, global/siteSettings.php).
 *
 * Every write goes to a temporary file in the same directory, is parsed there to prove it is
 * valid PHP returning an array, and only then renamed into place — so a half-written config can
 * never replace a working one. An existing file is copied aside first and can be put back if a
 * later step of the install fails.
 *
 * The temporary and backup files are dotfiles: the web server rules deny those already, and
 * a copy of databaseSettings.php under another name must not be fetchable while it exists.
 */
final class configFileWriter {
	/** @var list<array{path: string, backup: ?string}> */
	private array $written = [];

	/**
	 * Render an array as a PHP config file.
	 *
	 * @param array<string, mixed>  $values   Key => value pairs, written in order.
	 * @param string                $header   Block comment placed above the return.
	 * @param array<string, string> $comments Per-key comment lines.
	 */
	public static function render(array $values, string $header = '', array $comments = []): string {
		$out = "<?php\n\n";

		if ($header !== '') {
			$out .= "/*\n";
			foreach (explode("\n", trim($header)) as $line) {
				$out .= rtrim(' * '.$line)."\n";
			}
			$out .= " */\n\n";
		}

		$out .= "return [\n";

		foreach ($values as $key => $value) {
			if (isset($comments[$key])) {
				foreach (explode("\n", trim($comments[$key])) as $line) {
					$out .= rtrim("\t// ".$line)."\n";
				}
			}
			$out .= "\t".var_export((string)$key, true).' => '.self::exportValue($value).",\n";
		}

		return $out."];\n";
	}

	private static function exportValue(mixed $value): string {
		if (is_array($value)) {
			// Config values written by the installer are flat; nested arrays are exported as-is.
			return preg_replace('/\n\s*/', ' ', var_export($value, true)) ?? var_export($value, true);
		}

		return var_export($value, true);
	}

	/**
	 * Write $contents to $path atomically, backing up whatever was there.
	 *
	 * @param int $mode Permissions for the new file (0640 for anything holding a secret).
	 * @return string|null Path of the backup, or null when there was nothing to back up.
	 */
	public function write(string $path, string $contents, int $mode = 0640): ?string {
		$directory = dirname($path);

		if (!is_dir($directory) || !is_writable($directory)) {
			throw new RuntimeException("Cannot write {$path}: {$directory} is not writable by the web server.");
		}

		$temporary = self::sidecarPath($path, '.tmp-'.bin2hex(random_bytes(4)));

		if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
			@unlink($temporary);
			throw new RuntimeException("Failed to write {$temporary}.");
		}

		self::assertValidConfigFile($temporary, $path);

		@chmod($temporary, $mode);

		$backup = null;
		if (file_exists($path)) {
			$backup = self::sidecarPath($path, '.bak-'.date('YmdHis'));
			if (!@copy($path, $backup)) {
				@unlink($temporary);
				throw new RuntimeException("Failed to back up the existing {$path}.");
			}
		}

		if (!@rename($temporary, $path)) {
			@unlink($temporary);
			throw new RuntimeException("Failed to move the new config into place at {$path}.");
		}

		$this->written[] = ['path' => $path, 'backup' => $backup];

		return $backup;
	}

	/** Put every file this writer replaced back the way it was. */
	public function rollback(): void {
		foreach (array_reverse($this->written) as $entry) {
			if ($entry['backup'] !== null && file_exists($entry['backup'])) {
				@rename($entry['backup'], $entry['path']);
				continue;
			}

			@unlink($entry['path']);
		}

		$this->written = [];
	}

	/** Drop the backups once the install has succeeded. */
	public function discardBackups(): void {
		foreach ($this->written as $entry) {
			if ($entry['backup'] !== null && file_exists($entry['backup'])) {
				@unlink($entry['backup']);
			}
		}

		$this->written = [];
	}

	/** "/dir/settings.php" + ".tmp-1a2b" => "/dir/.settings.php.tmp-1a2b". */
	public static function sidecarPath(string $path, string $suffix): string {
		return dirname($path).'/.'.basename($path).$suffix;
	}

	/** Parse the freshly written file to be sure it is loadable before it replaces anything. */
	private static function assertValidConfigFile(string $temporary, string $destination): void {
		try {
			$parsed = include $temporary;
		} catch (\Throwable $e) {
			@unlink($temporary);
			throw new RuntimeException("Generated config for {$destination} is not valid PHP: {$e->getMessage()}");
		}

		if (!is_array($parsed)) {
			@unlink($temporary);
			throw new RuntimeException("Generated config for {$destination} did not return an array.");
		}
	}
}
