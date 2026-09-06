<?php

namespace Kokonotsuba\install;

/**
 * Turns a driver error into something a person can act on.
 *
 * MariaDB's own wording is accurate but says nothing about what to do, and "SQLSTATE[HY000]
 * [1049]" is the single most common way an install stalls.
 */
final class databaseErrorAdvice {
	public function __construct(
		public readonly string $message,
		public readonly ?string $fix = null
	) {}

	/**
	 * @param string $error  Driver message, e.g. from PDOException::getMessage().
	 * @param string $host   Host as entered, used in the GRANT the user is told to run.
	 * @param string $user   Database user as entered.
	 * @param string $name   Database name as entered.
	 */
	public static function forError(string $error, string $host, string $user, string $name): self {
		// The user's grant is written against the host MariaDB sees the connection coming from,
		// which is 'localhost' for a unix socket and '127.0.0.1' for TCP — they are not the same
		// grant, and mixing them up is the usual cause of "access denied".
		$grantHost = $host === '' ? 'localhost' : $host;

		if (str_contains($error, '[1049]') || str_contains($error, 'Unknown database')) {
			return new self(
				"The database \"{$name}\" does not exist. Create it, then install again.",
				"sudo mariadb -e \"CREATE DATABASE \\`{$name}\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\""
			);
		}

		// 1044 is checked first: its message also starts with "Access denied for user".
		if (str_contains($error, '[1044]')) {
			return new self(
				"The user \"{$user}\" exists but has no privileges on \"{$name}\".",
				"sudo mariadb -e \"GRANT ALL PRIVILEGES ON \\`{$name}\\`.* TO '{$user}'@'{$grantHost}'; FLUSH PRIVILEGES;\""
			);
		}

		if (str_contains($error, '[1045]') || str_contains($error, 'Access denied for user')) {
			return new self(
				"MariaDB refused the username or password for \"{$user}\". Note that a grant for "
					."'{$user}'@'localhost' does not cover a connection to 127.0.0.1, and vice versa.",
				"sudo mariadb -e \"CREATE USER IF NOT EXISTS '{$user}'@'{$grantHost}' IDENTIFIED BY 'your_password'; "
					."GRANT ALL PRIVILEGES ON \\`{$name}\\`.* TO '{$user}'@'{$grantHost}'; FLUSH PRIVILEGES;\""
			);
		}

		if (str_contains($error, '[2002]') || str_contains($error, 'Connection refused')) {
			return new self(
				"Nothing answered at {$host}. Either the server is not running, or it is not listening where you pointed it.",
				'sudo systemctl status mariadb'
			);
		}

		if (str_contains($error, '[2005]') || str_contains($error, 'Unknown MySQL server host')) {
			return new self("The host \"{$host}\" could not be resolved. Use localhost or 127.0.0.1 for a database on this machine.");
		}

		if (str_contains($error, 'could not find driver')) {
			return new self(
				'PHP has no MariaDB/MySQL driver loaded.',
				'sudo apt install php-mysql && sudo systemctl restart '.systemRequirements::fpmServiceName()
			);
		}

		return new self($error);
	}
}
