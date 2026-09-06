<?php

/*
 * Canonical table names.
 *
 * Not configurable: these are part of the schema, and the migration ledger records changes
 * against them. Renaming one here on a live install orphans its data. New tables are added
 * by whichever migration creates them, so this file arrives by git pull (or in a release
 * tarball) alongside that migration.
 *
 * Credentials live in databaseSettings.php, which is not tracked.
 */

return [
	'SCHEMA_MIGRATION_TABLE' => 'schema_migrations', // applied-migration ledger

	'POST_TABLE' => 'posts',
	'FILE_TABLE' => 'files',
	'QUOTE_LINK_TABLE' => 'quote_links',
	'THREAD_TABLE' => 'threads',
	'THREAD_REDIRECT_TABLE' => 'redirects',
	'THREAD_THEMES_TABLE' => 'thread_themes',
	'DELETED_POSTS_TABLE' => 'deleted_posts',
	'POST_NUMBER_TABLE' => 'post_numbers',
	'POST_NUMBER_HISTORY_TABLE' => 'post_number_history',
	'LAST_THREAD_SUBMISSIONS_TABLE' => 'last_thread_submissions',

	'BOARD_TABLE' => 'boards',
	'BOARD_CONFIG_TABLE' => 'board_configs',
	'BOARD_PATH_CACHE_TABLE' => 'board_paths',

	'ACCOUNT_TABLE' => 'accounts',
	'LOGIN_ATTEMPT_TABLE' => 'login_attempts',
	'ACTIONLOG_TABLE' => 'actionlog',
	'CAPCODE_TABLE' => 'capcodes',
	'NOTE_TABLE' => 'notes',
	'HOST_NOTE_TABLE' => 'host_notes',
	'POST_EDIT_REVISION_TABLE' => 'post_edit_revisions',
	'PRIVATE_MESSAGE_TABLE' => 'private_messages',

	'BAN_TABLE' => 'bans',
	'BAN_APPEAL_TABLE' => 'ban_appeals',

	'REPORT_TABLE' => 'reports',
	'REPORT_READ_TABLE' => 'report_reads',
	'SPAM_STRING_TABLE' => 'spam_string_rules',
	'FILE_BAN_TABLE' => 'file_bans',
	'PERCEPTUAL_BAN_TABLE' => 'perceptual_bans',

	'ANON_IP_RUN_TABLE' => 'anon_ip_runs',

	'SOUDANE_TABLE' => 'soudane_votes',
	'COUNTRY_FLAG_TABLE' => 'country_flags',
	'DISPLAY_IP_TABLE' => 'display_ip',
	'BANNER_AD_TABLE' => 'banner_ads',
	'ADS_TABLE' => 'ads',
	'BLOTTER_TABLE' => 'blotter',
];
