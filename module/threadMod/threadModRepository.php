<?php

namespace Kokonotsuba\Modules\threadMod;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\openDeletionExistsCondition;
use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/**
 * The thread_mods table, plus the two post-table reads the module needs (OP deletions are a flag
 * on the post row, not a row here).
 */
final class threadModRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $threadModTable,
		private readonly string $postTable,
		private readonly string $deletedPostsTable = ''
	) {
		parent::__construct($databaseConnection, $threadModTable);
		self::validateTableName($postTable);

		if ($deletedPostsTable !== '') {
			self::validateTableName($deletedPostsTable);
		}
	}

	/** Timestamps are written on PHP's clock, like the ban tables. */
	public static function now(): string {
		return date('Y-m-d H:i:s');
	}

	/** The live grant a browser holds on a thread, or null. */
	public function findActive(string $threadUid, string $tokenHash): ?array {
		if ($tokenHash === '') {
			return null;
		}

		$row = $this->queryOne(
			"SELECT * FROM {$this->table} WHERE thread_uid = :thread_uid AND visitor_token_hash = :hash AND revoked_at IS NULL",
			[':thread_uid' => $threadUid, ':hash' => $tokenHash]
		);

		return $row ?: null;
	}

	/** The owner row written by a transfer, or null when the OP post still says who owns it. */
	public function findActiveOwner(string $threadUid): ?array {
		$row = $this->queryOne(
			"SELECT * FROM {$this->table} WHERE thread_uid = :thread_uid AND role = :role AND revoked_at IS NULL LIMIT 1",
			[':thread_uid' => $threadUid, ':role' => threadRole::OWNER->value]
		);

		return $row ?: null;
	}

	/**
	 * Live grants on a thread, with the number of the post each was granted from.
	 *
	 * @return array<int, array>
	 */
	public function listActive(string $threadUid): array {
		return $this->queryAll(
			"SELECT m.*, p.no AS granted_from_no, p.boardUID AS granted_from_board
				FROM {$this->table} m
				LEFT JOIN {$this->postTable} p ON p.post_uid = m.granted_from_post_uid
				WHERE m.thread_uid = :thread_uid AND m.revoked_at IS NULL
				ORDER BY m.role DESC, m.granted_at ASC",
			[':thread_uid' => $threadUid]
		);
	}

	/**
	 * Which of these threads a browser holds a grant on.
	 *
	 * @param string[] $threadUids
	 * @return array<string, threadRole> thread uid => role
	 */
	public function activeRolesForToken(string $tokenHash, array $threadUids): array {
		$threadUids = array_values(array_unique(array_filter(array_map('strval', $threadUids))));

		if ($tokenHash === '' || !$threadUids) {
			return [];
		}

		$placeholders = pdoPlaceholdersForIn($threadUids);
		$rows = $this->queryAll(
			"SELECT thread_uid, role FROM {$this->table}
				WHERE visitor_token_hash = ? AND revoked_at IS NULL AND thread_uid IN $placeholders",
			array_merge([$tokenHash], $threadUids)
		);

		$roles = [];
		foreach ($rows as $row) {
			$roles[(string)$row['thread_uid']] = threadRole::fromStored($row['role']);
		}

		return $roles;
	}

	/** Write or reopen a grant. One row per thread and browser, so a re-grant updates in place. */
	public function grant(string $threadUid, string $tokenHash, threadRole $role, ?int $fromPostUid, string $byHash): void {
		$this->query(
			"INSERT INTO {$this->table}
				(thread_uid, visitor_token_hash, role, granted_from_post_uid, granted_by_hash, granted_at)
				VALUES (:thread_uid, :hash, :role, :from_post, :by_hash, :now)
				ON DUPLICATE KEY UPDATE
					role = VALUES(role),
					granted_from_post_uid = VALUES(granted_from_post_uid),
					granted_by_hash = VALUES(granted_by_hash),
					granted_at = VALUES(granted_at),
					revoked_at = NULL, revoked_by_hash = NULL, revoked_by_account = NULL",
			[
				':thread_uid' => $threadUid,
				':hash' => $tokenHash,
				':role' => $role->value,
				':from_post' => $fromPostUid,
				':by_hash' => $byHash === '' ? null : $byHash,
				':now' => self::now(),
			]
		);
	}

	/** @return int Rows closed, 0 when there was no live grant. */
	public function revoke(string $threadUid, string $tokenHash, ?string $byHash, ?int $byAccount): int {
		return $this->queryAffected(
			"UPDATE {$this->table}
				SET revoked_at = :now, revoked_by_hash = :by_hash, revoked_by_account = :by_account
				WHERE thread_uid = :thread_uid AND visitor_token_hash = :hash AND revoked_at IS NULL",
			[':now' => self::now(), ':by_hash' => $byHash, ':by_account' => $byAccount, ':thread_uid' => $threadUid, ':hash' => $tokenHash]
		);
	}

	/** Close every live grant on a thread, owner rows included. */
	public function revokeAll(string $threadUid, ?int $byAccount): int {
		return $this->queryAffected(
			"UPDATE {$this->table}
				SET revoked_at = :now, revoked_by_account = :by_account
				WHERE thread_uid = :thread_uid AND revoked_at IS NULL",
			[':now' => self::now(), ':by_account' => $byAccount, ':thread_uid' => $threadUid]
		);
	}

	/**
	 * Which of these threads have had ownership transferred (hold a live owner row).
	 *
	 * @param string[] $threadUids
	 * @return array<string, true>
	 */
	public function threadsWithOwnerRow(array $threadUids): array {
		$threadUids = array_values(array_unique(array_filter(array_map('strval', $threadUids))));

		if (!$threadUids) {
			return [];
		}

		$placeholders = pdoPlaceholdersForIn($threadUids);
		$uids = $this->queryFlatColumn(
			"SELECT thread_uid FROM {$this->table} WHERE role = ? AND revoked_at IS NULL AND thread_uid IN $placeholders",
			array_merge([threadRole::OWNER->value], $threadUids)
		);

		return array_fill_keys(array_map('strval', $uids), true);
	}

	/** On a transfer the previous owner row, if any, becomes an ordinary mod row. */
	public function demoteOwner(string $threadUid): void {
		$this->query(
			"UPDATE {$this->table} SET role = :mod WHERE thread_uid = :thread_uid AND role = :owner AND revoked_at IS NULL",
			[':mod' => threadRole::MOD->value, ':thread_uid' => $threadUid, ':owner' => threadRole::OWNER->value]
		);
	}

	/** How many posts in a thread currently carry an OP deletion. */
	public function countOpDeletions(string $threadUid): int {
		return (int)$this->queryValue(
			"SELECT COUNT(*) FROM {$this->postTable} WHERE thread_uid = :thread_uid AND status LIKE :flag",
			[':thread_uid' => $threadUid, ':flag' => '%\_' . threadModService::DELETED_FLAG . ':%']
		);
	}

	/**
	 * The thread's live replies, oldest first, for the tools page's post picker.
	 *
	 * @return array<int, array{post_uid: int, no: int, status: string}>
	 */
	public function listReplies(string $threadUid): array {
		$visible = $this->deletedPostsTable !== ''
			? ' AND NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid')
			: '';

		return $this->queryAll(
			"SELECT p.post_uid, p.no, p.status FROM {$this->postTable} p
				WHERE p.thread_uid = :thread_uid AND p.is_op = 0{$visible}
				ORDER BY p.post_uid ASC",
			[':thread_uid' => $threadUid]
		);
	}

	/** @return int[] */
	public function opDeletedPostUids(string $threadUid): array {
		return array_map('intval', $this->queryFlatColumn(
			"SELECT post_uid FROM {$this->postTable} WHERE thread_uid = :thread_uid AND status LIKE :flag",
			[':thread_uid' => $threadUid, ':flag' => '%\_' . threadModService::DELETED_FLAG . ':%']
		));
	}
}
