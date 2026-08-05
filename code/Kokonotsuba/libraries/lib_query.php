<?php

namespace Kokonotsuba\libraries;

use Kokonotsuba\post\Post;
use Kokonotsuba\post\deletion\DeletedPost;

/**
 * Attachment column list selected from two file-table aliases at once, taking whichever of the two
 * matched. Used by the deletion-centric query, where an attachment is reached either by file id
 * (file-only deletions) or by post uid (post-level deletions) — see getBasePostQuery().
 *
 * @param string $fileIdAlias  Alias joined on the file's primary key.
 * @param string $postUidAlias Alias joined on the file's post uid.
 * @return string Comma-separated select list, aliased identically to the single-table version.
 */
function coalescedAttachmentColumns(string $fileIdAlias, string $postUidAlias): string {
	$columns = [
		'id'                => 'attachment_id',
		'file_name'         => 'attachment_file_name',
		'stored_filename'   => 'attachment_stored_filename',
		'file_ext'          => 'attachment_file_ext',
		'file_md5'          => 'attachment_file_md5',
		'file_size'         => 'attachment_file_size',
		'file_width'        => 'attachment_file_width',
		'file_height'       => 'attachment_file_height',
		'thumb_file_width'  => 'attachment_thumb_width',
		'thumb_file_height' => 'attachment_thumb_height',
		'mime_type'         => 'attachment_mime_type',
		'is_hidden'         => 'attachment_is_hidden',
		'is_animated'       => 'attachment_is_animated',
		'is_spoilered'      => 'attachment_is_spoilered',
		'is_deleted'        => 'attachment_is_deleted',
		'timestamp_added'   => 'attachment_timestamp_added',
	];

	$select = [];

	foreach ($columns as $column => $alias) {
		$select[] = "\t\t\tCOALESCE({$fileIdAlias}.{$column}, {$postUidAlias}.{$column}) AS {$alias}";
	}

	return ltrim(implode(",\n", $select));
}

/**
 * Generate the base query for posts or deleted posts.
 *
 * In post-centric mode (default), the posts table is the main FROM and
 * deleted posts are LEFT JOINed. Soudane and staff notes are included.
 *
 * In deletion-centric mode, the deleted_posts table is the main FROM and
 * posts are LEFT JOINed. Account usernames for deleted_by/restored_by are
 * included instead of soudane/notes.
 *
 * @param string $postTable
 * @param string $deletedPostsTable
 * @param string $fileTable
 * @param string $threadTable
 * @param string $soudaneTable
 * @param string $noteTable
 * @param string $accountTable
 * @param bool   $viewDeleted      Whether to include deleted posts (post-centric only)
 * @param bool   $deletionCentric  If true, query from deleted_posts perspective
 * @param string $reportTable      Reports table, or '' to leave the pending-report count out.
 * @param bool   $includeObjectivePosition Select the objective_position column (post-centric only).
 *                                 It is a correlated COUNT(*) evaluated per result row, so pass
 *                                 false when the caller follows up with
 *                                 threadRepository::attachObjectivePositions(), which derives the
 *                                 same values in one window-function query and overwrites these.
 * @return string
 */
