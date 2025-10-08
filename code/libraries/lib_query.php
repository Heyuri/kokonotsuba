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
			f_main.thumb_file_width,
			f_main.thumb_file_height,
			f_main.file_size,
			f_main.mime_type,
			f_main.is_hidden AS main_is_hidden,
		
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
		
		-- Main file join (non-thumb)
		LEFT JOIN (
			SELECT f1.*
			FROM $fileTable f1
			INNER JOIN (
				SELECT post_uid, MIN(id) AS min_id
				FROM $fileTable
				GROUP BY post_uid
			) f2 ON f1.post_uid = f2.post_uid AND f1.id = f2.min_id
		) f_main ON p.post_uid = f_main.post_uid
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
