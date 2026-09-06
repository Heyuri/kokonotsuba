<?php

namespace Kokonotsuba\ban;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/**
 * Storage for bans and warnings.
 *
 * Every timestamp here is written and compared using PHP's clock, never the database's: a DATETIME
 * written by PHP and compared against NOW() silently misreads by the offset between the two
 * whenever they disagree, which would expire bans the moment they were filed.
 *
 * Enforcement reads through findEnforceableFor(), which narrows to the rows that could possibly
 * apply — an exact address match, any wildcard pattern, or a ban tied to one of the visitor's
 * token — and leaves the wildcard comparison itself to banService. Every other query here
 * serves the admin table, which is why they all carry the same join set.
 */
class banRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $banTable,
		private readonly string $banAppealTable,
		private readonly string $accountTable,
		private readonly string $postTable,
		private readonly string $boardTable,
	) {
		parent::__construct($databaseConnection, $banTable);
		self::validateTableNames($banAppealTable, $accountTable, $postTable, $boardTable);
	}

	/**
	 * The token hash of the browser that made a post, for tying a ban to it.
	 *
	 * '' when the post recorded that the browser kept no token, null when it predates the column.
	 */
	public function findVisitorTokenHashForPost(int $postUid): ?string {
		$row = $this->queryOne("SELECT visitor_token_hash FROM {$this->postTable} WHERE post_uid = ?", [$postUid]);

		return $row === false || $row['visitor_token_hash'] === null ? null : (string) $row['visitor_token_hash'];
	}

	/** A ban row plus the staff usernames, post number, board title and appeal counts every view wants. */
	private function baseSelect(): string {
		return "
			SELECT
				b.*,
				fa.username AS filed_by_username,
				ra.username AS revoked_by_username,
				p.no AS post_number,
				bo.board_title AS board_title,
				(SELECT COUNT(*) FROM {$this->banAppealTable} ap WHERE ap.ban_id = b.ban_id) AS appeal_count,
				(SELECT COUNT(*) FROM {$this->banAppealTable} ap WHERE ap.ban_id = b.ban_id AND ap.status = 0) AS pending_appeal_count
			FROM {$this->table} b
			LEFT JOIN {$this->accountTable} fa ON fa.id = b.filed_by
			LEFT JOIN {$this->accountTable} ra ON ra.id = b.revoked_by
			LEFT JOIN {$this->postTable} p ON p.post_uid = b.post_uid
			LEFT JOIN {$this->boardTable} bo ON bo.board_uid = b.board_uid";
	}

	/**
	 * Rows that still hold something over this visitor: not revoked, scoped to one of the given
	 * boards, and matching either the address, any wildcard pattern, or a known token.
	 *
	 * A lapsed ban comes back too while its expiry notice is still owed - it stops nothing, but
	 * it has one interruption left in it to say so. banService tells the two apart.
	 *
	 * @param list<int>    $boardUids Board scopes to consider (the current board plus the global scope).
	 * @param list<string> $tokenHashes Token of the browser making this request, if any.
	 * @return list<banEntry>
	 */
	public function findEnforceableFor(string $ip, array $boardUids, array $tokenHashes = []): array {
		$matchClauses = ['b.ip_pattern = ?', 'b.is_wildcard = 1'];

		// Placeholders are positional, so the parameters have to be assembled in the order the
		// clauses appear in the statement: the moment, then board scopes, then the address, then
		// the tokens.
		$params = array_merge([self::now()], $boardUids, [$ip]);

		if ($tokenHashes !== []) {
			$matchClauses[] = 'b.visitor_token_hash IN ' . pdoPlaceholdersForIn($tokenHashes);
			$params = array_merge($params, $tokenHashes);
		}

		$query = $this->baseSelect() . '
			WHERE b.revoked_at IS NULL
				AND (
					b.expires_at IS NULL
					OR b.expires_at > ?
					OR (b.expiry_seen_at IS NULL AND b.is_warning = 0 AND b.is_mute = 0)
				)
				AND b.board_uid IN ' . pdoPlaceholdersForIn($boardUids) . '
				AND (' . implode(' OR ', $matchClauses) . ')
			ORDER BY b.filed_at DESC';

		return $this->hydrateAll($this->queryAll($query, $params));
	}

	/** The current moment as a DATETIME literal, on PHP's clock. */
	public static function now(): string {
		return date('Y-m-d H:i:s');
	}

	public function findById(int $banId): ?banEntry {
		$row = $this->queryOne($this->baseSelect() . ' WHERE b.ban_id = ?', [$banId]);

		return $row === false ? null : banEntry::fromRow($row);
	}

	/**
	 * @param array<string, mixed> $data Column => value for the new row.
	 * @return int The new ban's id.
	 */
	public function insertBan(array $data): int {
		$this->insert($data);

		return (int) $this->lastInsertId();
	}

	/**
	 * Filters shared by listBans() and countBans().
	 *
	 * Every filter is optional and they narrow together. Anything that needs a table other than
	 * the ban table is asked as a subquery rather than a join, so the count can share this
	 * without growing joins it has no other use for.
	 *
	 * @param array{
	 *     banId?: int|null, boards?: list<int>, boardUid?: int|null, status?: string,
	 *     kind?: string, ip?: string, token?: string, general?: string, search?: string,
	 *     reason?: string, staff?: string, postNumber?: int|null, checkpoints?: list<string>,
	 *     dateAfter?: string, dateBefore?: string, searchAddresses?: bool
	 * } $filters
	 * @return array{0: string, 1: array}
	 */
	private function buildFilters(array $filters): array {
		$clauses = [];
		$params = [];

		// Addresses are hidden from staff who may not see them, so a text search must not reach
		// them either - it would answer "is this address banned" for someone who cannot ask.
		$searchAddresses = ($filters['searchAddresses'] ?? true) !== false;

		$banId = (int) ($filters['banId'] ?? 0);

		if ($banId > 0) {
			$clauses[] = 'b.ban_id = ?';
			$params[] = $banId;
		}

		// boardUid is the one-scope shorthand; the filter form sends a list.
		$boards = array_map('intval', $filters['boards'] ?? []);

		if (($filters['boardUid'] ?? null) !== null) {
			$boards[] = (int) $filters['boardUid'];
		}

		$boards = array_values(array_unique($boards));

		if ($boards !== []) {
			$clauses[] = 'b.board_uid IN ' . pdoPlaceholdersForIn($boards);
			$params = array_merge($params, $boards);
		}

		// Exact rather than a LIKE: filtering for 192.0.2.1 must not drag in 192.0.2.10.
		$ip = $searchAddresses ? trim((string) ($filters['ip'] ?? '')) : '';

		if ($ip !== '') {
			$clauses[] = 'b.ip_pattern = ?';
			$params[] = $ip;
		}

		$token = $searchAddresses ? trim((string) ($filters['token'] ?? '')) : '';

		if ($token !== '') {
			$clauses[] = 'b.visitor_token_hash = ?';
			$params[] = $token;
		}

		switch ($filters['status'] ?? 'all') {
			case 'active':
				$clauses[] = 'b.revoked_at IS NULL AND (b.expires_at IS NULL OR b.expires_at > ?)';
				$params[] = self::now();
				break;
			case 'expired':
				$clauses[] = 'b.revoked_at IS NULL AND b.expires_at IS NOT NULL AND b.expires_at <= ?';
				$params[] = self::now();
				break;
			case 'revoked':
				$clauses[] = 'b.revoked_at IS NOT NULL';
				break;
			case 'appealed':
				$clauses[] = "EXISTS (SELECT 1 FROM {$this->banAppealTable} ap WHERE ap.ban_id = b.ban_id AND ap.status = 0)";
				break;
		}

		// A warning and a mute are ban rows with a flag on them, so the table can be narrowed to
		// one kind the same way it is narrowed to one status.
		switch ($filters['kind'] ?? 'all') {
			case 'ban':
				$clauses[] = 'b.is_warning = 0 AND b.is_mute = 0';
				break;
			case 'warning':
				$clauses[] = 'b.is_warning = 1';
				break;
			case 'mute':
				$clauses[] = 'b.is_mute = 1';
				break;
		}

		// Any of the three reasons: which one a moderator wrote in is not something they should
		// have to remember to search it back out.
		$reason = trim((string) ($filters['reason'] ?? ''));

		if ($reason !== '') {
			$clauses[] = '(b.reason LIKE ? OR b.public_reason LIKE ? OR b.private_reason LIKE ?)';
			$params = array_merge($params, array_fill(0, 3, '%' . $reason . '%'));
		}

		$staff = trim((string) ($filters['staff'] ?? ''));

		if ($staff !== '') {
			$clauses[] = "b.filed_by IN (SELECT id FROM {$this->accountTable} WHERE username LIKE ?)";
			$params[] = '%' . $staff . '%';
		}

		$postNumber = (int) ($filters['postNumber'] ?? 0);

		if ($postNumber > 0) {
			$clauses[] = "EXISTS (SELECT 1 FROM {$this->postTable} fp WHERE fp.post_uid = b.post_uid AND fp.no = ?)";
			$params[] = $postNumber;
		}

		// Checkpoints are stored as the comma list the ban form built, so a ban matches when it
		// blocks any of the ticked ones. A warning blocks nothing and so matches none of them.
		$checkpoints = array_filter(array_map('strval', $filters['checkpoints'] ?? []));

		if ($checkpoints !== []) {
			$matches = [];

			foreach ($checkpoints as $checkpoint) {
				$matches[] = 'FIND_IN_SET(?, b.checkpoints)';
				$params[] = $checkpoint;
			}

			$clauses[] = '(' . implode(' OR ', $matches) . ')';
		}

		$dateAfter = self::toDateBound((string) ($filters['dateAfter'] ?? ''), false);

		if ($dateAfter !== null) {
			$clauses[] = 'b.filed_at >= ?';
			$params[] = $dateAfter;
		}

		$dateBefore = self::toDateBound((string) ($filters['dateBefore'] ?? ''), true);

		if ($dateBefore !== null) {
			$clauses[] = 'b.filed_at <= ?';
			$params[] = $dateBefore;
		}

		// The catch-all field: everything the specific ones ask, asked at once. "search" is what
		// the old single-box form called it, and links to it are still in people's histories.
		$general = trim((string) ($filters['general'] ?? $filters['search'] ?? ''));

		if ($general !== '') {
			$matches = [
				'b.reason LIKE ?',
				'b.public_reason LIKE ?',
				'b.private_reason LIKE ?',
				"b.filed_by IN (SELECT id FROM {$this->accountTable} WHERE username LIKE ?)",
			];
			$params = array_merge($params, array_fill(0, 4, '%' . $general . '%'));

			if ($searchAddresses) {
				$matches[] = 'b.ip_pattern LIKE ?';
				$params[] = '%' . $general . '%';
			}

			$clauses[] = '(' . implode(' OR ', $matches) . ')';
		}

		return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
	}

	/**
	 * One end of a date range as the ban table stores its timestamps.
	 *
	 * A date names a whole day, so the upper bound is its last second rather than its first.
	 */
	private static function toDateBound(string $date, bool $isUpper): ?string {
		$date = trim($date);

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return null;
		}

		return $date . ($isUpper ? ' 23:59:59' : ' 00:00:00');
	}

	/**
	 * @param array $filters As buildFilters() takes them.
	 * @return list<banEntry>
	 */
	public function listBans(array $filters, int $limit, int $offset): array {
		[$where, $params] = $this->buildFilters($filters);

		// Interpolated rather than bound: every other parameter here is positional, and PDO will
		// not take a named LIMIT placeholder alongside them. Both are cast, so nothing user-typed
		// reaches the SQL.
		$query = $this->baseSelect() . $where
			. ' ORDER BY b.filed_at DESC, b.ban_id DESC'
			. ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

		return $this->hydrateAll($this->queryAll($query, $params));
	}

	/** @param array $filters As buildFilters() takes them. */
	public function countBans(array $filters): int {
		[$where, $params] = $this->buildFilters($filters);

		return (int) $this->queryValue("SELECT COUNT(*) FROM {$this->table} b" . $where, $params);
	}

	/**
	 * Lift the given bans, ignoring any that were already lifted.
	 *
	 * @param list<int> $banIds
	 * @return list<banEntry> The rows that were actually revoked, for the action log.
	 */
	public function revokeBans(array $banIds, ?int $accountId): array {
		if ($banIds === []) {
			return [];
		}

		$placeholders = pdoPlaceholdersForIn($banIds);

		$revoked = $this->hydrateAll($this->queryAll(
			$this->baseSelect() . " WHERE b.ban_id IN {$placeholders} AND b.revoked_at IS NULL",
			$banIds
		));

		if ($revoked === []) {
			return [];
		}

		$ids = array_map(fn(banEntry $ban): int => $ban->id, $revoked);

		$this->query(
			"UPDATE {$this->table} SET revoked_at = ?, revoked_by = ?
			WHERE ban_id IN " . pdoPlaceholdersForIn($ids),
			array_merge([self::now(), $accountId], $ids)
		);

		return $revoked;
	}

	/**
	 * Apply an edit to an existing ban.
	 *
	 * @param array<string, mixed> $data Column => value for the columns being changed.
	 */
	public function updateBan(int $banId, array $data): void {
		if ($data === []) {
			return;
		}

		$this->updateWhere($data, 'ban_id', $banId);
	}

	/**
	 * Public ban notices for a batch of posts, keyed by post UID.
	 *
	 * The notice is rendered under the post from here rather than written into its comment, so
	 * editing a ban's public reason changes what every reader sees. A revoked ban's notice
	 * disappears with it - the ban was undone, so the post should stop saying otherwise - but an
	 * expired one keeps its notice, because they were still banned for that post.
	 *
	 * @param list<int> $postUids
	 * @return array<int, string>
	 */
	public function findPublicReasonsForPosts(array $postUids): array {
		if ($postUids === []) {
			return [];
		}

		$rows = $this->queryAll(
			"SELECT post_uid, public_reason FROM {$this->table}
			WHERE revoked_at IS NULL
				AND public_reason IS NOT NULL AND public_reason <> ''
				AND post_uid IN " . pdoPlaceholdersForIn($postUids) . '
			ORDER BY filed_at ASC',
			$postUids
		);

		$reasons = [];

		foreach ($rows as $row) {
			$reasons[(int) $row['post_uid']] = (string) $row['public_reason'];
		}

		return $reasons;
	}

	/**
	 * Throw away mutes that have lapsed.
	 *
	 * A mute is a short automatic ban filed in bulk during a flood; once it is over it is only
	 * clutter in the table, and nothing hangs off it (mutes cannot be appealed).
	 *
	 * @return int Rows removed.
	 */
	public function deleteExpiredMutes(): int {
		return $this->queryAffected(
			"DELETE FROM {$this->table} WHERE is_mute = 1 AND expires_at IS NOT NULL AND expires_at <= ?",
			[self::now()]
		);
	}

	/**
	 * Move a ban's expiry, used when an appeal is approved with a reduced sentence.
	 *
	 * An expiry that has not passed yet takes the notice back with it: the ban runs again, so the
	 * telling it owes when it next lapses has not happened.
	 */
	public function setExpiry(int $banId, ?int $expiresAt): void {
		$stillToCome = $expiresAt === null || $expiresAt > time();

		$this->query(
			"UPDATE {$this->table} SET expires_at = ?" . ($stillToCome ? ', expiry_seen_at = NULL' : '')
				. " WHERE ban_id = ?",
			[$expiresAt === null ? null : date('Y-m-d H:i:s', $expiresAt), $banId]
		);
	}

	/**
	 * Record that the banned party has now seen the ban, and whether their browser carried a
	 * token cookie when they did. Only the first sighting is kept.
	 */
	public function markSeen(int $banId, bool $withCookies): void {
		$this->query(
			"UPDATE {$this->table} SET seen_at = ?, seen_cookies = ? WHERE ban_id = ? AND seen_at IS NULL",
			[self::now(), $withCookies ? 1 : 0, $banId]
		);
	}

	/**
	 * Record that the banned party has now been told this ban ran out, which is what lets go of
	 * it. Only the first telling is kept.
	 */
	public function markExpirySeen(int $banId): void {
		$this->query(
			"UPDATE {$this->table} SET expiry_seen_at = ? WHERE ban_id = ? AND expiry_seen_at IS NULL",
			[self::now(), $banId]
		);
	}

	/**
	 * Active, non-warning bans matching this address, for the user's own ban page.
	 *
	 * Unlike findEnforceableFor() this is not scoped to a board — the ban page shows everything
	 * currently held against the visitor.
	 *
	 * @param list<string> $tokenHashes
	 * @return list<banEntry>
	 */
	public function findVisibleFor(string $ip, array $tokenHashes = []): array {
		$params = [$ip];
		$matchClauses = ['b.ip_pattern = ?', 'b.is_wildcard = 1'];

		if ($tokenHashes !== []) {
			$matchClauses[] = 'b.visitor_token_hash IN ' . pdoPlaceholdersForIn($tokenHashes);
			$params = array_merge($params, $tokenHashes);
		}

		$query = $this->baseSelect() . '
			WHERE b.revoked_at IS NULL
				AND (' . implode(' OR ', $matchClauses) . ')
			ORDER BY b.filed_at DESC';

		return $this->hydrateAll($this->queryAll($query, $params));
	}

	/** @return list<banEntry> */
	private function hydrateAll(array $rows): array {
		return array_map(fn(array $row): banEntry => banEntry::fromRow($row), $rows);
	}
}
