<?php

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
            dp.note AS deleted_note,
            dp.file_id,
            dp.deleted_by,
            dp.deleted_at,
            dp.restored_at

        FROM ($postFilterSubquery) p

        -- Attachments
        LEFT JOIN $fileTable f ON f.post_uid = p.post_uid

        -- Thread info
        INNER JOIN $threadTable t ON p.thread_uid = t.thread_uid

        -- All deletion entries
        LEFT JOIN $deletedPostsTable dp 
            ON dp.post_uid = p.post_uid AND dp.open_flag = 1
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
		}

		/**
		 * Delegate the repeated logic to one helper
		 */
		mergeRowIntoPost($posts[$uid], $row);
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
			'note'              => $row['deleted_note'] ?? ($row['note'] ?? null),
		];
	}

	// remove raw attachment_* columns
	stripAttachmentColumns($target);
}

/**
 * Remove all attachment_* columns from a post row.
 *
 * @param array &$row  Row to clean (modified in-place)
 */
function stripAttachmentColumns(array &$row): void {
	static $cols = [
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
	];

	foreach ($cols as $c) {
		unset($row[$c]);
	}
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