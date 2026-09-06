<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpTarget.php';

use Kokonotsuba\database\ValidatesIdentifiersTrait;

/**
 * Every IP-bearing column in the schema, in one place.
 *
 * Adding a table that stores an address means adding it here; nothing else in the module needs
 * to change. Two kinds of value are deliberately left alone:
 *
 *  - bans.ip_pattern on a ban that can still match. The pattern is what enforces the ban, so
 *    only patterns that can no longer match anything are touched (see the target below).
 *  - Wildcard patterns, in bans and host notes alike, which are matched in PHP rather than by
 *    an equality lookup and are not a single address to begin with.
 *  - bans.visitor_token_hash, which is enforcement data like ip_pattern: a token-tied ban
 *    matches on it, so clearing it would silently lift the ban.
 */
final class anonIpTargets {
	use ValidatesIdentifiersTrait;

	/**
	 * Build the target list against a logical-key => real-name table map.
	 *
	 * @param array<string, string> $tableNames From getTableNames().
	 * @param string                $now        Current time as 'Y-m-d H:i:s', on PHP's clock.
	 * @return anonIpTarget[]
	 */
	public static function build(array $tableNames, string $now): array {
		$postTable = self::table($tableNames, 'POST_TABLE');
		self::validateTableName($postTable);

		return [
			new anonIpTarget(
				'posts',
				$postTable,
				'host',
				cutoffSql: 'root < :cutoff',
			),
			// The browser token hash recorded on the post. Nothing needs to match it once the
			// post is old, and a hash of a hash would still tell two posters apart, so it is
			// discarded outright. NULL is what a post written before the column existed holds,
			// and what the renderer and the staff filters already read as "no token".
			new anonIpTarget(
				'postTokens',
				$postTable,
				'visitor_token_hash',
				mode: anonIpTarget::MODE_CLEAR,
				clearTo: null,
				cutoffSql: 'root < :cutoff',
			),
			new anonIpTarget(
				'actionLog',
				self::table($tableNames, 'ACTIONLOG_TABLE'),
				'ip_address',
				cutoffSql: 'time_added < :cutoff',
			),
			new anonIpTarget(
				'soudane',
				self::table($tableNames, 'SOUDANE_TABLE'),
				'ip_address',
				cutoffSql: 'date_added < :cutoff',
			),
			new anonIpTarget(
				'reports',
				self::table($tableNames, 'REPORT_TABLE'),
				'reporter_ip',
				cutoffSql: 'date_reported < :cutoff',
			),
			new anonIpTarget(
				'privateMessages',
				self::table($tableNames, 'PRIVATE_MESSAGE_TABLE'),
				'ip_address',
				cutoffSql: 'date_sent < :cutoff',
			),
			new anonIpTarget(
				'banAppeals',
				self::table($tableNames, 'BAN_APPEAL_TABLE'),
				'appellant_ip',
				cutoffSql: 'filed_at < :cutoff',
			),
			new anonIpTarget(
				'bannerAds',
				self::table($tableNames, 'BANNER_AD_TABLE'),
				'ip_address',
				cutoffSql: 'date_submitted < :cutoff',
			),
			// The ledger prunes itself on its retention setting, but until it does it holds raw
			// addresses; anonymizing an old row only costs the lockout counter a failure that
			// already fell outside its window.
			new anonIpTarget(
				'loginAttempts',
				self::table($tableNames, 'LOGIN_ATTEMPT_TABLE'),
				'ip',
				cutoffSql: 'attempted_at < :cutoff',
			),
			// Only exact patterns on bans that can no longer match: revoked, or lapsed. Expiry
			// is compared on PHP's clock like everywhere else in the ban system, because PHP and
			// MariaDB may sit in different timezones.
			new anonIpTarget(
				'bans',
				self::table($tableNames, 'BAN_TABLE'),
				'ip_pattern',
				cutoffSql: 'filed_at < :cutoff',
				guardSql: 'is_wildcard = 0 AND (revoked_at IS NOT NULL'
					. ' OR (expires_at IS NOT NULL AND expires_at <= :ban_now))',
				guardParams: [':ban_now' => $now],
			),
			// Only exact patterns, for the same reason bans hold back wildcards: a range is not
			// an address, and is matched in PHP rather than by an equality lookup. A note and
			// the posts it applies to age together, so both sides end up hashed under the same
			// salt and go on matching. A browser note holds no address at all, so it is skipped
			// explicitly rather than left to fall out of the NULL comparison.
			new anonIpTarget(
				'hostNotes',
				self::table($tableNames, 'HOST_NOTE_TABLE'),
				'ip_pattern',
				cutoffSql: 'note_submitted < :cutoff',
				guardSql: 'is_wildcard = 0 AND ip_pattern IS NOT NULL',
			),
			// A display fragment rather than an address: the leading half of the IP, rendered
			// publicly under the post. Hashing it would leave a hash on the page, so it is
			// cleared instead, which the renderer already draws as nothing. The table has no
			// timestamp of its own, so it ages against the post it belongs to.
			new anonIpTarget(
				'displayIp',
				self::table($tableNames, 'DISPLAY_IP_TABLE'),
				'ip_part',
				mode: anonIpTarget::MODE_CLEAR,
				cutoffSql: "post_uid IN (SELECT post_uid FROM `{$postTable}` WHERE root < :cutoff)",
			),
		];
	}

	/**
	 * @param array<string, string> $tableNames
	 * @throws \InvalidArgumentException when the schema has no such table.
	 */
	private static function table(array $tableNames, string $key): string {
		if (!isset($tableNames[$key])) {
			throw new \InvalidArgumentException("Unknown table key: {$key}. Add it to tables.php.");
		}

		return $tableNames[$key];
	}
}
