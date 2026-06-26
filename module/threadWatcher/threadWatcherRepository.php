<?php

namespace Kokonotsuba\Modules\threadWatcher;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for thread-watcher batch lookups (post counts, OP previews, quote-reply counts). */
class threadWatcherRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $threadTable,
		private string $postTable,
		private string $deletedPostsTable,
		private string $boardTable,
		private string $fileTable,
		private string $quoteLinkTable
	) {
		parent::__construct($databaseConnection, $threadTable);
		self::validateTableNames($postTable, $deletedPostsTable, $boardTable, $fileTable, $quoteLinkTable);
	}

	/**
	 * Fetch metadata for a list of thread UIDs in one query: live post count plus the OP's
	 * board title, subject, comment and first attachment filename (used to build a display label).
	 * Threads that are deleted or do not exist are omitted from the result.
	 *
	 * @param string[] $threadUids
	 * @return array Rows with keys: thread_uid, post_count, board_title, subject, comment, op_file_name
	 */
	public function batchGetThreadMeta(array $threadUids): array {
		if (empty($threadUids)) {
			return [];
		}

		$placeholders = '(' . implode(', ', array_fill(0, count($threadUids), '?')) . ')';

		$query = "
			SELECT
				t.thread_uid,
				COALESCE(b.board_title, '') AS board_title,
				COALESCE(p_op.sub, '')      AS subject,
				COALESCE(p_op.com, '')      AS comment,
				COALESCE((
					SELECT f.file_name
					FROM {$this->fileTable} f
					WHERE f.post_uid = t.post_op_post_uid
					  AND f.is_deleted = 0
					ORDER BY f.id ASC
					LIMIT 1
				), '') AS op_file_name,
				(
					SELECT COUNT(*)
					FROM {$this->postTable} p_cnt
					WHERE p_cnt.thread_uid = t.thread_uid
					  AND NOT EXISTS (
					      SELECT 1
					      FROM {$this->deletedPostsTable} dpx2
					      WHERE dpx2.post_uid = p_cnt.post_uid
					        AND dpx2.open_flag = 1
					        AND dpx2.file_id IS NULL
					  )
				) AS post_count
			FROM {$this->table} t
			LEFT JOIN {$this->postTable} p_op ON p_op.post_uid = t.post_op_post_uid
			LEFT JOIN {$this->boardTable} b ON b.board_uid = t.boardUID
			WHERE t.thread_uid IN {$placeholders}
			  AND NOT EXISTS (
			      SELECT 1
			      FROM {$this->deletedPostsTable} dpx
			      WHERE dpx.post_uid = t.post_op_post_uid
			        AND dpx.open_flag = 1
			        AND dpx.file_id IS NULL
			  )
		";

		return $this->queryAll($query, array_values($threadUids));
	}

	/**
	 * Count, per watched thread, how many live posts quote one of the user's own posts.
	 * Own posts are identified by their (boardUID, no) pairs so no post UID lookup is needed
	 * client-side. Self-quotes (a quoting post that is itself one of the user's own posts)
	 * and deleted quoting posts are excluded.
	 *
	 * @param string[] $threadUids Watched thread UIDs to scope the search to.
	 * @param int[][]  $ownPosts   List of [boardUID, no] pairs identifying the user's own posts.
	 * @return array<string,int> Map of thread_uid => quote-reply count (only threads with >0).
	 */
	public function batchGetQuoteCounts(array $threadUids, array $ownPosts): array {
		if (empty($threadUids) || empty($ownPosts)) {
			return [];
		}

		$threadPlaceholders = '(' . implode(', ', array_fill(0, count($threadUids), '?')) . ')';
		$pairPlaceholders = '(' . implode(', ', array_fill(0, count($ownPosts), '(?, ?)')) . ')';

		$pairParams = [];
		foreach ($ownPosts as $pair) {
			$pairParams[] = (int) $pair[0];
			$pairParams[] = (int) $pair[1];
		}

		$query = "
			SELECT host.thread_uid AS thread_uid, COUNT(*) AS quote_count
			FROM {$this->quoteLinkTable} q
			JOIN {$this->postTable} host   ON q.host_post_uid   = host.post_uid
			JOIN {$this->postTable} target ON q.target_post_uid = target.post_uid
			WHERE host.thread_uid IN {$threadPlaceholders}
			  AND (target.boardUID, target.no) IN {$pairPlaceholders}
			  AND (host.boardUID, host.no) NOT IN {$pairPlaceholders}
			  AND NOT EXISTS (
			      SELECT 1
			      FROM {$this->deletedPostsTable} dp
			      WHERE dp.post_uid = host.post_uid
			        AND dp.open_flag = 1
			        AND dp.file_id IS NULL
			  )
			GROUP BY host.thread_uid
		";

		$params = array_merge(array_values($threadUids), $pairParams, $pairParams);
		$rows = $this->queryAll($query, $params);

		$counts = [];
		foreach ($rows as $row) {
			$counts[(string) $row['thread_uid']] = (int) $row['quote_count'];
		}
		return $counts;
	}

	/**
	 * For each watched thread, return the post `no` of the first post the client hasn't
	 * seen yet, given how many posts from the top it has already read.
	 *
	 * "Seen" is a count of live posts counted from the OP down (OP = 1), matching the
	 * client's lastSeenCount. The first unread post is therefore the one at chronological
	 * position seen+1. Posts are ranked with the SAME deleted-post filter used by
	 * batchGetThreadMeta()'s post_count, so the ranking lines up with that count.
	 * Threads where seen+1 is past the live post count (nothing unread, or a stale count
	 * after deletions) simply return no row and are omitted.
	 *
	 * @param array<string,int> $seenMap thread_uid => number of posts already seen
	 * @return array<string,int> thread_uid => first-unread post number (only threads with one)
	 */
	public function batchGetFirstUnreadNo(array $seenMap): array {
		if (empty($seenMap)) {
			return [];
		}

		$threadUids = array_keys($seenMap);
		$threadPlaceholders = '(' . implode(', ', array_fill(0, count($threadUids), '?')) . ')';
		$pairPlaceholders = '(' . implode(', ', array_fill(0, count($seenMap), '(?, ?)')) . ')';

		$pairParams = [];
		foreach ($seenMap as $uid => $seen) {
			$pairParams[] = (string) $uid;
			// Chronological position (1-based) of the first post past what's been seen.
			$pairParams[] = max(0, (int) $seen) + 1;
		}

		$query = "
			SELECT ranked.thread_uid AS thread_uid, ranked.no AS no
			FROM (
				SELECT
					p.thread_uid AS thread_uid,
					p.no AS no,
					ROW_NUMBER() OVER (PARTITION BY p.thread_uid ORDER BY p.no ASC) AS rn
				FROM {$this->postTable} p
				WHERE p.thread_uid IN {$threadPlaceholders}
				  AND NOT EXISTS (
				      SELECT 1
				      FROM {$this->deletedPostsTable} dp
				      WHERE dp.post_uid = p.post_uid
				        AND dp.open_flag = 1
				        AND dp.file_id IS NULL
				  )
			) ranked
			WHERE (ranked.thread_uid, ranked.rn) IN {$pairPlaceholders}
		";

		$params = array_merge(array_map('strval', $threadUids), $pairParams);
		$rows = $this->queryAll($query, $params);

		$out = [];
		foreach ($rows as $row) {
			$out[(string) $row['thread_uid']] = (int) $row['no'];
		}
		return $out;
	}

	/** SQL fragment + params for excluding blacklisted board UIDs. Returns ['', []] when empty. */
	private function buildBlacklistClause(array $blacklist): array {
		$blacklist = array_values(array_filter(array_map('intval', $blacklist)));
		if (empty($blacklist)) {
			return ['', []];
		}
		$placeholders = implode(', ', array_fill(0, count($blacklist), '?'));
		return [" AND t.boardUID NOT IN ($placeholders)", $blacklist];
	}

	/**
	 * Return the creation time of the newest non-deleted thread across all listed boards,
	 * ignoring the blacklist. This is the client's "last seen thread" high-water marker:
	 * it must advance past blacklisted boards too, so re-enabling a board doesn't replay
	 * the threads created while it was hidden.
	 */
	public function getLatestThreadTime(): string {
		$query = "
			SELECT t.thread_created_time
			FROM {$this->table} t
			JOIN {$this->boardTable} b ON b.board_uid = t.boardUID AND b.listed = 1
			WHERE NOT EXISTS (
			    SELECT 1 FROM {$this->deletedPostsTable} dp
			    WHERE dp.post_uid = t.post_op_post_uid AND dp.open_flag = 1 AND dp.file_id IS NULL
			)
			ORDER BY t.thread_created_time DESC
			LIMIT 1
		";

		$value = $this->queryColumn($query);
		return $value !== null ? (string) $value : '';
	}

	/**
	 * Fetch threads created after $since on listed, non-blacklisted boards, newest first,
	 * with the OP's board title/identifier, subject, comment and first attachment filename.
	 * Deleted-OP threads are excluded.
	 *
	 * @param string $since     Timestamp ('YYYY-MM-DD HH:MM:SS') to fetch threads newer than.
	 * @param int[]  $blacklist Board UIDs to exclude.
	 * @param int    $limit     Max rows to return.
	 * @return array Rows with: boardUID, thread_uid, post_op_number, thread_created_time,
	 *               board_title, board_identifier, subject, comment, op_file_name
	 */
	public function getNewThreadsSince(string $since, array $blacklist, int $limit): array {
		[$blacklistSql, $blacklistParams] = $this->buildBlacklistClause($blacklist);
		$limit = max(1, min(100, $limit));

		$query = "
			SELECT
				t.boardUID,
				t.thread_uid,
				t.post_op_number,
				t.thread_created_time,
				b.board_title,
				b.board_identifier,
				COALESCE(p.sub, '') AS subject,
				COALESCE(p.com, '') AS comment,
				COALESCE((
					SELECT f.file_name
					FROM {$this->fileTable} f
					WHERE f.post_uid = t.post_op_post_uid AND f.is_deleted = 0
					ORDER BY f.id ASC
					LIMIT 1
				), '') AS op_file_name
			FROM {$this->table} t
			JOIN {$this->boardTable} b ON b.board_uid = t.boardUID AND b.listed = 1
			JOIN {$this->postTable} p ON p.post_uid = t.post_op_post_uid
			WHERE t.thread_created_time > ?
			  AND NOT EXISTS (
			      SELECT 1 FROM {$this->deletedPostsTable} dp
			      WHERE dp.post_uid = t.post_op_post_uid AND dp.open_flag = 1 AND dp.file_id IS NULL
			  )
			  {$blacklistSql}
			ORDER BY t.thread_created_time DESC
			LIMIT {$limit}
		";

		$params = array_merge([$since], $blacklistParams);
		return $this->queryAll($query, $params);
	}
}
