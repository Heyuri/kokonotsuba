<?php

// A helper function to generate the base query part for posts
function getBasePostQuery(string $postTable, string $deletedPostsTable, string $fileTable): string {
	$query = "
		SELECT 
			p.*,
		
			-- All attachment rows
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
					
			-- Deleted post info
			dp.open_flag,
			dp.file_only AS file_only_deleted,
			dp.id AS deleted_post_id,
			dp.by_proxy,
			dp.note AS deleted_note
		
		FROM $postTable p
		
		-- Join latest deleted_post info
		LEFT JOIN (
			SELECT dp1.post_uid, dp1.open_flag, dp1.file_only, dp1.by_proxy, dp1.note, dp1.id
			FROM $deletedPostsTable dp1
			INNER JOIN (
				SELECT post_uid, MAX(deleted_at) AS max_deleted_at
				FROM $deletedPostsTable
				GROUP BY post_uid
			) dp2 ON dp1.post_uid = dp2.post_uid AND dp1.deleted_at = dp2.max_deleted_at
		) dp ON p.post_uid = dp.post_uid
		
		-- Additional file rows (all attachments)
		LEFT JOIN $fileTable f ON f.post_uid = p.post_uid


		";
	return $query;
}

function excludeDeletedPostsCondition(string $query): string {
	$query .= " AND (COALESCE(dp.open_flag, 0) = 0
					OR COALESCE(dp.file_only, 0) = 1
					OR COALESCE(dp.by_proxy, 0) = 1)";
	// return modified query
	return $query;
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
function mergeMultiplePostRows(false|array $rows): false|array {
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
         * -------------------------------------------
         * NORMAL ATTACHMENTS
         * -------------------------------------------
         * We only add an attachment if the row contains a real f.* row.
         * This is safe because attachment_id comes from f.id.
         */
        if (!empty($row['attachment_id'])) {
            $posts[$uid]['attachments'][$row['attachment_id']] = buildAttachment($row);
        }

        /**
         * -------------------------------------------
         * DELETED ATTACHMENTS
         * -------------------------------------------
         * file_id comes from deleted_posts.file_id and indicates a deleted attachment.
         * We only store metadata here — NOT the file itself.
         */
        if (!empty($row['file_id'])) {
            $posts[$uid]['deleted_attachments'][$row['file_id']] = [
                'deleted_post_id'   => $row['deleted_post_id'],
                'deleted_by'        => $row['deleted_by'],
                'deleted_at'        => $row['deleted_at'],
                'restored_at'       => $row['restored_at'],
                'file_only_deleted' => (bool)$row['file_only_deleted'],
                'by_proxy'          => (bool)$row['by_proxy'],
                'note'              => $row['deleted_note'],
            ];
        }
    }

    // Return flat array of merged posts
    return array_values($posts);
}

/**
 * Merge a single SQL row into a structured post array with an
 * attachments[] list (always created).
 *
 * @param false|array $row
 * @return false|array
 */
function mergeSinglePostRow(false|array $row): false|array {
    if (!$row) {
        return false;
    }

    // Copy all base post fields
    $post = $row;

    // Always create an attachment list
    $post['attachments'] = [];

    /**
     * Add a single attachment only if the f.* row exists
     * attachment_id == f.id
     */
    if (!empty($row['attachment_id'])) {
        $post['attachments'][$row['attachment_id']] = buildAttachment($row);
    }

    return $post;
}


/**
 * Merge attachment and deleted-attachment data from a database row
 * into an existing post array. This keeps logic shared between
 * mergeSinglePostRow() and mergeMultiplePostRows().
 *
 * @param array $post Reference to the post array being built
 * @param array $row  A single database query row containing post + attachment data
 *
 * Expected fields:
 *   attachment_id      → live attachment file ID (from files table)
 *   file_id            → deleted attachment file ID (from deleted_posts table)
 *   deleted_post_id    → deleted_posts row ID for this attachment deletion
 *   deleted_by         → account ID who deleted it
 *   deleted_at         → timestamp of deletion
 *   restored_at        → timestamp of restoration (if restored)
 *   file_only_deleted  → whether this deletion is attachment-only
 *   by_proxy           → whether deletion was done via proxy
 *   deleted_note       → moderator note
 */
function mergeAttachmentData(array &$post, array $row): void {
	// Ensure arrays exist to avoid undefined index errors
	$post['attachments'] ??= [];
	$post['deleted_attachments'] ??= [];

	/**
	 * LIVE ATTACHMENT
	 * This means the file still exists on the post.
	 */
	if (!empty($row['attachment_id'])) {
		$post['attachments'][$row['attachment_id']] = buildAttachment($row);
	}

	/**
	 * DELETED ATTACHMENT ENTRY
	 * This indicates a file-only deletion in deleted_posts.
	 * Multiple of these can exist for a single post_uid.
	 */
	if (!empty($row['file_id'])) {
		$post['deleted_attachments'][$row['file_id']] = [
			'deleted_post_id'     => $row['deleted_post_id'],
			'deleted_by'          => $row['deleted_by'],
			'deleted_at'          => $row['deleted_at'],
			'restored_at'         => $row['restored_at'],
			'file_only_deleted'   => (bool) $row['file_only_deleted'],
			'by_proxy'            => (bool) $row['by_proxy'],
			'note'                => $row['deleted_note'],
		];
	}
}

/**
 * Merge rows from deleted-posts query into structured entries.
 *
 * The query now returns:
 *   • One row per deleted_posts.id
 *   • At most one attachment per row (the one referenced by dp.file_id)
 *
 * This function:
 *   • Groups post-level deletions by post_uid
 *   • Creates separate entries for attachment-only deletions
 *   • Preserves all attachments and deleted_attachments metadata
 */
function mergeDeletedPostRows(array $rows): array {
    $results = [];

    foreach ($rows as $row) {
        $postUid = $row['post_uid'];
        $fileId  = $row['file_id'] ?? null;

        // Initialize entry if it doesn’t exist
        if (!isset($results[$postUid])) {
            $results[$postUid] = $row;
            $results[$postUid]['attachments'] = [];
            $results[$postUid]['deleted_attachments'] = [];
        }

        // Always merge normal attachments
        if (!empty($row['attachment_id'])) {
            $results[$postUid]['attachments'][$row['attachment_id']] = buildAttachment($row);
        }

        // Merge deleted attachment metadata if present
        if ($fileId !== null) {
            $results[$postUid]['deleted_attachments'][$fileId] = [
                'file_id'              => $fileId,
                'deleted_post_id'      => $row['deleted_post_id'],
                'deleted_at'           => $row['deleted_at'],
                'deleted_by'           => $row['deleted_by'],
                'deleted_by_username'  => $row['deleted_by_username'],
                'restored_at'          => $row['restored_at'],
                'restored_by'          => $row['restored_by'],
                'restored_by_username' => $row['restored_by_username'],
                'note'                 => $row['note'],
                'by_proxy'             => (bool)$row['by_proxy'],
                'file_only_deleted'    => !empty($row['file_only_deleted']),
            ];
        }
    }

    return array_values($results);
}
