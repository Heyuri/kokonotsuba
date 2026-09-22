<?php

namespace Kokonotsuba\quote_link;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\excludeDeletedThreadsCondition;
use function Kokonotsuba\libraries\openDeletionExistsCondition;

/**
 * Finds the posts a text or file name quote could refer to: visible posts of one thread that
 * come before the quoting post, nearest first.
 *
 * Everything a lookup needs is asked in one statement. The replies in range, the thread's
 * opening post and the posts carrying a file of that name are three branches of one union, and
 * whether the quoting post is real, in this thread and not the opening post is a condition on
 * each of them rather than a question asked first: a lookup from a post that fails it comes back
 * empty, which is the same answer for one round trip less.
 *
 * The comparison is made on the bytes (CAST ... AS BINARY) rather than in the column's own
 * collation, which is accent- and case-insensitive: a quote is the text somebody copied, so
 * "cafe" is not "café". That is also what textQuoteMatcher does in PHP, so the rows that come
 * back are the rows that will be accepted rather than a looser set to be whittled down - which
 * is most of the cost of a search that finds nothing.
 */
class textQuoteRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $postTable,
		private readonly string $threadTable,
		private readonly string $deletedPostsTable,
		private readonly string $fileTable
	) {
		parent::__construct($databaseConnection, $postTable);
		self::validateTableNames($threadTable, $deletedPostsTable, $fileTable);
	}

	/**
	 * Everything that could be the source of a quote, in one statement.
	 *
	 * Only the newest $window replies before the quoting post are looked at, so the cost of a
	 * quote that matches nothing is bounded however long the thread is. A row with a file_name
	 * is a post carrying a file of that name; the rest are posts whose comment holds the text.
	 *
	 * @param string[] $needles    Needles to match anywhere in a comment.
	 * @param array{0: string, 1: string}|null $fileName The quoted name split into name and extension.
	 * @return array<int, array{post_uid: int, is_op: int, com: string, text_format: int, file_name: ?string, file_ext: ?string}>
	 */
	public function findCandidates(
		string $threadUid,
		int $beforeUid,
		array $needles,
		int $window,
		int $limit,
		bool $withOpeningPost,
		?array $fileName = null
	): array {
		$window = max(1, $window);
		$limit = max(1, $limit);

		$notDeleted = 'NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');
		$liveThread = excludeDeletedThreadsCondition($this->deletedPostsTable);

		[$replyLike, $params] = $this->holdsAnyNeedle($needles, 'r');
		$params[':thread_uid'] = $threadUid;
		$params[':before_uid'] = $beforeUid;

		$branches = ["
			(SELECT p.post_uid, p.is_op, p.com, p.text_format, NULL AS file_name, NULL AS file_ext
			 FROM (
				SELECT w.post_uid
				FROM {$this->table} w
				WHERE w.thread_uid = :thread_uid AND w.is_op = 0 AND w.post_uid < :before_uid
				  AND {$this->quotingPostExists(':thread_uid_guard', ':before_uid_guard')}
				ORDER BY w.post_uid DESC
				LIMIT {$window}
			 ) recent
			 INNER JOIN {$this->table} p ON p.post_uid = recent.post_uid
			 INNER JOIN {$this->threadTable} t ON t.thread_uid = p.thread_uid
			 WHERE ({$replyLike}) AND {$notDeleted} {$liveThread}
			 ORDER BY p.post_uid DESC
			 LIMIT {$limit})"];

		$params[':thread_uid_guard'] = $threadUid;
		$params[':before_uid_guard'] = $beforeUid;

		if ($withOpeningPost) {
			[$openingLike, $openingParams] = $this->holdsAnyNeedle($needles, 'o');
			$params += $openingParams;
			$params[':thread_uid_op'] = $threadUid;
			$params[':thread_uid_op_guard'] = $threadUid;
			$params[':before_uid_op_guard'] = $beforeUid;

			$branches[] = "
			(SELECT p.post_uid, p.is_op, p.com, p.text_format, NULL AS file_name, NULL AS file_ext
			 FROM {$this->table} p
			 INNER JOIN {$this->threadTable} t ON t.thread_uid = p.thread_uid
			 WHERE p.thread_uid = :thread_uid_op AND p.is_op = 1 AND ({$openingLike})
			   AND {$this->quotingPostExists(':thread_uid_op_guard', ':before_uid_op_guard')}
			   {$liveThread}
			 LIMIT 1)";
		}

		if ($fileName !== null) {
			$params[':thread_uid_file'] = $threadUid;
			$params[':before_uid_file'] = $beforeUid;
			$params[':file_name'] = $fileName[0];
			$params[':file_ext'] = $fileName[1];
			$params[':file_ext_dotted'] = '.' . $fileName[1];
			$params[':thread_uid_file_guard'] = $threadUid;
			$params[':before_uid_file_guard'] = $beforeUid;

			$branches[] = "
			(SELECT p.post_uid, p.is_op, p.com, p.text_format, f.file_name, f.file_ext
			 FROM {$this->table} p
			 INNER JOIN {$this->threadTable} t ON t.thread_uid = p.thread_uid
			 INNER JOIN {$this->fileTable} f ON f.post_uid = p.post_uid
			 WHERE p.thread_uid = :thread_uid_file
			   AND (p.is_op = 1 OR p.post_uid < :before_uid_file)
			   AND p.post_uid <> :before_uid_file
			   AND f.file_name = :file_name
			   AND f.file_ext IN (:file_ext, :file_ext_dotted)
			   AND f.is_hidden = 0
			   AND {$this->quotingPostExists(':thread_uid_file_guard', ':before_uid_file_guard')}
			   AND {$notDeleted} {$liveThread}
			 ORDER BY p.is_op ASC, p.post_uid DESC
			 LIMIT {$limit})";
		}

		if (count($branches) === 1) {
			return $this->queryAll($branches[0], $params);
		}

		// nearest first: replies by uid, then the opening post, and a file row after the comment
		// row of the same post, since a comment holding the text is the better answer
		$query = 'SELECT c.* FROM (' . implode(' UNION ALL ', $branches)
			. ') c ORDER BY c.is_op ASC, c.post_uid DESC, c.file_name IS NOT NULL ASC';

		return $this->queryAll($query, $params);
	}

	/**
	 * A visible post of the thread by its number, when it comes before the quoting post.
	 *
	 * @return int|null The post uid.
	 */
	public function findPostUidByNumber(string $threadUid, int $beforeUid, int $postNumber): ?int {
		$query = "
			SELECT p.post_uid
			FROM {$this->table} p
			INNER JOIN {$this->threadTable} t ON t.thread_uid = p.thread_uid
			WHERE p.thread_uid = :thread_uid
			  AND p.no = :post_number
			  AND (p.is_op = 1 OR p.post_uid < :before_uid)
			  AND p.post_uid <> :before_uid
			  AND {$this->quotingPostExists(':thread_uid_guard', ':before_uid_guard')}
			  AND NOT " . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid') . "
			  " . excludeDeletedThreadsCondition($this->deletedPostsTable) . "
			LIMIT 1";

		$postUid = $this->queryValue($query, [
			':thread_uid' => $threadUid,
			':post_number' => $postNumber,
			':before_uid' => $beforeUid,
			':thread_uid_guard' => $threadUid,
			':before_uid_guard' => $beforeUid,
		]);

		return $postUid ? (int)$postUid : null;
	}

	/**
	 * The condition that makes a lookup legitimate: the post the quote was typed in exists, is
	 * a reply of the thread it names, and so has posts before it.
	 */
	private function quotingPostExists(string $threadParam, string $beforeParam): string {
		return "EXISTS (
			SELECT 1 FROM {$this->table} q
			WHERE q.post_uid = {$beforeParam} AND q.thread_uid = {$threadParam} AND q.is_op = 0
		)";
	}

	/**
	 * Byte-exact "holds this text" conditions, one per needle, OR'd together.
	 *
	 * @param string[] $needles
	 * @return array{0: string, 1: array<string, string>} The conditions and their parameters.
	 */
	private function holdsAnyNeedle(array $needles, string $prefix): array {
		$conditions = [];
		$params = [];

		foreach (array_values($needles) as $i => $needle) {
			$conditions[] = "CAST(p.com AS BINARY) LIKE CAST(:{$prefix}_like_{$i} AS BINARY)";
			$params[":{$prefix}_like_{$i}"] = textQuoteMatcher::likePattern($needle);
		}

		return [$conditions ? implode(' OR ', $conditions) : '0', $params];
	}
}
