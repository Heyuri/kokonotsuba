<?php

namespace Kokonotsuba\Modules\catalog;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\OrderFieldWhitelistTrait;

use function Kokonotsuba\libraries\openDeletionExistsCondition;
use function Kokonotsuba\libraries\excludeDeletedThreadsCondition;

/**
 * Lightweight repository for catalog-specific queries.
 * 
 * Unlike the full threadRepository, this only fetches the minimum data
 * required for catalog display: OP subject, comment, thumbnail info, and reply count.
 */
class catalogRepository extends baseRepository {
	use OrderFieldWhitelistTrait;

	private array $allowedOrderFields;

	public function __construct(
		databaseConnection $databaseConnection,
		string $threadTable,
		private readonly string $postTable,
		private readonly string $fileTable,
		private readonly string $deletedPostsTable,
	) {
		parent::__construct($databaseConnection, $threadTable);
		self::validateTableNames($postTable, $fileTable, $deletedPostsTable);
		$this->allowedOrderFields = ['last_bump_time', 'thread_created_time'];
	}

	/**
	 * Fetch lightweight catalog entries for a board.
	 *
	 * Returns only the data needed for catalog display: thread number, OP subject,
	 * OP comment, reply count, and first attachment info for the thumbnail.
	 *
	 * @param int    $boardUID   Board UID to fetch threads from.
	 * @param int    $limit      Maximum number of threads to return.
	 * @param int    $offset     Pagination offset.
	 * @param string $orderBy    Column to sort by (validated against allowlist).
	 * @param string $direction  Sort direction ('ASC' or 'DESC').
	 * @return array Array of associative arrays with catalog entry data.
	 */
	public function getCatalogEntries(
		int $boardUID,
		int $limit,
		int $offset = 0,
		string $orderBy = 'last_bump_time',
		string $direction = 'DESC'
	): array {
		$limit = max(0, $limit);
		$offset = max(0, $offset);
		$orderBy = $this->validateOrderField($orderBy, 'last_bump_time');

		$direction = self::sortDirection($direction);

		$excludeDeleted = excludeDeletedThreadsCondition($this->deletedPostsTable);
		$replyVisible = ' AND NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'cp.post_uid');

		// Fetch thread metadata + OP post data + first attachment in a single query.
		// Uses a lateral-style subquery to grab only the first file per OP post.
		$query = "
			SELECT
				t.thread_uid,
				t.post_op_number,
				t.is_sticky,
				t.last_bump_time,
				t.thread_created_time,

				op.sub AS op_subject,
				op.com AS op_comment,
				op.text_format AS op_text_format,

				(
					SELECT COUNT(*) - 1
					FROM {$this->postTable} cp
					WHERE cp.thread_uid = t.thread_uid
					{$replyVisible}
				) AS reply_count,

				f.id AS file_id,
				f.stored_filename AS file_stored_name,
				f.file_ext AS file_extension,
				f.file_width AS file_width,
				f.file_height AS file_height,
				f.thumb_file_width AS file_thumb_width,
				f.thumb_file_height AS file_thumb_height,
				f.mime_type AS file_mime_type,
				f.is_hidden AS file_is_hidden,
				op.boardUID AS boardUID

			FROM {$this->table} t

			INNER JOIN {$this->postTable} op
				ON op.post_uid = t.post_op_post_uid

			LEFT JOIN {$this->fileTable} f
				ON f.post_uid = op.post_uid
				AND f.id = (
					-- oldest file still on the post, falling back to a deleted one when a
					-- replacement never arrived, so a file dropped and re-uploaded shows the new one
					SELECT f2.id
					FROM {$this->fileTable} f2
					WHERE f2.post_uid = op.post_uid
					ORDER BY f2.is_deleted ASC, f2.id ASC
					LIMIT 1
				)

			WHERE t.boardUID = :board_uid
			{$excludeDeleted}

			ORDER BY t.is_sticky DESC, t.{$orderBy} {$direction}
		";

		$params = [':board_uid' => $boardUID];

		if ($limit > 0) {
			$this->paginate($query, $params, $limit, $offset);
		}

		return $this->queryAll($query, $params);
	}

	/**
	 * Count total visible threads for a board.
	 *
	 * @param int $boardUID Board UID.
	 * @return int Thread count.
	 */
	public function countCatalogEntries(int $boardUID): int {
		$excludeDeleted = excludeDeletedThreadsCondition($this->deletedPostsTable);

		$query = "
			SELECT COUNT(*)
			FROM {$this->table} t
			WHERE t.boardUID = :board_uid
			{$excludeDeleted}
		";

		return (int) ($this->queryColumn($query, [':board_uid' => $boardUID]) ?? 0);
	}
}
