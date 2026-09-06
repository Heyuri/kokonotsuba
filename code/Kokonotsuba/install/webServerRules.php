<?php

namespace Kokonotsuba\install;

/**
 * What must not be reachable over HTTP now that the backend lives inside the web root, and the
 * server config that denies it.
 *
 * Apache is covered by the .htaccess files shipped in the tree; nginx ignores those, so the
 * installer prints the equivalent location blocks with the install's own URL prefix filled in.
 */
final class webServerRules {
	/** Directories that hold nothing a browser should ever fetch. */
	public const DENIED_DIRECTORIES = [
		'bootstrap',
		'code',
		'configs',
		'global',
		'migrations',
		'module',
		'templates',
		'tests',
		'Utilities',
	];

	/** Root-level files that are includes, credentials or docs, never entry points. */
	public const DENIED_FILES = [
		'autoload.php',
		'databaseSettings.php',
		'databaseSettings.example.php',
		'koko.php',
		'paths.php',
		'tables.php',
		'README.md',
		'LICENSE',
	];

	/**
	 * Paths probed over HTTP to prove the rules are in place. Each is [relative path, marker];
	 * a response containing the marker means the file was served as source.
	 *
	 * @return list<array{path: string, marker: string}>
	 */
	public static function probeTargets(): array {
		return [
			['path' => 'databaseSettings.php', 'marker' => 'DATABASE_USERNAME'],
			['path' => 'tables.php', 'marker' => 'SCHEMA_MIGRATION_TABLE'],
			['path' => 'global/globalconfig.php', 'marker' => 'TRIPSALT'],
			['path' => 'global/globalmsg.txt', 'marker' => ''],
			['path' => 'code/Kokonotsuba/constants.php', 'marker' => 'KOKO_VERSION'],
			['path' => 'README.md', 'marker' => 'Kokonotsuba'],
		];
	}

	/**
	 * nginx location blocks for this install.
	 *
	 * @param string $urlPrefix URL path the backend is served from, e.g. "/kokonotsuba/".
	 */
	public static function nginxSnippet(string $urlPrefix): string {
		$prefix = '/'.trim($urlPrefix, '/');
		$prefix = $prefix === '/' ? '' : preg_quote($prefix);

		$directories = implode('|', self::DENIED_DIRECTORIES);
		$files = implode('|', array_map(
			static fn (string $file): string => preg_quote($file, '/'),
			self::DENIED_FILES
		));

		return <<<NGINX
		# Kokonotsuba: keep the backend out of reach. These go ABOVE the "location ~ \.php$"
		# block, or that block hands the denied .php files to PHP-FPM first.
		location ~ ^{$prefix}/({$directories})/ {
		    deny all;
		}

		location ~ ^{$prefix}/({$files})(/|$) {
		    deny all;
		}

		# Dotfiles at any depth, and every board's boardUID.ini
		location ~ ^{$prefix}/((.*/)?\.|.*\.ini$) {
		    deny all;
		}
		NGINX;
	}

	/**
	 * What Apache needs before the shipped .htaccess files do anything: Debian's default
	 * <Directory /var/www/> sets AllowOverride None, which ignores them all.
	 *
	 * @param string $appRoot Filesystem path of the install, e.g. "/var/www/html/kokonotsuba".
	 */
	public static function apacheSnippet(string $appRoot): string {
		$path = rtrim($appRoot, '/');

		return <<<APACHE
		<Directory "{$path}">
		    AllowOverride All
		</Directory>
		APACHE;
	}
}
