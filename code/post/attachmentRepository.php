<?php

class attachmentRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection, 
		private readonly string $postTable, 
		private readonly string $threadTable) {}

	public function getAttachmentsByMd5(string $boardUID, string $md5hash): array {
		$query = "SELECT tim, ext FROM {$this->postTable} WHERE ext <> '' AND md5chksum = :md5chksum AND boardUID = :boardUID ORDER BY no DESC";
		$params = [
			':md5chksum' => $md5hash,
			':boardUID' => $boardUID,
		];
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	public function getAllAttachments(): array {
		$query = "SELECT post_uid, ext, tim FROM {$this->postTable} WHERE ext <> '' ORDER BY no";
		return $this->databaseConnection->fetchAllAsArray($query);
	}

	public function getAttachmentRecords(array $posts, bool $recursion): array {
		$postPlaceholders = pdoNamedPlaceholdersForIn($posts, 'post');
		$params = $postPlaceholders['params'];

		// Build the SQL query
		if ($recursion) {
			// If recursion is enabled, we need to use the same placeholders in both IN clauses
			$query = "
				SELECT ext, tim, boardUID 
				FROM {$this->postTable} 
				WHERE ext <> '' AND (
					post_uid IN ({$postPlaceholders['placeholders']})
					OR thread_uid IN (
						SELECT thread_uid
						FROM {$this->threadTable}
						WHERE post_op_post_uid IN ({$postPlaceholders['placeholders']})
					)
   			 )";
		} else {
			// Without recursion, we only use the placeholders for the main IN clause
			$query = "
				SELECT ext, tim, boardUID 
				FROM {$this->postTable} 
				WHERE post_uid IN ({$postPlaceholders['placeholders']})
				AND ext <> ''";
		}
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	public function getAttachmentsFromThreads(array $threadUidList): array|false {
		$inClause = pdoPlaceholdersForIn($threadUidList);
		$query = "SELECT tim, ext, boardUID FROM {$this->postTable} WHERE thread_uid IN $inClause AND ext <> ''";

		$attachments = $this->databaseConnection->fetchAllAsArray($query, $threadUidList);

		return $attachments;
	}

}