function getBasePostQuery(
    string $postTable,
    string $deletedPostsTable,
    string $fileTable,
    string $threadTable,
	string $soudaneTable,
	string $noteTable,
	string $accountTable,
    bool $viewDeleted = false,
	bool $deletionCentric = false,
	string $countryFlagTable = '',
	string $displayIpTable = '',
	bool $includeObjectivePosition = true,
	string $reportTable = ''
): string {

	// Shared column definitions
	$attachmentColumns = "
			f.id                 AS attachment_id,
			f.file_name          AS attachment_file_name,
			f.stored_filename    AS attachment_stored_filename,
			f.file_ext           AS attachment_file_ext,
			f.file_md5           AS attachment_file_md5,
			f.file_size          AS attachment_file_size,
			f.file_width         AS attachment_file_width,
			f.file_height        AS attachment_file_height,
			f.thumb_file_width   AS attachment_thumb_width,
			f.thumb_file_height  AS attachment_thumb_height,
			f.mime_type          AS attachment_mime_type,
			f.is_hidden          AS attachment_is_hidden,
			f.is_animated        AS attachment_is_animated,
			f.is_spoilered       AS attachment_is_spoilered,
			f.is_deleted         AS attachment_is_deleted,
			f.timestamp_added    AS attachment_timestamp_added";

	$deletedPostColumns = "
			dp.open_flag,
			dp.id                AS deleted_post_id,
			dp.deleted_at,
			dp.deleted_by,
			dp.restored_at,
			dp.by_proxy,
			dp.file_only         AS file_only_deleted,
			dp.file_id";

	$noteColumns = "
			n.id AS note_id,
			n.note_submitted,
			n.added_by AS note_added_by,
			n.note_text";

	// Shared JOIN fragments
	$noteJoin = "
		LEFT JOIN {$noteTable} n";

	// Deletion-centric mode: deleted_posts is the main table
	if ($deletionCentric) {
		// A file-only deletion names one specific attachment (dp.file_id); a post-level deletion
		// takes every attachment on the post. Expressing that as a single join with an OR of two
		// different keys forces a full scan of the file table per deleted_posts row, so split it
		// into two ref-joined aliases — fk (by file id, primary key) and pk (by post uid,
		// idx_post_uid) — and coalesce the columns back together. Exactly one alias can match a
		// given row, because their conditions are mutually exclusive on dp.file_id.
		$attachmentColumnsCoalesced = coalescedAttachmentColumns('fk', 'pk');

		// The shared soudane join is a derived table aggregating the entire votes table with no
		// filter, which MariaDB cannot merge into the outer query and so materializes in full on
		// every call. These pages only ever show a page's worth of posts, so use correlated
		// subqueries instead — they ride idx_soudane_vote (post_uid, yeah) and run per result row.
		// SUM over no rows yields NULL, matching the miss behaviour of the LEFT JOIN they replace.
		$soudaneSubqueryColumns = "
			(SELECT SUM(CASE WHEN sv.yeah = 1 THEN 1 ELSE -1 END)
				FROM {$soudaneTable} sv WHERE sv.post_uid = dp.post_uid) AS votes_total_count,
			(SELECT COUNT(*)
				FROM {$soudaneTable} sv WHERE sv.post_uid = dp.post_uid AND sv.yeah = 1) AS votes_yeah_count,
			(SELECT COUNT(*)
				FROM {$soudaneTable} sv WHERE sv.post_uid = dp.post_uid AND sv.yeah = 0) AS votes_nope_count";

		return "
			SELECT
				{$deletedPostColumns},
				dp.post_uid,
				dp.restored_by,

				p.*,
				t.post_op_number,

				{$attachmentColumnsCoalesced},

				da.username AS deleted_by_username,
				ra.username AS restored_by_username,

				{$soudaneSubqueryColumns},

				{$noteColumns},
				na.username AS note_added_by_username

			FROM {$deletedPostsTable} dp

			LEFT JOIN {$postTable} p
				ON p.post_uid = dp.post_uid

			LEFT JOIN {$fileTable} fk
				ON fk.id = dp.file_id

			LEFT JOIN {$fileTable} pk
				ON pk.post_uid = dp.post_uid AND dp.file_id IS NULL

			LEFT JOIN {$accountTable} da ON dp.deleted_by = da.id
			LEFT JOIN {$accountTable} ra ON dp.restored_by = ra.id

			LEFT JOIN {$threadTable} t ON p.thread_uid = t.thread_uid

			{$noteJoin} ON n.post_uid = dp.post_uid
			LEFT JOIN {$accountTable} na ON na.id = n.added_by
		";
	}

	// Post-centric mode: posts table is the main FROM

    // Base subquery: filtered posts (excludes deleted if $viewDeleted = false)
    $postFilterSubquery = $viewDeleted
        ? "SELECT * FROM $postTable"
        : "
        SELECT p1.*
        FROM $postTable p1
        WHERE NOT " . openDeletionExistsCondition($deletedPostsTable, 'p1.post_uid');

    // Main query: join threads, attachments, and all deletion rows (for mergeRowIntoPost)
	$countryFlagColumn = $countryFlagTable ? " cf.country AS country_flag_country," : '';
	$countryFlagJoin   = $countryFlagTable ? " LEFT JOIN $countryFlagTable cf ON cf.post_uid = p.post_uid" : '';
	$displayIpColumn   = $displayIpTable ? " dip.ip_part AS display_ip_ip_part," : '';
	$displayIpJoin     = $displayIpTable ? " LEFT JOIN $displayIpTable dip ON dip.post_uid = p.post_uid" : '';

	// The vote aggregate used to be a LEFT JOIN onto a derived table that grouped the entire
	// soudane table with no filter. MariaDB can neither merge a GROUP BY derived table into the
	// outer query nor push the caller's WHERE through it, so every call materialised every vote in
	// the database *and* left the posts scan unfiltered. Correlated subqueries ride
	// idx_soudane_vote (post_uid, yeah) and run per result row instead. SUM over no rows yields
	// NULL, matching the miss behaviour of the LEFT JOIN they replace - mergeRowIntoPost() reads a
	// NULL votes_total_count as "this post has no votes".
	$soudaneColumns = "
			(SELECT SUM(CASE WHEN sv.yeah = 1 THEN 1 ELSE -1 END)
				FROM {$soudaneTable} sv WHERE sv.post_uid = p.post_uid) AS votes_total_count,
			(SELECT SUM(CASE WHEN sv.yeah = 1 THEN 1 ELSE 0 END)
				FROM {$soudaneTable} sv WHERE sv.post_uid = p.post_uid) AS votes_yeah_count,
			(SELECT SUM(CASE WHEN sv.yeah = 0 THEN 1 ELSE 0 END)
				FROM {$soudaneTable} sv WHERE sv.post_uid = p.post_uid) AS votes_nope_count";

	// True ordinal within the thread (OP = 0), used for pagination page math instead of
	// the drift-prone stored post_position column. Correlated COUNT(*) with a nested EXISTS,
	// evaluated once per result row - see $includeObjectivePosition.
	$objectivePositionColumn = $includeObjectivePosition
		? objectivePositionSubquery($postTable, $deletedPostsTable, 'p', $viewDeleted) . ' AS objective_position,'
		: '';

	// Lets the report module flag a reported post wherever posts are rendered, without a query
	// per post. Correlated like the soudane counts above so it rides idx_reports_post_uid;
	// empty when no reports table is configured, so the join is opt-out.
	$reportColumn = $reportTable
		? "(SELECT COUNT(*) FROM {$reportTable} rp
				WHERE rp.post_uid = p.post_uid AND rp.status = 0) AS pending_report_count,"
		: '';

    $query = "
        SELECT
            p.*,
            {$objectivePositionColumn}
            t.post_op_number,
			{$countryFlagColumn}
			{$displayIpColumn}
			{$reportColumn}

            {$attachmentColumns},

            {$deletedPostColumns},

			{$soudaneColumns},

			{$noteColumns},
			a.username AS note_added_by_username

        FROM ($postFilterSubquery) p

        LEFT JOIN $fileTable f ON f.post_uid = p.post_uid

		{$noteJoin} ON n.post_uid = p.post_uid
		LEFT JOIN $accountTable a ON a.id = n.added_by

        -- Thread info
        INNER JOIN $threadTable t ON p.thread_uid = t.thread_uid

        -- Open deletion entries: the post-level one if the post is deleted, otherwise every
        -- attachment-level one, so mergeRowIntoPost() can mark individually deleted files.
		LEFT JOIN $deletedPostsTable dp
		ON dp.post_uid = p.post_uid
		AND dp.open_flag = 1
		AND (
			dp.open_key = p.post_uid
			OR NOT " . openDeletionExistsCondition($deletedPostsTable, 'p.post_uid') . "
		)
		{$countryFlagJoin}
		{$displayIpJoin}
    ";

    return $query;
}

