<?php

namespace Kokonotsuba\install;

/**
 * The submitted install form: trimmed, validated, and turned into the two config arrays that get
 * written to disk.
 *
 * Errors are collected per field rather than thrown, so the form can be redrawn with everything
 * the user typed still in it and each problem next to the input that caused it.
 */
final class installInput {
	private const FIELDS = [
		'db_host', 'db_port', 'db_name', 'db_user', 'db_password',
		'admin_username', 'admin_password', 'admin_password_confirm',
		'board_identifier', 'board_title', 'board_sub_title',
		'website_url', 'home_url', 'static_url', 'static_path',
	];

	private const SECRET_FIELDS = ['db_password', 'admin_password', 'admin_password_confirm'];

	/** @var array<string, string> */
	private array $values;

	/** @var array<string, string> */
	private array $errors = [];

	/** @param array<string, mixed> $post */
	private function __construct(array $post) {
		$this->values = [];

		foreach (self::FIELDS as $field) {
			$raw = $post[$field] ?? '';
			$raw = is_scalar($raw) ? (string)$raw : '';
			// Passwords are taken exactly as typed; everything else loses surrounding whitespace.
			$this->values[$field] = in_array($field, self::SECRET_FIELDS, true) ? $raw : trim($raw);
		}

		$this->validate();
	}

	/** @param array<string, mixed> $post */
	public static function fromArray(array $post): self {
		return new self($post);
	}

	public function value(string $field): string {
		return $this->values[$field] ?? '';
	}

	/** Everything except the passwords, for redrawing the form. */
	public function redrawValues(): array {
		return array_diff_key($this->values, array_flip(self::SECRET_FIELDS));
	}

	/** @return array<string, string> field => message */
	public function errors(): array {
		return $this->errors;
	}

	public function isValid(): bool {
		return $this->errors === [];
	}

	private function fail(string $field, string $message): void {
		// First error per field wins: it is the one closest to what the user typed.
		$this->errors[$field] ??= $message;
	}

	private function validate(): void {
		$this->validateDatabase();
		$this->validateAdmin();
		$this->validateBoard();
		$this->validateSite();
	}

	private function validateDatabase(): void {
		if ($this->value('db_host') === '') {
			$this->fail('db_host', 'Required. Usually localhost or 127.0.0.1.');
		} elseif (preg_match('/\s/', $this->value('db_host'))) {
			$this->fail('db_host', 'Cannot contain spaces.');
		}

		$port = $this->value('db_port');
		if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
			$this->fail('db_port', 'Must be a port number between 1 and 65535 (3306 for MariaDB).');
		}

