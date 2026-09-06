<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\migrations\tableBlueprint;

/**
 * Every table the engine had when the migration ledger was introduced, squashed out of
 * install.php's tableCreator.
 *
 * Reconcilable: re-running this against an older database adds whatever tables, columns, indexes
 * and constraints it is missing, without touching anything already there. That is how an install
 * predating the ledger catches up, however old it is.
 */
return new class extends migration {
	public function description(): string {
		return 'Baseline schema';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function isReconcilable(): bool {
		return true;
	}

	public function up(migrationContext $ctx): void {
		$schema = $ctx->schema;

		$schema->createTable('BOARD_TABLE', function (tableBlueprint $t): void {
			$t->column('board_uid', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('board_identifier', 'TEXT DEFAULT NULL');
			$t->column('board_title', 'TEXT NOT NULL');
			$t->column('board_sub_title', 'TEXT DEFAULT NULL');
			$t->column('storage_directory_name', 'TEXT NOT NULL');
			$t->column('subdomain', 'VARCHAR(253) NOT NULL DEFAULT \'\'');
			$t->column('listed', 'TINYINT(1) DEFAULT 1');
			$t->column('date_added', 'DATE DEFAULT curdate()');
			$t->primary('board_uid');
			$t->index('date_added', ['date_added']);
		});

		$schema->createTable('THREAD_TABLE', function (tableBlueprint $t): void {
			$t->column('insert_id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('thread_uid', 'VARCHAR(255) NOT NULL');
			$t->column('post_op_number', 'INT(11) NOT NULL');
			$t->column('post_op_post_uid', 'INT(11) NOT NULL');
			$t->column('boardUID', 'INT(11) NOT NULL');
			$t->column('last_reply_time', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('last_bump_time', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('thread_created_time', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('is_sticky', 'TINYINT(1) DEFAULT 0');
			$t->primary('insert_id');
			$t->index('fk_thread_boardUID', ['boardUID']);
			$t->index('thread_uid', ['thread_uid']);
			$t->index('last_reply_time', ['last_reply_time']);
			$t->index('last_bump_time', ['last_bump_time']);
			$t->index('thread_created_time', ['thread_created_time']);
			$t->foreign('fk_thread_boardUID', 'boardUID', 'BOARD_TABLE', 'board_uid', 'CASCADE');
		});

		$schema->createTable('POST_TABLE', function (tableBlueprint $t): void {
			$t->column('post_uid', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('no', 'INT(11) NOT NULL');
			$t->column('poster_hash', 'VARCHAR(255) DEFAULT NULL');
			$t->column('boardUID', 'INT(11) NOT NULL');
			$t->column('thread_uid', 'VARCHAR(255) NOT NULL');
			$t->column('post_position', 'INT(11) DEFAULT 0');
			$t->column('is_op', 'TINYINT(1) NOT NULL');
			$t->column('root', 'TIMESTAMP NOT NULL');
			$t->column('md5chksum', 'TEXT DEFAULT NULL');
			$t->column('category', 'TEXT DEFAULT NULL');
			$t->column('pwd', 'TEXT NOT NULL');
			$t->column('now', 'TEXT NOT NULL');
			$t->column('name', 'TEXT NOT NULL');
			$t->column('tripcode', 'TEXT DEFAULT NULL');
			$t->column('secure_tripcode', 'TEXT DEFAULT NULL');
			$t->column('capcode', 'TEXT DEFAULT NULL');
			$t->column('email', 'TEXT NOT NULL');
			$t->column('sub', 'TEXT NOT NULL');
			$t->column('com', 'MEDIUMTEXT NOT NULL');
			$t->column('host', 'VARCHAR(45) NOT NULL');
			$t->column('status', 'TEXT DEFAULT NULL');
			$t->column('tag', 'VARCHAR(16) DEFAULT NULL');
			$t->primary('post_uid');
			$t->unique('uniq_board_no', ['boardUID', 'no']);
			$t->index('thread_uid', ['thread_uid']);
			$t->index('no', ['no']);
			$t->index('idx_host', ['host']);
			$t->index('idx_posts_thread_rank', ['thread_uid', 'is_op DESC', 'post_uid DESC']);
			$t->index('idx_post_root', ['root']);
			$t->index('idx_post_board_root', ['boardUID', 'root', 'no']);
			$t->index('idx_tag', ['tag']);
			$t->index('idx_tripcode', ['tripcode(10)']);
			$t->index('idx_secure_tripcode', ['secure_tripcode(10)']);
			$t->fulltext('ft_com', ['com']);
			$t->fulltext('ft_sub', ['sub']);
			$t->fulltext('ft_name', ['name']);
			$t->fulltext('ft_email', ['email']);
			$t->fulltext('ft_general', ['name', 'email', 'sub', 'com']);
			$t->foreign('fk_boardUID', 'boardUID', 'BOARD_TABLE', 'board_uid', 'CASCADE');
			$t->foreign('fk_thread_uid', 'thread_uid', 'THREAD_TABLE', 'thread_uid', 'CASCADE');
		});

		$schema->createTable('FILE_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('post_uid', 'INT(11) NOT NULL');
			$t->column('file_name', 'VARCHAR(255) NOT NULL');
			$t->column('stored_filename', 'TEXT NOT NULL');
			$t->column('file_ext', 'VARCHAR(16) NOT NULL');
			$t->column('file_md5', 'VARCHAR(32) NOT NULL');
			$t->column('file_width', 'INT(11) DEFAULT NULL');
			$t->column('file_height', 'INT(11) DEFAULT NULL');
			$t->column('thumb_file_width', 'INT(11) DEFAULT NULL');
			$t->column('thumb_file_height', 'INT(11) DEFAULT NULL');
			$t->column('file_size', 'BIGINT(20) UNSIGNED DEFAULT NULL');
			$t->column('mime_type', 'VARCHAR(255) DEFAULT NULL');
			$t->column('is_hidden', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('is_animated', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('is_spoilered', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('timestamp_added', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->index('idx_md5', ['file_md5']);
			$t->index('idx_post_uid', ['post_uid']);
			$t->index('idx_file_ext', ['file_ext']);
			$t->index('idx_file_size', ['file_size']);
			$t->index('idx_file_name_prefix', ['file_name']);
			$t->index('idx_mime_type', ['mime_type']);
			$t->index('idx_post_uid_file_md5', ['post_uid', 'file_md5']);
			$t->fulltext('ft_file_name', ['file_name']);
			$t->foreign('fk_file_post_uid', 'post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('QUOTE_LINK_TABLE', function (tableBlueprint $t): void {
			$t->column('quotelink_id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('host_post_uid', 'INT(11) NOT NULL');
			$t->column('target_post_uid', 'INT(11) NOT NULL');
			$t->primary('quotelink_id');
			$t->index('host_post_uid', ['host_post_uid']);
			$t->index('target_post_uid', ['target_post_uid']);
			$t->foreign('fk_quote_link_host_post_uid', 'host_post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
			$t->foreign('fk_quote_link_target_post_uid', 'target_post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('THREAD_REDIRECT_TABLE', function (tableBlueprint $t): void {
			$t->column('redirect_id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('original_board_uid', 'INT(11) NOT NULL');
			$t->column('new_board_uid', 'INT(11) NOT NULL');
			$t->column('post_op_number', 'INT(11) NOT NULL');
			$t->column('thread_uid', 'VARCHAR(255) NOT NULL');
			$t->primary('redirect_id');
			$t->index('new_board_uid', ['new_board_uid']);
			$t->index('original_board_uid', ['original_board_uid']);
			$t->index('thread_uid', ['thread_uid']);
			$t->foreign('new_board_uid', 'new_board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
			$t->foreign('redirect_thread_uid', 'thread_uid', 'THREAD_TABLE', 'thread_uid', 'CASCADE');
		});

		$schema->createTable('ACCOUNT_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('username', 'TEXT NOT NULL');
			$t->column('role', 'INT(11) DEFAULT 0');
			$t->column('password_hash', 'TEXT NOT NULL');
			$t->column('number_of_actions', 'INT(11) DEFAULT 0');
			$t->column('last_login', 'TIMESTAMP NULL DEFAULT NULL');
			$t->column('date_added', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->unique('username', ['username'], 'HASH');
			$t->index('last_login', ['last_login']);
			$t->index('date_added', ['date_added']);
		});

		$schema->createTable('THREAD_THEMES_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('thread_uid', 'VARCHAR(255) DEFAULT NULL');
			$t->column('background_hex_color', 'CHAR(7) DEFAULT NULL');
			$t->column('reply_background_hex_color', 'CHAR(7) DEFAULT NULL');
			$t->column('text_hex_color', 'CHAR(7) DEFAULT NULL');
			$t->column('background_image_url', 'TEXT DEFAULT NULL');
			$t->column('date_added', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('audio', 'TEXT DEFAULT NULL');
			$t->column('raw_styling', 'TEXT DEFAULT NULL');
			$t->column('added_by', 'INT(11) DEFAULT NULL');
			$t->primary('id');
			$t->unique('unique_thread_uid', ['thread_uid']);
			$t->index('idx_theme_added_by', ['added_by']);
			$t->foreign('fk_theme_added_by', 'added_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
			$t->foreign('fk_theme_thread_uid', 'thread_uid', 'THREAD_TABLE', 'thread_uid', 'CASCADE');
		});

		$schema->createTable('DELETED_POSTS_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('post_uid', 'INT(11) NOT NULL');
			$t->column('deleted_by', 'INT(11) DEFAULT NULL');
			$t->column('deleted_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('file_only', 'TINYINT(1) DEFAULT 0');
			$t->column('by_proxy', 'TINYINT(1) DEFAULT 0');
			$t->column('restored_at', 'TIMESTAMP NULL DEFAULT NULL');
			$t->column('restored_by', 'INT(11) DEFAULT NULL');
			$t->column('file_id', 'INT(11) DEFAULT NULL');
			$t->column('open_flag', 'TINYINT(1) GENERATED ALWAYS AS (if(`restored_at` is NULL,1,0)) STORED');
			$t->column('open_key', 'INT(11) GENERATED ALWAYS AS (case when `restored_at` is NULL and `file_id` is NULL then `post_uid` else NULL end) STORED');
			$t->primary('id');
			$t->unique('uq_open_post', ['open_key']);
			$t->index('idx_post_uid', ['post_uid']);
			$t->index('idx_deleted_by_deleted_at', ['deleted_by', 'deleted_at']);
			$t->index('idx_restored_at', ['restored_at']);
			$t->index('idx_file_id', ['file_id']);
			$t->foreign('fk_dp_file', 'file_id', 'FILE_TABLE', 'id', 'CASCADE');
			$t->foreign('fk_dp_post', 'post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('POST_NUMBER_TABLE', function (tableBlueprint $t): void {
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('post_number', 'INT(11) NOT NULL DEFAULT 0');
			$t->primary('board_uid');
			$t->foreign('fk_post_count_board_uid', 'board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
		});

		$schema->createTable('POST_NUMBER_HISTORY_TABLE', function (tableBlueprint $t): void {
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('day', 'DATE NOT NULL');
			$t->column('post_number', 'INT(11) NOT NULL');
			$t->primary('board_uid', 'day');
			$t->foreign('fk_post_number_history_board_uid', 'board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
		});

		$schema->createTable('LAST_THREAD_SUBMISSIONS_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('last_submission_timestamp', 'TIMESTAMP(3) NOT NULL');
			$t->primary('id');
			$t->unique('board_uid', ['board_uid']);
			$t->foreign('fk_last_thread_submissions_board_uid', 'board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
		});

		$schema->createTable('BOARD_CONFIG_TABLE', function (tableBlueprint $t): void {
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('conf_values', 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`conf_values`))');
			$t->primary('board_uid');
			$t->unique('uq_board_config_board_uid', ['board_uid']);
			$t->foreign('fk_board_config_board_uid', 'board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
		});

		$schema->createTable('BOARD_PATH_CACHE_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('boardUID', 'INT(11) NOT NULL');
			$t->column('board_path', 'TEXT NOT NULL');
			$t->primary('id');
			$t->index('path_cache_board_uid', ['boardUID']);
			$t->foreign('path_cache_board_uid', 'boardUID', 'BOARD_TABLE', 'board_uid', 'CASCADE');
		});

		$schema->createTable('ACTIONLOG_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('time_added', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('date_added', 'DATE DEFAULT curdate()');
			$t->column('name', 'TEXT NOT NULL');
			$t->column('role', 'INT(11) NOT NULL');
			$t->column('log_action', 'TEXT NOT NULL');
			$t->column('ip_address', 'TEXT NOT NULL');
			$t->column('board_uid', 'INT(11) DEFAULT NULL');
			$t->column('board_title', 'TEXT NOT NULL');
			$t->primary('id');
			$t->index('role', ['role']);
			$t->index('time_added', ['time_added']);
			$t->index('name', ['name(768)']);
			$t->index('board_uid', ['board_uid']);
		});

		$schema->createTable('CAPCODE_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('tripcode', 'VARCHAR(255) DEFAULT NULL');
			$t->column('is_secure', 'TINYINT(1) DEFAULT 0');
			$t->column('date_added', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('added_by', 'INT(11) DEFAULT NULL');
			$t->column('color_hex', 'CHAR(7) NOT NULL');
			$t->column('cap_text', 'TEXT DEFAULT NULL');
			$t->primary('id');
			$t->unique('unique_tripcode_is_secure', ['tripcode', 'is_secure']);
			$t->index('fk_capcodes_added_by', ['added_by']);
			$t->foreign('fk_capcodes_added_by', 'added_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});

		$schema->createTable('NOTE_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('post_uid', 'INT(11) NOT NULL');
			$t->column('note_submitted', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('added_by', 'INT(11) DEFAULT NULL');
			$t->column('note_text', 'TEXT NOT NULL');
			$t->primary('id');
			$t->index('fk_notes_post_uid', ['post_uid']);
			$t->index('fk_notes_added_by', ['added_by']);
			$t->foreign('fk_notes_added_by', 'added_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
			$t->foreign('fk_notes_post_uid', 'post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('PRIVATE_MESSAGE_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('ip_address', 'TEXT NOT NULL');
			$t->column('date_sent', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('sender_tripcode', 'VARCHAR(255) NOT NULL');
			$t->column('sender_name', 'TEXT NOT NULL');
			$t->column('recipient_tripcode', 'VARCHAR(255) NOT NULL');
			$t->column('message_subject', 'TEXT NOT NULL');
			$t->column('message_body', 'TEXT NOT NULL');
			$t->column('is_read', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->primary('id');
		});

		$schema->createTable('REPORT_TABLE', function (tableBlueprint $t): void {
			$t->column('report_id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('post_uid', 'INT(11) NOT NULL');
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('reporter_ip', 'VARCHAR(255) NOT NULL');
			$t->column('reporter_reason', 'TEXT DEFAULT NULL');
			$t->column('date_reported', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('status', 'TINYINT(4) NOT NULL DEFAULT 0');
			$t->column('actioned_by', 'INT(11) DEFAULT NULL');
			$t->column('actioned_at', 'DATETIME DEFAULT NULL');
			$t->column('public_reason', 'TEXT DEFAULT NULL');
			$t->column('private_reason', 'TEXT DEFAULT NULL');
			$t->primary('report_id');
			$t->index('idx_reports_status_date', ['status', 'date_reported']);
			$t->index('idx_reports_post_uid', ['post_uid']);
			$t->index('idx_reports_board_uid', ['board_uid']);
			$t->index('idx_reports_reporter_ip', ['reporter_ip']);
			$t->index('idx_reports_date_reported', ['date_reported']);
			$t->index('idx_reports_actioned_by', ['actioned_by']);
			$t->foreign('fk_reports_actioned_by', 'actioned_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
			$t->foreign('fk_reports_board_uid', 'board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
			$t->foreign('fk_reports_post_uid', 'post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('REPORT_READ_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('report_id', 'BIGINT(20) UNSIGNED NOT NULL');
			$t->column('account_id', 'INT(11) NOT NULL');
			$t->column('date_read', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->unique('uniq_report_read', ['report_id', 'account_id']);
			$t->index('idx_report_reads_account', ['account_id']);
			$t->foreign('fk_report_reads_account_id', 'account_id', 'ACCOUNT_TABLE', 'id', 'CASCADE');
			$t->foreign('fk_report_reads_report_id', 'report_id', 'REPORT_TABLE', 'report_id', 'CASCADE');
		});

		$schema->createTable('SPAM_STRING_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('pattern', 'TEXT NOT NULL');
			$t->column('max_distance', 'TINYINT(3) UNSIGNED DEFAULT NULL');
			$t->column('match_type', 'ENUM(\'contains\',\'exact\',\'fuzzy\',\'regex\') NOT NULL DEFAULT \'contains\'');
			$t->column('apply_subject', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('apply_comment', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('apply_name', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('apply_email', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('apply_filename', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('apply_op_only', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('silent_reject', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('case_sensitive', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('user_message', 'TEXT DEFAULT NULL');
			$t->column('description', 'TEXT DEFAULT NULL');
			$t->column('action', 'ENUM(\'mute\',\'reject\',\'ban\') NOT NULL DEFAULT \'reject\'');
			$t->column('created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('created_by', 'INT(11) DEFAULT NULL');
			$t->primary('id');
			$t->index('idx_spam_active', ['is_active']);
			$t->index('idx_spam_match_type', ['match_type']);
			$t->index('idx_spam_created_by', ['created_by']);
			$t->foreign('fk_spam_string_rules_created_by', 'created_by', 'ACCOUNT_TABLE', 'id', 'SET NULL', 'CASCADE');
		});

		$schema->createTable('FILE_BAN_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('file_md5', 'CHAR(32) NOT NULL');
			$t->column('added_by', 'INT(11) DEFAULT NULL');
			$t->column('created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->unique('uq_file_md5', ['file_md5']);
			$t->index('idx_file_ban_added_by', ['added_by']);
			$t->foreign('fk_file_ban_added_by', 'added_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});

		$schema->createTable('PERCEPTUAL_BAN_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('phash', 'BIGINT(20) NOT NULL');
			$t->column('phash_hex', 'CHAR(16) NOT NULL');
			$t->column('added_by', 'INT(11) DEFAULT NULL');
			$t->column('created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->unique('uq_phash', ['phash']);
			$t->index('idx_perceptual_ban_added_by', ['added_by']);
			$t->foreign('fk_perceptual_ban_added_by', 'added_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});

		$schema->createTable('SOUDANE_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('ip_address', 'VARCHAR(255) DEFAULT NULL');
			$t->column('yeah', 'TINYINT(1) DEFAULT 0');
			$t->column('post_uid', 'INT(11) DEFAULT NULL');
			$t->column('date_added', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->index('idx_soudane_vote', ['post_uid', 'yeah']);
			$t->index('idx_soudane_ip', ['ip_address']);
			$t->index('idx_soudane_date_added', ['date_added']);
			$t->foreign('fk_soudane_post_uid', 'post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('COUNTRY_FLAG_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('post_uid', 'INT(11) NOT NULL');
			$t->column('country', 'VARCHAR(8) NOT NULL');
			$t->primary('id');
			$t->unique('uq_country_flag_post_uid', ['post_uid']);
			$t->foreign('fk_country_flag_post_uid', 'post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('DISPLAY_IP_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('post_uid', 'INT(11) NOT NULL');
			$t->column('ip_part', 'VARCHAR(512) NOT NULL DEFAULT \'\'');
			$t->primary('id');
			$t->unique('uq_display_ip_post_uid', ['post_uid']);
			$t->foreign('fk_display_ip_post_uid', 'post_uid', 'POST_TABLE', 'post_uid', 'CASCADE');
		});

		$schema->createTable('BANNER_AD_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('link', 'TEXT DEFAULT NULL');
			$t->column('banner_file_name', 'TEXT NOT NULL');
			$t->column('ip_address', 'VARCHAR(45) DEFAULT NULL');
			$t->column('is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('is_approved', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('date_submitted', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->index('idx_active_approved', ['is_active', 'is_approved']);
			$t->index('idx_date_submitted', ['date_submitted']);
			$t->index('idx_ip_date', ['ip_address', 'date_submitted']);
		});

		$schema->createTable('ADS_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('slot', 'VARCHAR(20) NOT NULL');
			$t->column('type', 'VARCHAR(10) NOT NULL');
			$t->column('src', 'TEXT DEFAULT NULL');
			$t->column('href', 'TEXT DEFAULT NULL');
			$t->column('alt', 'TEXT DEFAULT NULL');
			$t->column('html', 'TEXT DEFAULT NULL');
			$t->column('enabled', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('date_added', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->index('idx_ads_slot_enabled', ['slot', 'enabled']);
		});

		$schema->createTable('BLOTTER_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('blotter_content', 'TEXT NOT NULL');
			$t->column('added_by', 'INT(11) DEFAULT NULL');
			$t->column('date_added', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->primary('id');
			$t->index('fk_blotter_added_by', ['added_by']);
			$t->foreign('fk_blotter_added_by', 'added_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->execute('SET FOREIGN_KEY_CHECKS = 0');
		$ctx->execute('DROP TABLE IF EXISTS {BLOTTER_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {ADS_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {BANNER_AD_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {DISPLAY_IP_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {COUNTRY_FLAG_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {SOUDANE_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {PERCEPTUAL_BAN_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {FILE_BAN_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {SPAM_STRING_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {REPORT_READ_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {REPORT_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {PRIVATE_MESSAGE_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {NOTE_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {CAPCODE_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {ACTIONLOG_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {BOARD_PATH_CACHE_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {BOARD_CONFIG_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {LAST_THREAD_SUBMISSIONS_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {POST_NUMBER_HISTORY_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {POST_NUMBER_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {DELETED_POSTS_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {THREAD_THEMES_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {ACCOUNT_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {THREAD_REDIRECT_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {QUOTE_LINK_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {FILE_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {POST_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {THREAD_TABLE}');
		$ctx->execute('DROP TABLE IF EXISTS {BOARD_TABLE}');
		$ctx->execute('SET FOREIGN_KEY_CHECKS = 1');
	}
};
