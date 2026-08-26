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

	/** Root-level PHP files that are includes or credentials, never entry points. */
	public const DENIED_FILES = [
		'autoload.php',
		'databaseSettings.php',
		'databaseSettings.example.php',
		'koko.php',
		'paths.php',
		'tables.php',
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
		];
	}

	/**
	 * nginx location blocks for this install.
	 *
	 * install.php is deliberately left reachable — it is deleted after the install instead.
	 *
	 * @param string $urlPrefix URL path the backend is served from, e.g. "/kokonotsuba/".
	 */
	public static function nginxSnippet(string $urlPrefix): string {
		$prefix = '/'.trim($urlPrefix, '/');
		$prefix = $prefix === '/' ? '' : $prefix;

		$directories = implode('|', self::DENIED_DIRECTORIES);
		$files = implode('|', array_map(
			static fn (string $file): string => preg_quote($file, '/'),
			self::DENIED_FILES
		));

		return <<<NGINX
		# Kokonotsuba: keep the backend out of reach of browsers.
		location ~ ^{$prefix}/({$directories})/ {
		    deny all;
		}

		location ~ ^{$prefix}/({$files})$ {
		    deny all;
		}

		# Dotfiles: .installed, .backend, .git, .gitignore
		location ~ ^{$prefix}/\. {
		    deny all;
		}
		NGINX;
	}

	/** The Apache equivalent, for reference — the shipped .htaccess files already do this. */
	public static function apacheSnippet(): string {
		return <<<APACHE
		<IfModule mod_authz_core.c>
		    Require all denied
		</IfModule>
		<IfModule !mod_authz_core.c>
		    Order allow,deny
		    Deny from all
		</IfModule>
		APACHE;
	}
}
