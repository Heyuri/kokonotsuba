<?php

namespace Kokonotsuba\install;

/**
 * Works out where this install is being served from, so the form arrives pre-filled instead of
 * asking the user to type URLs it can already see.
 *
 * Pure: everything comes from the $_SERVER array it is handed.
 */
final class installDefaults {
	public function __construct(
		public readonly string $scheme,
		public readonly string $host,
		/** URL path the backend is served from, with both slashes: "/kokonotsuba/". */
		public readonly string $urlPrefix,
		public readonly string $appRoot
	) {}

	/** @param array<string, mixed> $server Normally $_SERVER. */
	public static function detect(array $server, string $appRoot): self {
		return new self(
			self::detectScheme($server),
			self::detectHost($server),
			self::detectUrlPrefix($server),
			rtrim($appRoot, '/')
		);
	}

	/** Absolute URL of the backend directory, e.g. "https://example.net/kokonotsuba/". */
	public function baseUrl(): string {
		return $this->scheme.'://'.$this->host.$this->urlPrefix;
	}

	/** WEBSITE_URL: the base every board URL is built on. */
	public function websiteUrl(): string {
		return $this->baseUrl().'boards/';
	}

	/**
	 * HOME: where the header's "Home" link goes. The site root is the best guess — the boards
	 * directory is rarely the front page of the site it is installed on.
	 */
	public function homeUrl(): string {
		return $this->scheme.'://'.$this->host.'/';
	}

	/** STATIC_URL: static/ sits inside the backend now, so it is served from the same prefix. */
	public function staticUrl(): string {
		return $this->baseUrl().'static/';
	}

	public function staticPath(): string {
		return $this->appRoot.'/static/';
	}

	public function boardsPath(): string {
		return $this->appRoot.'/boards/';
	}

	/** @param array<string, mixed> $server */
	private static function detectScheme(array $server): string {
		$https = strtolower((string)($server['HTTPS'] ?? ''));
		if ($https !== '' && $https !== 'off') {
			return 'https';
		}

		$forwarded = strtolower(trim((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
		if ($forwarded !== '') {
			// A proxy may send a list; the first entry is the client-facing scheme.
			$forwarded = trim(explode(',', $forwarded)[0]);
		}
		if ($forwarded === 'https') {
			return 'https';
		}

		return ((string)($server['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
	}

	/** @param array<string, mixed> $server */
	private static function detectHost(array $server): string {
		$host = (string)($server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? ''));

		// The Host header is attacker-controlled; keep only what can appear in an authority.
		$host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?? '';

		return $host !== '' ? $host : 'localhost';
	}

	/** @param array<string, mixed> $server */
	private static function detectUrlPrefix(array $server): string {
		$script = (string)($server['SCRIPT_NAME'] ?? ($server['PHP_SELF'] ?? '/install.php'));
		$directory = str_replace('\\', '/', dirname($script));

		if ($directory === '' || $directory === '.' || $directory === '/') {
			return '/';
		}

		return '/'.trim($directory, '/').'/';
	}
}
