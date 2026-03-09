<?php

namespace Kokonotsuba\libraries;

/**
 * Generate the base query for posts, optionally hiding deleted posts.
 *
 * @param string $postTable
 * @param string $deletedPostsTable
 * @param string $fileTable
 * @param string $threadTable
 * @param bool $viewDeleted  Whether to include deleted posts (default false)
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
    bool $viewDeleted = false
): string {

    // Base subquery: filtered posts (excludes deleted if $viewDeleted = false)
    $postFilterSubquery = $viewDeleted
        ? "SELECT * FROM $postTable"
        : "
        SELECT p1.*
        FROM $postTable p1
        LEFT JOIN (
            -- latest post-level deletions only
            SELECT d1.post_uid
            FROM $deletedPostsTable d1
            INNER JOIN (
                SELECT post_uid, MAX(id) AS max_id
                FROM $deletedPostsTable
                GROUP BY post_uid
            ) d2 ON d1.post_uid = d2.post_uid AND d1.id = d2.max_id
            WHERE d1.file_id IS NULL AND d1.open_flag = 1
        ) deleted_latest ON deleted_latest.post_uid = p1.post_uid
        WHERE deleted_latest.post_uid IS NULL
        ";

    // Main query: join threads, attachments, and all deletion rows (for mergeRowIntoPost)
    $query = "
        SELECT 
            p.*,
            
            -- Thread info
            t.post_op_number,
            
            -- Attachments
            f.id AS attachment_id,
            f.file_name AS attachment_file_name,
            f.stored_filename AS attachment_stored_filename,
            f.file_ext AS attachment_file_ext,
            f.file_md5 AS attachment_file_md5,
            f.file_size AS attachment_file_size,
            f.file_width AS attachment_file_width,
            f.file_height AS attachment_file_height,
            f.thumb_file_width AS attachment_thumb_width,
            f.thumb_file_height AS attachment_thumb_height,
            f.mime_type AS attachment_mime_type,
            f.is_hidden AS attachment_is_hidden,
            f.is_animated AS attachment_is_animated,
            f.is_deleted AS attachment_is_deleted,
            f.timestamp_added AS attachment_timestamp_added,
            
            -- Deleted post info (all rows)
            dp.open_flag,
            dp.file_only AS file_only_deleted,
            dp.id AS deleted_post_id,
            dp.by_proxy,
            dp.file_id,
            dp.deleted_by,
            dp.deleted_at,
            dp.restored_at,

			-- soudane
			sv.votes_total_count,
			sv.votes_yeah_count,
			sv.votes_nope_count,

			-- Staff notes
			n.id AS note_id,
			n.note_submitted,
			n.added_by AS note_added_by,
			a.username AS note_added_by_username,
			n.note_text

        FROM ($postFilterSubquery) p

        -- Attachments
        LEFT JOIN $fileTable f ON f.post_uid = p.post_uid

		-- vote data
		LEFT JOIN (
			SELECT
				post_uid,
				COUNT(*) AS vote_rows,
				SUM(CASE WHEN yeah = 1 THEN 1 ELSE 0 END) AS votes_yeah_count,
				SUM(CASE WHEN yeah = 0 THEN 1 ELSE 0 END) AS votes_nope_count,
				SUM(CASE WHEN yeah = 1 THEN 1 ELSE -1 END) AS votes_total_count
			FROM $soudaneTable
			GROUP BY post_uid
		) sv ON sv.post_uid = p.post_uid

		-- Staff notes
		LEFT JOIN $noteTable n ON n.post_uid = p.post_uid
		
		-- Staff note author
		LEFT JOIN $accountTable a ON a.id = n.added_by

        -- Thread info
        INNER JOIN $threadTable t ON p.thread_uid = t.thread_uid

        -- All deletion entries
		LEFT JOIN $deletedPostsTable dp
		ON dp.post_uid = p.post_uid
		AND dp.open_flag = 1
		AND (
			dp.file_id IS NULL
			OR NOT EXISTS (
				SELECT 1
				FROM $deletedPostsTable dp2
				WHERE dp2.post_uid = p.post_uid
					AND dp2.file_id IS NULL
					AND dp2.open_flag = 1
			)
		)
    ";

    return $query;
}

function excludeDeletedThreadsCondition(string $deletedPostsTable): string {
	return " AND NOT EXISTS (
		SELECT 1
		FROM $deletedPostsTable dpx
		WHERE dpx.post_uid = t.post_op_post_uid
		  AND dpx.open_flag = 1
		  AND dpx.file_id IS NULL
	)";
}

function excludeDeletedPostsCondition(string $alias = 'dp'): string {
	return " AND (COALESCE($alias.open_flag, 0) = 0
					OR COALESCE($alias.file_only, 0) = 1
					OR COALESCE($alias.by_proxy, 0) = 1)";
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
		'isDeleted'      => $row['attachment_is_deleted'],
		'onlyFileDeleted' => $row['file_only_deleted'],
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
 * @return false|array
 */
