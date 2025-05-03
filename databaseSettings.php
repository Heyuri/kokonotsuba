<?php
//database settings
return [
	'DATABASE_USERNAME' => 'user', //database user name
	'DATABASE_PASSWORD' =>'password', //database user password

	'DATABASE_DRIVER' => 'mysql',
	'DATABASE_HOST' => '127.0.0.1',
	'DATABASE_PORT' => 3306,
	'DATABASE_CHARSET' => 'utf8mb4',
	'DATABASE_NAME' => 'kokonotsuba', //the database the instance will be using

	/* Tables used by various parts of kokonotsuba, do not change them after installing */
	'POST_TABLE' => 'posts', //post table, contains all posts on an instance
	'REPORT_TABLE' => 'reports', //report table used by report module
	'BAN_TABLE' => 'bans', //ban table used by adminban module
	'BOARD_TABLE' => 'boards', //board table for all boards active on an instance
	'BOARD_PATH_CACHE_TABLE' => 'board_paths', //for caching board paths
	'POST_NUMBER_TABLE' => 'post_numbers', //used for futaba-like `No. XXXX` system
	'ACCOUNT_TABLE' => 'accounts', //staff account table
	'ACTIONLOG_TABLE' => 'actionlog', //user log
	'THREAD_TABLE' => 'threads', //holds all threads
	'THREAD_REDIRECT_TABLE' => 'redirects', //for thread redirecting
];