/**
 * Build a correlated subquery that yields a post's *objective position* within its
 * thread: the OP is 0, and each reply is numbered by its ordinal among the currently
 * visible replies ordered by post_uid ASC (1 = first reply, 2 = second, ...).
 *
 * This is the position pagination actually slices on. The stored `post_position`
 * column is a monotonic insert counter (MAX+1) that is never decremented when a post
 * is soft-deleted, so it drifts out of sync with the rendered order after deletions —
 * use this instead of `post_position` for any page-number math.
 *
 * @param string $postTable        Posts table name.
 * @param string $deletedPostsTable Deleted-posts table name.
 * @param string $postAlias        Alias of the post row to position (e.g. 'p', 'tp').
 * @param bool   $viewDeleted      When true, deleted replies are counted too (matches
 *                                 admin/deleted-visible pagination); when false they are
 *                                 excluded, matching the default rendered thread.
 * @return string A parenthesized scalar subquery (no trailing alias).
 */
function objectivePositionSubquery(
	string $postTable,
	string $deletedPostsTable,
	string $postAlias,
	bool $viewDeleted = false
): string {
	$deletionFilter = $viewDeleted
		? ''
		: "\n\t\t\t  AND NOT " . openDeletionExistsCondition($deletedPostsTable, 'objpos.post_uid');

	return "(
			SELECT COUNT(*)
			FROM $postTable objpos
			WHERE objpos.thread_uid = {$postAlias}.thread_uid
			  AND objpos.is_op = 0
			  AND objpos.post_uid <= {$postAlias}.post_uid
			  {$deletionFilter}
		)";
}