function mergeMultiplePostRows(null|false|array $rows): false|array {
	if (!$rows) {
		return false;
	}

	$posts = [];

	foreach ($rows as $row) {
		$uid = $row['post_uid'];

		// Initialize the post entry if we haven't seen it yet
		if (!isset($posts[$uid])) {
			$posts[$uid] = $row;              // copy base post data
			$posts[$uid]['attachments'] = []; // normal attachments
			$posts[$uid]['deleted_attachments'] = []; // attachment deletion metadata
			$posts[$uid]['staff_notes'] = [];
		}

		/**
		 * Delegate the repeated logic to one helper
		 */
		mergeRowIntoPost($posts[$uid], $row);

		// soudane data
		if ($row['votes_total_count'] !== null) {
			$posts[$uid]['votes'] = [
				'total_score' => $row['votes_total_count'],
				'yeah_count' => $row['votes_yeah_count'],
				'nope_count' => $row['votes_nope_count']
			];
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
 * @param array $target The post entry being built
 * @param array $row    The SQL row
 */
function mergeRowIntoPost(array &$target, array $row): void {
	// normal attachments
	if (!empty($row['attachment_id'])) {
		$target['attachments'][$row['attachment_id']] = buildAttachment($row);
	}

	// deleted attachments
	if (!empty($row['file_id'])) {
		$target['deleted_attachments'][$row['file_id']] = [
			'deleted_post_id'   => $row['deleted_post_id'] ?? null,
			'deleted_by'        => $row['deleted_by'] ?? null,
			'deleted_at'        => $row['deleted_at'] ?? null,
			'restored_at'       => $row['restored_at'] ?? null,
			'file_only_deleted' => (bool)($row['file_only_deleted'] ?? false),
			'by_proxy'          => (bool)($row['by_proxy'] ?? false),
		];
	}

	// staff notes
	if (!empty($row['note_id'])) {

	    $noteId = $row['note_id'];

	    if (!isset($target['staff_notes'][$noteId])) {
	        $target['staff_notes'][$noteId] = [
	            'id'			=> $noteId,
	            'note_submitted'=> $row['note_submitted'],
	            'note_added_by'	=> $row['note_added_by'],
	            'note_text'		=> $row['note_text'],
				'note_added_by_username' => $row['note_added_by_username'],
	        ];
	    }
	}

	// remove raw attachment_* columns
	stripAttachmentColumns($target);

	// strip soudane columns
	stripSoudaneColumns($target);

	// strip the notes columns from the base array
	stripNoteColumns($row);
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
	// loop through post rows by reference
	foreach ($posts as &$post) {
		// Apply metadata to every attachment
		if (!empty($post['attachments'])) {
			foreach ($post['attachments'] as $attachmentId => &$att) {
				// attachment-level deletion
				$att['deletedPostId'] =
					$post['deleted_attachments'][$attachmentId]['deleted_post_id']
					?? null;
			}
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
			// Nothing sensible to group by; skip this row
			continue;
		}

		// Initialize this dp entry if we haven't seen it yet
		if (!isset($entries[$dpId])) {
			$entries[$dpId] = $row;
			$entries[$dpId]['attachments'] = [];
			$entries[$dpId]['deleted_attachments'] = [];
		}

		// Reuse your existing logic for wiring up attachments + deleted_attachments
		mergeRowIntoPost($entries[$dpId], $row);
	}

	return array_values($entries);
}

function sqlLatestDeletionEntry(string $deletedPostsTable): string {
	return "
		SELECT d1.post_uid, d1.open_flag, d1.file_only, d1.by_proxy
		FROM {$deletedPostsTable} d1
		INNER JOIN (
			SELECT post_uid, MAX(id) AS max_id
			FROM {$deletedPostsTable}
			GROUP BY post_uid
		) d2 ON d1.post_uid = d2.post_uid
		     AND d1.id = d2.max_id
	";
}