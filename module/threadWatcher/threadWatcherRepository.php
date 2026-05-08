<?php

namespace Kokonotsuba\Modules\threadWatcher;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for thread-watcher batch post-count queries. */
class threadWatcherRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $threadTable,
		private string $postTable,
		private string $deletedPostsTable
	) {
		parent::__construct($databaseConnection, $threadTable);
	}

	/**
	 * Fetch post counts and OP subjects for a list of thread UIDs in one query.
	 * Threads that are deleted or do not exist are omitted from the result.
	 *
	 * @param string[] $threadUids
	 * @return array Rows with keys: thread_uid, post_count, subject
	 */
	public function batchGetThreadCounts(array $threadUids): array {
		if (empty($threadUids)) {
			return [];
		}

		$placeholders = '(' . implode(', ', array_fill(0, count($threadUids), '?')) . ')';

		$query = "
			SELECT
				t.thread_uid,
				COALESCE(p_op.sub, '') AS subject,
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
}
