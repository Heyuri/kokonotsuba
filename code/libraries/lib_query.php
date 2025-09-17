<?php

// A helper fybctuib to generate the base query part for posts
function getBasePostQuery(string $postTable, string $deletedPostsTable, string $fileTable): string {
	$query = "
		SELECT 
			p.*,
		
			-- Main file info
			f_main.id AS file_id,
			f_main.file_name,
			f_main.stored_filename,
			f_main.file_ext,
			f_main.file_md5,
			f_main.file_width,
			f_main.file_height,
			f_main.file_size,
			f_main.mime_type,
			f_main.is_hidden AS main_is_hidden,
			f_main.is_thumb AS main_is_thumb,
		
			-- Thumbnail file info
			f_thumb.id AS thumb_file_id,
			f_thumb.file_name AS thumb_file_name,
			f_thumb.stored_filename AS thumb_stored_filename,
			f_thumb.file_ext AS thumb_file_ext,
			f_thumb.file_md5 AS thumb_file_md5,
			f_thumb.file_width AS thumb_file_width,
			f_thumb.file_height AS thumb_file_height,
			f_thumb.file_size AS thumb_file_size,
			f_thumb.mime_type AS thumb_mime_type,
			f_thumb.is_hidden AS thumb_is_hidden,
			f_thumb.is_thumb AS thumb_is_thumb,
		
			-- Deleted post info
			dp.open_flag,
			dp.file_only AS file_only_deleted,
			dp.id AS deleted_post_id,
			dp.by_proxy
		
		FROM $postTable p
		
		-- Join latest deleted_post info
		LEFT JOIN (
			SELECT dp1.post_uid, dp1.open_flag, dp1.file_only, dp1.by_proxy, dp1.id
			FROM $deletedPostsTable dp1
			INNER JOIN (
				SELECT post_uid, MAX(deleted_at) AS max_deleted_at
				FROM $deletedPostsTable
				GROUP BY post_uid
			) dp2 ON dp1.post_uid = dp2.post_uid AND dp1.deleted_at = dp2.max_deleted_at
		) dp ON p.post_uid = dp.post_uid
		
		-- Main file join (non-thumb)
		LEFT JOIN (
			SELECT f1.*
			FROM $fileTable f1
			INNER JOIN (
				SELECT post_uid, MIN(id) AS min_id
				FROM $fileTable
				WHERE is_thumb = FALSE OR is_thumb IS NULL
				GROUP BY post_uid
			) f2 ON f1.post_uid = f2.post_uid AND f1.id = f2.min_id
		) f_main ON p.post_uid = f_main.post_uid
		
		-- Thumbnail file join
		LEFT JOIN (
			SELECT f1.*
			FROM $fileTable f1
			INNER JOIN (
				SELECT post_uid, MIN(id) AS min_id
				FROM $fileTable
				WHERE is_thumb = TRUE
				GROUP BY post_uid
			) f2 ON f1.post_uid = f2.post_uid AND f1.id = f2.min_id
		) f_thumb ON p.post_uid = f_thumb.post_uid

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