/*
 * ---------------------------------------------------------------------------
 * Deletion-state predicates
 *
 * A post is hidden exactly when an *open post-level* deletion record exists for
 * it: one that has not been restored (restored_at IS NULL) and that deletes the
 * post rather than a single attachment (file_id IS NULL).
 *
 * The deletedPosts table keeps every deletion and restore a post has ever been
 * through, so several rows can share a post_uid and only one of them describes
 * the post's state right now. Rather than re-derive that at read time — which
 * used to mean grouping the whole table by post_uid to find each post's newest
 * row — lean on the schema: the STORED generated column
 *
 *     open_key = CASE WHEN restored_at IS NULL AND file_id IS NULL
 *                     THEN post_uid ELSE NULL END
 *
 * carries a UNIQUE index (uq_open_post). Repeated NULLs are permitted in a
 * MySQL unique index, so history rows coexist freely while the database
 * guarantees at most one open post-level row per post. That makes
 * "open_key = <post uid>" both exact and a single unique-index probe, and it
 * removes any notion of a 'latest' row from the read path entirely.
 *
 * Every visibility test in the codebase goes through the helpers below so the
 * rule is stated once. Do not hand-roll another one.
 * ---------------------------------------------------------------------------
 */

/**
 * Condition matching posts that currently have an open post-level deletion.
 *
 * @param string $deletedPostsTable Deleted-posts table name.
 * @param string $postUidExpr       SQL expression yielding the post uid to test (e.g. 'p.post_uid').
 * @return string A bare EXISTS(...) condition, with no leading boolean operator.
 */
function openDeletionExistsCondition(string $deletedPostsTable, string $postUidExpr): string {
	return "EXISTS (
		SELECT 1 FROM $deletedPostsTable WHERE open_key = $postUidExpr
	)";
}

/**
 * Join that attaches a post's open post-level deletion row, or nothing when it is visible.
 * The join is a lookup on uq_open_post and can match at most one row, so it never fans out.
 *
 * @param string $deletedPostsTable Deleted-posts table name.
 * @param string $postUidExpr       SQL expression yielding the post uid to join on.
 * @param string $alias             Alias to give the joined deleted-posts row.
 * @return string A LEFT JOIN clause.
 */
function openDeletionJoin(string $deletedPostsTable, string $postUidExpr, string $alias): string {
	return "LEFT JOIN $deletedPostsTable $alias ON $alias.open_key = $postUidExpr";
}

/**
 * Condition matching posts that have an open *attachment-level* deletion — one or more of their
 * files were deleted while the post itself stayed visible. Distinct from
 * openDeletionExistsCondition(): the two are independent, and a post can satisfy both.
 *
 * @param string $deletedPostsTable Deleted-posts table name.
 * @param string $postUidExpr       SQL expression yielding the post uid to test.
 * @return string A bare EXISTS(...) condition, with no leading boolean operator.
 */
function openFileDeletionExistsCondition(string $deletedPostsTable, string $postUidExpr): string {
	return "EXISTS (
		SELECT 1 FROM $deletedPostsTable
		WHERE post_uid = $postUidExpr AND open_flag = 1 AND file_id IS NOT NULL
	)";
}

/**
 * Restrict a thread query to threads whose OP post is not deleted. Expects the thread table
 * aliased as 't', matching every call site.
 *
 * @param string $deletedPostsTable Deleted-posts table name.
 * @return string A condition prefixed with ' AND ', for appending to an existing WHERE clause.
 */
