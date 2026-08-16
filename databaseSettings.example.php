<?php

/*
 * Copy to databaseSettings.php and fill in. That file is not tracked by git, so it survives
 * updates and never carries real credentials into the repository.
 *
 * Table names are not here — they live in tables.php, which is tracked.
 */

return [
	'DATABASE_USERNAME' => 'user',
	'DATABASE_PASSWORD' => 'password',

	'DATABASE_DRIVER' => 'mysql',
	// Must match the host of the DB user you created (e.g. a 'user'@'localhost' grant
	// only authorizes socket connections; use '127.0.0.1' if the grant is on that host).
	'DATABASE_HOST' => 'localhost',
	'DATABASE_PORT' => 3306,
	'DATABASE_CHARSET' => 'utf8mb4',
	'DATABASE_NAME' => 'kokonotsuba',

	/*
	 * Secret salt for IP anonymization (anonIp module). MUST be changed to a long,
	 * random, secret value before relying on anonymization, otherwise the truncated
	 * hashes are trivially brute-forceable. Keep this secret
	 * and out of the database. Generate one with: bin2hex(random_bytes(32))
	 */
	'ANON_IP_SALT' => '',
];
