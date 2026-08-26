<?php

/*
 * Template for databaseSettings.php, which holds the database credentials.
 *
 * install.php writes that file for you. Copy this one over it by hand only if you are setting an
 * instance up without the installer:
 *
 *     cp databaseSettings.example.php databaseSettings.php
 *
 * databaseSettings.php is not tracked by git, so updating the code never touches your credentials.
 * Table names are not configurable and live in tables.php.
 */

return [
	'DATABASE_USERNAME' => 'koko_user',
	'DATABASE_PASSWORD' => 'your_password',

	'DATABASE_DRIVER' => 'mysql',
	// Must match the host of the DB user you created: a 'user'@'localhost' grant only authorizes
	// socket connections, so use '127.0.0.1' here if the grant is on that host.
	'DATABASE_HOST' => 'localhost',
	'DATABASE_PORT' => 3306,
	'DATABASE_CHARSET' => 'utf8mb4',
	'DATABASE_NAME' => 'kokonotsuba',

	/*
	 * Secret salt for IP anonymization (anonIp module). MUST be a long, random, secret value
	 * before relying on anonymization, otherwise the truncated hashes are trivially
	 * brute-forceable. Keep it secret and out of the database.
	 * Generate one with: php -r 'echo bin2hex(random_bytes(32));'
	 */
	'ANON_IP_SALT' => '',
];