function excludeDeletedThreadsCondition(string $deletedPostsTable): string {
	return ' AND NOT ' . openDeletionExistsCondition($deletedPostsTable, 't.post_op_post_uid');
}

/**
 * Restrict a query to visible posts, given a deleted-posts row already attached by
 * openDeletionJoin(). The join only ever matches open post-level deletions, so the row being
 * absent is exactly the post being visible.
 *
 * @param string $alias Alias used for the openDeletionJoin() row.
 * @return string A condition prefixed with ' AND ', for appending to an existing WHERE clause.
 */
function excludeDeletedPostsCondition(string $alias = 'dp'): string {
	return " AND $alias.open_key IS NULL";
}

/**
 * Build a normalized attachment array from a database row.
 *
 * Extracts attachment-related fields from a flat row (post + attachment fields)
 * and returns them in a structured, consistent format.
 *
 * @param array $row  The database row containing post and attachment columns.
 * @return array       Normalized attachment data.
 */
function buildAttachment(array $row): array {
	return [
		'fileId'         => $row['attachment_id'],
		'fileName'       => $row['attachment_file_name'],
		'storedFileName' => $row['attachment_stored_filename'],
		'fileExtension'  => $row['attachment_file_ext'],
		'fileMd5'        => $row['attachment_file_md5'],
		'fileSize'       => $row['attachment_file_size'],
		'fileWidth'      => $row['attachment_file_width'],
		'fileHeight'     => $row['attachment_file_height'],
		'thumbWidth'     => $row['attachment_thumb_width'],
		'thumbHeight'    => $row['attachment_thumb_height'],
		'mimeType'       => $row['attachment_mime_type'],
		'isHidden'       => $row['attachment_is_hidden'],
		'isAnimated'     => $row['attachment_is_animated'],
		'isSpoilered'    => $row['attachment_is_spoilered'],
		'isDeleted'      => $row['attachment_is_deleted'],
		'onlyFileDeleted' => $row['file_only_deleted'] ?? false,
		'timestampAdded' => $row['attachment_timestamp_added'],
		'postUid'        => $row['post_uid'],
		'boardUID'       => $row['boardUID'],
		'isLegacy'       => false
	];
}

/**
 * Merge multiple SQL rows representing the same post into a single
 * structured post array containing:
 *  - post-level data
 *  - attachments[]
 *  - deleted_attachments[]
 *
 * @param false|array $rows
 * @return false|Post[]
 */
function mergeMultiplePostRows(null|false|array $rows): false|array {
	if (!$rows) {
		return false;
	}

	$posts = [];

	foreach ($rows as $row) {
		$uid = $row['post_uid'];

		// Initialize the Post object if we haven't seen this uid yet
		if (!isset($posts[$uid])) {
			$posts[$uid] = new Post($row);
		}

		// Merge attachment, deletion, and note data from this row
		mergeRowIntoPost($posts[$uid], $row);

		// soudane data
		if (isset($row['votes_total_count']) && $row['votes_total_count'] !== null) {
			$posts[$uid]->setVotes([
				'total_score' => $row['votes_total_count'],
				'yeah_count' => $row['votes_yeah_count'],
				'nope_count' => $row['votes_nope_count']
			]);
		}
	}

	// apply deletion meta data to attachments
	applyDeletionMetadata($posts);

	// Return flat array of merged posts
	return array_values($posts);
}

/**
 * Merge a single $row into a post structure:
 *  - ensures attachments[] exists
 *  - ensures deleted_attachments[] exists
 *  - merges attachment data
 *  - merges deleted-attachment metadata
 *  - strips attachment_* columns
 *
 * @param Post $target The post entry being built
 * @param array $row    The SQL row
 */
