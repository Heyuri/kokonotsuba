<?php

class fileRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $fileTable,
		private readonly string $postTable
	) {}

	public function getFileById(int $fileId): false|fileEntry {
		// query to get a INNER joined file rows
		$query = $this->buildBaseFileQuery();

		// WHERE clause
		$query .= " WHERE f.id = :file_id";

		// parameters
		$params = [
			':file_id' => $fileId
		];

		// fetch row as fileEntry class
		$fileEntry = $this->databaseConnection->fetchAsClass($query, $params, 'fileEntry');
	
		// return fileEnty results
		return $fileEntry;
	}

	public function getFilesForPost(int $postUid): false|array {
		// query to get a INNER joined file rows
		$query = $this->buildBaseFileQuery();

		// WHERE clause
		$query .= " WHERE f.post_uid = :post_uid LIMIT 2";

		// parameters
		$params = [
			':post_uid' => $postUid
		];

		// fetch rows as fileEntry classes
		$fileEntries = $this->databaseConnection->fetchAllAsClass($query, $params, 'fileEntry');
	
		// return fileEntries results
		return $fileEntries;
	}

	public function getFilesForThread(string $threadUid): false|array {
		// query to get inner joined file rows
		$query = $this->buildBaseFileQuery();

		// WHERE clause for the thread
		$query .= " WHERE f.post_uid 
				IN( SELECT post_uid FROM {$this->postTable} 
					WHERE p.thread_uid = :thread_uid
		)";

		// parameters
		$params = [
			':thread_uid' => $threadUid
		];

		// fetch all as calss
		$fileEntries = $this->databaseConnection->fetchAllAsClass($query, $params, 'fileEntry');

		// return results
		return $fileEntries;
	}

	public function getAttachmentsFromPostUids(array $postUids): false|array {
		// generate part of the in clause
		$inClause = pdoPlaceholdersForIn($postUids);

		// query to fetch fileEntries from the database based on a list of post uids
		$query = $this->buildBaseFileQuery(); 
		
		// append WHERE + IN clause
		$query .= " WHERE f.post_uid IN $inClause";

		// parameters
		$params = $postUids;

		// fetch the results as fileEntries objects
		$attachments = $this->databaseConnection->fetchAllAsClass($query, $params, 'fileEntry');

		// return result
		return $attachments;
	}

	private function buildBaseFileQuery(): string {
		// define base query the repo will use
		$query = "SELECT f.*, p.boardUID, p.thread_uid  FROM {$this->fileTable} f
				INNER JOIN {$this->postTable} p ON p.post_uid = f.post_uid";

		// return query
		return $query;
	}

	public function deleteFileRows(array $fileIds): void {
		// generate IN clause placeholders
		$inClause = pdoPlaceholdersForIn($fileIds);

		// query to delete file rows by IDs
		$query = "DELETE FROM {$this->fileTable} WHERE id IN $inClause";

		// parameters
		$params = $fileIds;

		// execute query
		$this->databaseConnection->execute($query, $params);
	}

	public function setHiddenStatuses(array $fileIds, bool $flag): void {
		// generate in clause
		$fileClause = pdoNamedPlaceholdersForIn($fileIds, 'file');

		// file placeholders
		$filePlaceholders = $fileClause['placeholders'];

		// file parameters
		$fileParameters = $fileClause['params'];

		// query to update the rows
		$query = "UPDATE {$this->fileTable} SET is_hidden = :flag WHERE id IN ($filePlaceholders)";

		// get flag placeholder
		$flagPlaceholder = [':flag' => (int) $flag];

		// merge named params with flag placeholder to bring together the parameters
		$params = array_merge($flagPlaceholder, $fileParameters);

		// execute query
		$this->databaseConnection->execute($query, $params);
	}

	public function insertFileRow(
		int $post_uid,
		string $file_name,
		string $stored_filename,
		string $file_ext,
		string $file_md5,
		?int $file_width,
		?int $file_height,
		int $file_size,
		string $mime_type,
		bool $is_hidden,
		bool $is_thumb
	): void {
		// query to insert a file row
		$query = "INSERT INTO {$this->fileTable} 
					(
						post_uid,
						file_name,
						stored_filename,
						file_ext,
						file_md5,
						file_width,
						file_height,
						file_size,
						mime_type,
						is_hidden,
						is_thumb
					) 
					VALUES (
						:post_uid,
						:file_name,
						:stored_filename,
						:file_ext,
						:file_md5,
						:file_width,
						:file_height,
						:file_size,
						:mime_type,
						:is_hidden,
						:is_thumb
					)";

		// parameters
		$params = [
			':post_uid' => $post_uid,
			':file_name' => $file_name,
			':stored_filename' => $stored_filename,
			':file_ext' => $file_ext,
			':file_md5' => $file_md5,
			':file_width' => $file_width,
			':file_height' => $file_height,
			':file_size' => $file_size,
			':mime_type' => $mime_type,
			':is_hidden' => (int) $is_hidden,
			':is_thumb' => (int) $is_thumb
		];

		// execute query
		$this->databaseConnection->execute($query, $params);
	}
}