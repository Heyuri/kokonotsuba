<?php

namespace Kokonotsuba\ip;

/**
 * Salted one-way hashing of stored IP addresses, and the test for whether a stored value has
 * already been through it.
 *
 * The salt lives in databaseSettings.php (ANON_IP_SALT) and never in the database: a 16-hex
 * truncation of an unsalted SHA-512 over the IPv4 space is brute-forceable in seconds, so an
 * unsalted hash is not anonymization.
 *
 * The anonIp module rewrites IP columns in place, so a row filed before anonymization holds the
 * raw address and the same row afterwards holds the hash. Any lookup keyed on an address has to
 * accept both forms, which is what storedForms() is for.
 */
final class ipAnonymizer {
	/** Digest characters kept. Must match the LEFT(..., 16) in hashColumnSql(). */
	public const HASH_LENGTH = 16;

	private static ?self $instance = null;

	public function __construct(private readonly string $salt) {}

	/** Shared instance built from the ANON_IP_SALT in databaseSettings.php. */
	public static function fromSettings(): self {
		return self::$instance ??= new self((string) (\getDatabaseSettings()['ANON_IP_SALT'] ?? ''));
	}

	/** Whether a salt is configured. Without one, nothing may be anonymized. */
	public function isConfigured(): bool {
		return $this->salt !== '';
	}

	/**
	 * The raw salt, for binding into the SQL that hashes a column in the database.
	 *
	 * @throws \RuntimeException when no salt is configured.
	 */
	public function requireSalt(): string {
		if ($this->salt === '') {
			throw new \RuntimeException(
				'ANON_IP_SALT is empty in databaseSettings.php. '
				. 'Set a long random secret before anonymizing IPs.'
			);
		}

		return $this->salt;
	}

	/**
	 * The stored form of an address once anonymized.
	 *
	 * @throws \RuntimeException when no salt is configured.
	 */
	public function hash(string $ip): string {
		return substr(hash('sha512', $this->requireSalt() . $ip), 0, self::HASH_LENGTH);
	}

	/**
	 * Every form the given address may be stored as, for a lookup that must match either.
	 * With no salt configured nothing has been anonymized, so there is no second form.
	 *
	 * @return string[]
	 */
	public function storedForms(string $ip): array {
		return $this->salt === '' ? [$ip] : [$ip, $this->hash($ip)];
	}

	/** Whether a stored value is already a hash rather than an address. */
	public static function isAnonymized(string $value): bool {
		return (bool) preg_match('/^[0-9a-f]{' . self::HASH_LENGTH . '}$/', $value);
	}

	/** SQL matching rows whose IP column has not been anonymized yet. */
	public static function notAnonymizedSql(string $column): string {
		return "{$column} NOT REGEXP '^[0-9a-f]{" . self::HASH_LENGTH . "}$'";
	}

	/** SQL producing the anonymized form of a column, salted by the bound :anon_salt. */
	public static function hashColumnSql(string $column): string {
		return "LEFT(SHA2(CONCAT(:anon_salt, {$column}), 512), " . self::HASH_LENGTH . ")";
	}
}
