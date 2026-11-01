<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Drop board-scope overrides of the settings that became site-wide.
 *
 * Those settings are only read from the global scope now, so a board's own value is dead data.
 * The list is frozen here: later site-wide fields are not this migration's concern.
 */
return new class extends migration {
	/** GLOBAL_BOARD_UID; the global scope's row is left alone. */
	private const GLOBAL_SCOPE = -1;

	private const KEYS = [
		'HOME', 'GZIP_COMPRESS_LEVEL', 'ACTIONLOG_MAX_PER_PAGE',
		'STAFF_LOGIN_TIMEOUT', 'LOGIN_MAX_ATTEMPTS', 'LOGIN_MAX_ATTEMPTS_PER_IP', 'LOGIN_ATTEMPT_WINDOW',
		'LOGIN_LOCKOUT_SECONDS', 'LOGIN_LOCKOUT_MAX_SECONDS', 'LOGIN_ATTEMPT_RETENTION_DAYS',
		'ModuleList.anonIp', 'ModuleList.csrfPrevent', 'ModuleList.imageServer',
		'OVERBOARD_TITLE', 'OVERBOARD_SUBTITLE', 'OVERBOARD_THREADS_PER_PAGE', 'OVERBOARD_TEMPLATE', 'CONTACT_URL',
		'modules.adminBan.ENABLE_APPEALS', 'modules.adminBan.APPEAL_MAX_LENGTH', 'modules.adminBan.APPEAL_COOLDOWN_HOURS',
		'modules.adminBan.ENABLE_JS_BAN_CHECK', 'modules.adminBan.BAN_MARKER_COOKIE', 'modules.adminBan.BAN_MARKER_URL',
		'modules.anonIp.AUTO_ANONYMIZE_DAYS',
		'modules.banner.BANNER_AD_ALLOW_SUBMISSIONS', 'modules.banner.BANNER_AD_SUBMISSION_COOLDOWN',
		'modules.banner.BANNER_AD_WIDTH', 'modules.banner.BANNER_AD_HEIGHT', 'modules.banner.BANNER_AD_MAX_FILE_SIZE',
		'modules.debugBar.PROFILER_STAFF_ONLY', 'modules.deletedPosts.PRUNE_TIME',
		'modules.report.ENABLE_NOTIFICATIONS', 'modules.report.NOTIFICATION_WINDOW_MINUTES', 'modules.report.NOTIFICATION_POLL_SECONDS',
		'modules.segregator.SEGREGATOR_SUB_DOMAIN', 'modules.segregator.SEGREGATOR_COOKIE_NAME', 'modules.segregator.SEGREGATOR_COOKIE_DOMAIN',
		'modules.staffAlerts.POLL_SECONDS',
	];

	public function description(): string {
		return 'Drop board overrides of site-wide settings';
	}

	public function up(migrationContext $ctx): void {
		$dropped = [];
		$rows = 0;

		foreach ($this->boardRows($ctx) as $boardUid => $values) {
			$kept = array_diff_key($values, array_flip(self::KEYS));
			if (count($kept) === count($values)) {
				continue;
			}

			foreach (array_keys(array_diff_key($values, $kept)) as $key) {
				$dropped[$key] = ($dropped[$key] ?? 0) + 1;
			}
			$rows++;

			// An emptied scope has no row, as configService leaves it.
			if ($kept === []) {
				$ctx->execute('DELETE FROM {BOARD_CONFIG_TABLE} WHERE board_uid = ?', [$boardUid]);
			} else {
				$ctx->execute(
					'UPDATE {BOARD_CONFIG_TABLE} SET conf_values = ? WHERE board_uid = ?',
					[json_encode($kept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $boardUid]
				);
			}
		}

		$verb = $ctx->isDryRun() ? 'would drop' : 'dropped';
		foreach ($dropped as $key => $count) {
			$ctx->note("{$verb} {$key} from {$count} board(s)");
		}
		$ctx->note("{$verb} site-wide overrides from {$rows} board(s)");
	}

	/** The dropped values were never applied once their settings went site-wide. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		if (!$ctx->inspector->tableExists($ctx->table('BOARD_CONFIG_TABLE'))) {
			return true;
		}

		foreach ($this->boardRows($ctx) as $values) {
			if (array_intersect_key($values, array_flip(self::KEYS)) !== []) {
				return false;
			}
		}

		return true;
	}

	/** @return array<int, array<string, mixed>> board uid => stored overrides, global scope excluded */
	private function boardRows(migrationContext $ctx): array {
		$rows = [];
		foreach ($ctx->fetchAll('SELECT board_uid, conf_values FROM {BOARD_CONFIG_TABLE} WHERE board_uid <> ?', [self::GLOBAL_SCOPE]) as $row) {
			$decoded = json_decode((string)$row['conf_values'], true);
			if (is_array($decoded)) {
				$rows[(int)$row['board_uid']] = $decoded;
			}
		}
		return $rows;
	}
};