function mergeRowIntoPost(Post &$target, array $row): void {
	// normal attachments
	if (!empty($row['attachment_id'])) {
		$target->addAttachment($row['attachment_id'], buildAttachment($row));
	}

	// deleted attachments
	if (!empty($row['file_id'])) {
		$target->addDeletedAttachment($row['file_id'], [
			'deleted_post_id'   => $row['deleted_post_id'] ?? null,
			'deleted_by'        => $row['deleted_by'] ?? null,
			'deleted_at'        => $row['deleted_at'] ?? null,
			'restored_at'       => $row['restored_at'] ?? null,
			'file_only_deleted' => (bool)($row['file_only_deleted'] ?? false),
			'by_proxy'          => (bool)($row['by_proxy'] ?? false),
		]);
	}

	// staff notes
	if (!empty($row['note_id'])) {
		$target->addStaffNote($row['note_id'], [
			'id'                     => $row['note_id'],
			'note_submitted'         => $row['note_submitted'],
			'note_added_by'          => $row['note_added_by'],
			'note_text'              => $row['note_text'],
			'note_added_by_username' => $row['note_added_by_username'],
		]);
	}
}

/**
 * Remove all columns in $cols from a post row.
 *
 * @param array &$row  Row to clean (modified in-place)
 * @param array $cols   Columns to remove
 */
function stripColumns(array &$row, array $cols): void {
	foreach ($cols as $c) {
		unset($row[$c]);
	}
}

function stripAttachmentColumns(array &$row): void {
	stripColumns($row, [
		'attachment_id',
		'attachment_file_name',
		'attachment_stored_filename',
		'attachment_file_ext',
		'attachment_file_md5',
		'attachment_file_size',
		'attachment_file_width',
		'attachment_file_height',
		'attachment_thumb_width',
		'attachment_thumb_height',
		'attachment_mime_type',
		'attachment_is_hidden',
		'attachment_is_animated',
		'attachment_is_spoilered',
		'attachment_is_deleted',
		'attachment_timestamp_added',
	]);
}

function stripSoudaneColumns(array &$row): void {
	stripColumns($row, [
		'votes_total_count',
		'votes_yeah_count',
		'votes_nope_count'
	]);
}

function stripNoteColumns(array &$row): void {
    stripColumns($row, [
        'note_id',
        'note_submitted',
        'note_added_by',
        'note_text'
    ]);
}

function applyDeletionMetadata(array &$posts): void {
	foreach ($posts as $post) {
		$attachments = $post->getAttachments();
		$deletedAttachments = $post->getDeletedAttachments();
		if (!empty($attachments)) {
			foreach ($attachments as $attachmentId => &$att) {
				$att['deletedPostId'] =
					$deletedAttachments[$attachmentId]['deleted_post_id']
					?? null;
			}
			$post->setAttachments($attachments);
		}
	}
}

/**
 * Merge rows for deleted_posts entries into one structure per deleted_post_id.
 *
 * This is used for the deleted-posts (DP) pages where we want one row per
 * deleted_posts.id (dp row), not one row per post_uid.
 *
 * For each dp row:
 *  - the base dp + post columns are kept
 *  - attachments[] is filled (if attachment_id present)
 *  - deleted_attachments[] is filled (if file_id present)
 *
 * @param null|false|array $rows
 * @return false|array
 */
function mergeDeletedPostRows(null|false|array $rows): false|array {
	if (!$rows) {
		return false;
	}

	$entries = [];

	foreach ($rows as $row) {
		// Group by dp.id (aliased as deleted_post_id in your SELECT)
		$dpId = $row['deleted_post_id'] ?? null;

		// Fallback: if for some reason alias missing, group by post_uid
		if ($dpId === null && isset($row['post_uid'])) {
			$dpId = $row['post_uid'];
		}

		if ($dpId === null) {
			continue;
		}

		// Initialize DeletedPost object if we haven't seen this entry yet
		if (!isset($entries[$dpId])) {
			$entries[$dpId] = new DeletedPost($row);
		}

		// Reuse existing logic for wiring up attachments + deleted_attachments
		mergeRowIntoPost($entries[$dpId], $row);

		// soudane data — set the same way mergeMultiplePostRows() does, so a deleted post renders
		// its vote counts like any other post
		if (isset($row['votes_total_count']) && $row['votes_total_count'] !== null) {
			$entries[$dpId]->setVotes([
				'total_score' => $row['votes_total_count'],
				'yeah_count' => $row['votes_yeah_count'],
				'nope_count' => $row['votes_nope_count']
			]);
		}
	}

	return array_values($entries);
}

// sqlLatestDeletionEntry() lived here. It built a derived table of each post's newest deletion
// row so callers could read state off it; use openDeletionJoin() instead, which gets the same
// answer from uq_open_post without grouping the table. See the deletion-state predicates above.