		if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $this->value('db_name'))) {
			$this->fail('db_name', 'Letters, digits, underscores, dashes and $ only. Create it first with CREATE DATABASE.');
		}

		if ($this->value('db_user') === '' || mb_strlen($this->value('db_user')) > 80) {
			$this->fail('db_user', 'Required, and at most 80 characters.');
		}
	}

	private function validateAdmin(): void {
		if (!preg_match('/^[A-Za-z0-9_.-]{1,32}$/', $this->value('admin_username'))) {
			$this->fail('admin_username', 'Letters, digits, dot, dash and underscore only, up to 32 characters.');
		}

		if (mb_strlen($this->value('admin_password')) < 8) {
			$this->fail('admin_password', 'At least 8 characters. This account can do anything on the site.');
		}

		if ($this->value('admin_password') !== $this->value('admin_password_confirm')) {
			$this->fail('admin_password_confirm', 'The two passwords do not match.');
		}
	}

	private function validateBoard(): void {
		if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $this->value('board_identifier'))) {
			$this->fail('board_identifier', 'Letters, digits, dash and underscore only. It becomes the directory and URL of the board.');
		}

		if ($this->value('board_title') === '') {
			$this->fail('board_title', 'Required.');
		} elseif (mb_strlen($this->value('board_title')) > 255) {
			$this->fail('board_title', 'At most 255 characters.');
		}

		if (mb_strlen($this->value('board_sub_title')) > 255) {
			$this->fail('board_sub_title', 'At most 255 characters.');
		}
	}

	private function validateSite(): void {
		foreach (['website_url' => 'Board base URL', 'static_url' => 'Static URL'] as $field => $label) {
			$url = $this->value($field);

			if ($url === '') {
				$this->fail($field, 'Required.');
				continue;
			}

			if (!str_starts_with($url, '/') && !preg_match('#^https?://#i', $url)) {
				$this->fail($field, "{$label} must start with http://, https:// or / .");
				continue;
			}

			if (!str_ends_with($url, '/')) {
				$this->fail($field, "{$label} must end with a slash.");
			}
		}

		// The home link is checked loosely on purpose: a full URL, an absolute path and a plain
		// 'index.html' next to the board are all things people legitimately point it at.
		$home = $this->value('home_url');
		if ($home === '') {
			$this->fail('home_url', 'Required. Use / for the site root if there is nothing else to link to.');
		} elseif (preg_match('/[\s<>"]/', $home)) {
			$this->fail('home_url', 'Cannot contain spaces, quotes or angle brackets.');
		} elseif (mb_strlen($home) > 255) {
			$this->fail('home_url', 'At most 255 characters.');
		}

		$path = $this->value('static_path');
		if ($path === '' || !str_starts_with($path, '/')) {
			$this->fail('static_path', 'Must be an absolute path to the static directory.');
		} elseif (!is_dir($path)) {
			$this->fail('static_path', 'No such directory on this server.');
		} elseif (!is_readable($path)) {
			$this->fail('static_path', 'Exists but is not readable by the web server.');
		}
	}

	/**
	 * DSN for the credentials as typed, used to test them before anything is written.
	 *
	 * Built the same way databaseConnection builds it, port included, so a connection that works
	 * here works for the application too. 'localhost' goes over a unix socket and takes no port.
	 */
	public function databaseDsn(): string {
		$host = $this->value('db_host');
		$port = $host === 'localhost' ? '' : 'port='.(int)$this->value('db_port').';';

		return 'mysql:host='.$host.';'.$port.'dbname='.$this->value('db_name').';charset=utf8mb4';
	}

	/**
	 * The databaseSettings.php array.
	 *
	 * @param string|null $anonIpSalt Existing salt to keep; a new one is generated when null.
	 * @return array<string, mixed>
	 */
	public function databaseSettings(?string $anonIpSalt = null): array {
		return [
			'DATABASE_USERNAME' => $this->value('db_user'),
			'DATABASE_PASSWORD' => $this->value('db_password'),
			'DATABASE_DRIVER' => 'mysql',
			'DATABASE_HOST' => $this->value('db_host'),
			'DATABASE_PORT' => (int)$this->value('db_port'),
			'DATABASE_CHARSET' => 'utf8mb4',
			'DATABASE_NAME' => $this->value('db_name'),
			'ANON_IP_SALT' => $anonIpSalt !== null && $anonIpSalt !== '' ? $anonIpSalt : self::randomSecret(),
		];
	}

	/**
	 * The global/siteSettings.php array.
	 *
	 * @param array<string, string> $existing Values to keep from a previous install.
	 * @return array<string, mixed>
	 */
	public function siteSettings(array $existing = []): array {
		return [
			'WEBSITE_URL' => $this->value('website_url'),
			'HOME' => $this->value('home_url'),
			'STATIC_URL' => $this->value('static_url'),
			'STATIC_PATH' => rtrim($this->value('static_path'), '/').'/',
			'TRIPSALT' => $existing['TRIPSALT'] ?? self::randomSecret(),
			'IDSEED' => $existing['IDSEED'] ?? self::randomSecret(),
		];
	}

	/** 64 hex characters from the CSPRNG. */
	public static function randomSecret(): string {
		return bin2hex(random_bytes(32));
	}
}
