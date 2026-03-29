<?php

namespace Kokonotsuba\post\attachment;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoNamedPlaceholdersForIn;
use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/** Repository for post attachment file records. */
class fileRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $fileTable,
		private readonly string $postTable,
		private readonly string $deletedPostsTable,
	) {
		parent::__construct($databaseConnection, $fileTable);
	}

	/**
	 * Fetch a single file entry by its primary key ID, with joined post and board data.
	 *
	 * @param int $fileId File row ID.
	 * @return fileEntry|false The hydrated fileEntry, or false if not found.
	 */
	public function getFileById(int $fileId): false|fileEntry {
		$query = $this->buildBaseFileQuery();
		$query .= " WHERE f.id = :file_id";
		$fileEntry = $this->queryAsClass($query, [':file_id' => $fileId], '\Kokonotsuba\post\attachment\fileEntry');
		return $fileEntry;
	}

	/**
	 * Fetch all file entries belonging to the given post UID.
	 *
	 * @param int $postUid Post UID.
	 * @return fileEntry[]|false Array of hydrated fileEntry objects, or false if none found.
	 */
	public function getFilesForPost(int $postUid): false|array {
		$query = $this->buildBaseFileQuery();
		$query .= " WHERE f.post_uid = :post_uid";
		return $this->queryAllAsClass($query, [':post_uid' => $postUid], '\Kokonotsuba\post\attachment\fileEntry');
	}

	/**
	 * Fetch all file entries for posts in the given thread.
	 * Optionally excludes files that are already flagged as deleted in the deletedPosts table.
	 *
	 * @param string $threadUid              Thread UID.
	 * @param bool   $excludeAlreadyDeleted  If true, skip files with an open deletion record.
	 * @return fileEntry[]|false Array of hydrated fileEntry objects, or false if none found.
	 */
	public function getFilesForThread(string $threadUid, bool $excludeAlreadyDeleted = false): false|array {
		$query = $this->buildBaseFileQuery();
		$query .= " WHERE p.thread_uid = :thread_uid";

		if ($excludeAlreadyDeleted) {
			$query .= "
				AND NOT EXISTS (
					SELECT 1
					FROM {$this->deletedPostsTable} dp1
					WHERE dp1.open_flag = 1
					AND dp1.by_proxy = 0
					AND p.is_op = 0
					AND (
						dp1.file_id = f.id
						OR (dp1.file_id IS NULL AND dp1.post_uid = p.post_uid)
					)
				)
			";
		}

		return $this->queryAllAsClass($query, [':thread_uid' => $threadUid], '\Kokonotsuba\post\attachment\fileEntry');
	}

	/**
	 * Fetch all file entries for an array of post UIDs.
	 *
	 * @param int[] $postUids Array of post UIDs.
	 * @return fileEntry[]|false Array of hydrated fileEntry objects, or false if none found.
	 */
	public function getAttachmentsFromPostUids(array $postUids): false|array {
		$inClause = pdoPlaceholdersForIn($postUids);
		$query = $this->buildBaseFileQuery(); 
		$query .= " WHERE f.post_uid IN $inClause";
		return $this->queryAllAsClass($query, $postUids, '\Kokonotsuba\post\attachment\fileEntry');
	}

	/**
	 * Build the shared SELECT/JOIN base for file queries, joining post and deletedPosts tables.
	 *
	 * @return string Base SQL query string (without WHERE clause).
	 */
	private function buildBaseFileQuery(): string {
		return "SELECT DISTINCT f.*, p.boardUID, p.thread_uid  FROM {$this->table} f
				INNER JOIN {$this->postTable} p ON p.post_uid = f.post_uid
				LEFT JOIN {$this->deletedPostsTable} dp ON dp.post_uid = f.post_uid ";
	}

	/**
	 * Delete file rows for the given file IDs.
	 *
	 * @param int[] $fileIds Array of file row IDs to delete.
	 * @return void
	 */
	public function deleteFileRows(array $fileIds): void {
		$inClause = pdoPlaceholdersForIn($fileIds);
		$this->query("DELETE FROM {$this->table} WHERE id IN $inClause", $fileIds);
	}

	/**
	 * Set the is_hidden flag on the given file IDs.
	 *
	 * @param int[]  $fileIds Array of file row IDs to update.
	 * @param bool   $flag    True to hide, false to unhide.
	 * @return void
	 */
	public function setHiddenStatuses(array $fileIds, bool $flag): void {
		$fileClause = pdoNamedPlaceholdersForIn($fileIds, 'file');
		$filePlaceholders = $fileClause['placeholders'];
		$fileParameters = $fileClause['params'];

		$query = "UPDATE {$this->table} SET is_hidden = :flag WHERE id IN ($filePlaceholders)";
		$params = array_merge([':flag' => (int) $flag], $fileParameters);
		$this->query($query, $params);
	}

	/**
	 * Insert a new file attachment row.
	 *
	 * @param int         $post_uid            Post UID this file belongs to.
	 * @param string|null $file_name           Original filename.
	 * @param string|null $stored_filename     Server-stored filename.
	 * @param string|null $file_ext            File extension.
	 * @param string|null $file_md5            MD5 hash of the file.
	 * @param int|null    $file_width          Image width in pixels.
	 * @param int|null    $file_height         Image height in pixels.
	 * @param int|null    $thumb_file_width    Thumbnail width in pixels.
	 * @param int|null    $thumb_file_height   Thumbnail height in pixels.
	 * @param int|null    $file_size           File size in bytes.
	 * @param string|null $mime_type           MIME type string.
	 * @param bool        $is_hidden           Whether the file is in purgatory.
	 * @param bool        $is_deleted          Whether the file is marked deleted.
	 * @return void
	 */
	public function insertFileRow(
		int $post_uid,
		?string $file_name,
		?string $stored_filename,
		?string $file_ext,
		?string $file_md5,
		?int $file_width,
		?int $file_height,
		?int $thumb_file_width,
		?int $thumb_file_height,
		?int $file_size,
		?string $mime_type,
		bool $is_hidden,
		bool $is_deleted = false,
	): void {
		$this->insert([
			'post_uid' => $post_uid,
			'file_name' => $file_name,
			'stored_filename' => $stored_filename,
			'file_ext' => $file_ext,
			'file_md5' => $file_md5,
			'file_width' => $file_width,
			'file_height' => $file_height,
			'thumb_file_width' => $thumb_file_width,
			'thumb_file_height' => $thumb_file_height,
			'file_size' => $file_size,
			'mime_type' => $mime_type,
			'is_hidden' => (int) $is_hidden,
			'is_deleted' => (int) $is_deleted,
		]);
	}

	/**
	 * Toggle the is_animated flag on the given file row.
	 *
	 * @param int  $fileId  File row ID.
	 * @param bool $animate True to mark as animated, false to unmark.
	 * @return void
	 */
	public function toggleAnimatedFileById(int $fileId, bool $animate): void {
		$this->updateWhere(['is_animated' => (int)$animate], 'id', $fileId);
	}

	/**
	 * Toggle the is_deleted flag on the given file IDs.
	 *
	 * @param int[]  $fileIDs Array of file row IDs to update.
	 * @param bool   $delete  True to mark deleted, false to restore.
	 * @return void
	 */
	public function toggleIsDeleted(array $fileIDs, bool $delete): void {
		$clause = pdoNamedPlaceholdersForIn($fileIDs, 'file');
		$filePlaceholders = $clause['placeholders'];
		$fileParameters = $clause['params'];

		$query = "UPDATE {$this->table} SET is_deleted = :delete WHERE id IN ($filePlaceholders)";
		$fileParameters[':delete'] = (int)$delete;
		$this->query($query, $fileParameters);
	}

	/**
	 * Check whether a file with the given MD5 hash already exists in the table.
	 *
	 * @param string   $md5Hash            MD5 hash to search for.
	 * @param bool     $countDeleted       If false, ignore rows where is_deleted = 1.
	 * @param int|null $timeRangeInSeconds If set, only check for hashes added within the last N seconds.
	 * @return bool True if a matching hash is found.
	 */
    public function checkDuplicateHash(string $md5Hash, bool $countDeleted = true, ?int $timeRangeInSeconds = null): bool {
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE file_md5 = :md5_hash";

        if (!$countDeleted) {
            $query .= " AND is_deleted = 0";
        }

        if ($timeRangeInSeconds !== null) {
            $timeThreshold = time() - $timeRangeInSeconds;
            $query .= " AND timestamp_added >= FROM_UNIXTIME(:time_threshold)";
        }

        $params = [':md5_hash' => $md5Hash];

        if ($timeRangeInSeconds !== null) {
            $params[':time_threshold'] = $timeThreshold;
        }

        $result = $this->queryColumn($query, $params);
        return $result > 0;
    }

	/**
	 * Return the next AUTO_INCREMENT value for the files table.
	 *
	 * @return int Next available file ID.
	 */
	public function getNextId(): int {
		return $this->getNextAutoIncrement();
	}
}