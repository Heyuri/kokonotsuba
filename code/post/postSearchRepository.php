<?php

class postSearchRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $postTable, 
		private readonly string $threadTable,
		private readonly string $deletedPostsTable,
		private readonly string $fileTable,
	) {}
	
	private function buildParamters(array $fields, array $boardUids): array {
		// initialize parameters array
		$params = [];

		// set the parameter for each field
		foreach ($fields as $field => $value) {
			// set the parameter for each field
			$params[":{$field}"] = $value;
		}

		// then build board UIDs parameter if any (board UIDs may be an array)
		if (!empty($boardUids)) {
			foreach($boardUids as $index => $boardUid) {
				$params[":board_{$index}"] = (int)$boardUid;
			}
		}

		// set the keyword parameter for LIKE searches
		return $params;
	}

	public function fetchPostsByFullText(array $fields, array $boardUids, int $limit, int $offset): false|array {
		// build the search field and parameters
		$params = $this->buildParamters($fields, $boardUids);

		// get base post query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable, false);

		// build the search field WHERE clause
		$searchClause = $this->buildSearchClause($fields, $boardUids);

		// append WHERE
		$query .= " WHERE $searchClause";

		// order it
		$query .= " ORDER BY p.root DESC";

		// append order / limit / offset
		$query .= "
			LIMIT $limit OFFSET $offset
		";

		// fetch posts from db
		$postsResults = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment rows
		$mergedPosts = mergeMultiplePostRows($postsResults);

		// return merged posts
		return $mergedPosts;
	}

	public function countPostsByFullText(array $fields, array $boardUids): int {
		// get base post query
		$postQuery = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable, false);

		// build the search field WHERE clause
		$searchClause = $this->buildSearchClause($fields, $boardUids);

		// build the search field WHERE clause
		$countQuery = "
			SELECT COUNT(*) AS total_posts
			FROM (
				$postQuery
				" . ($searchClause !== '' ? "WHERE $searchClause" : "") . "
			) AS post_count
		";

		// build parameters
		$params = $this->buildParamters($fields, $boardUids);

		return $this->databaseConnection->fetchOne($countQuery, $params)['total_posts'] ?? 0;
	}

	private function buildSearchClause(array $fields, array $boardUids): string {
		// init caluses
		$clauses = [];

		// map of searchable columns
		$fieldColumns = [
			'general'   => ['p.name', 'p.email', 'p.sub', 'p.com', 'f.file_name'],
			'com'       => 'p.com',
			'name'      => 'p.name',
			'email'     => 'p.email',
			'sub'       => 'p.sub',
			'no'        => 'p.no',
			'file_name' => 'f.file_name',
			'root'      => 'p.root',
		];

		// get the keys
		$fieldKeys = array_keys($fields);

		// build fulltext clauses
		foreach ($fieldKeys as $field) {
			// skip unknown fields
			if (!isset($fieldColumns[$field])) continue;

			// build sub-clauses for this field
			$columns = (array)$fieldColumns[$field];
			
			// build sub-clauses
			$subClauses = [];

			// build MATCH AGAINST for each column
			foreach ($columns as $column) {
				// if its a post number search, use equality
				if ($field === 'no') {
					$subClauses[] = "$column = :{$field}";
					continue;
				}
				
				// use boolean mode for fulltext search
				$subClauses[] = "MATCH($column) AGAINST (:{$field} IN BOOLEAN MODE)";
			}

			$combineMethod = ' OR ';
			
			// add to main clauses
			$clauses[] = '(' . implode($combineMethod, $subClauses) . ')';
		}

		// build boardUID clause (if any)
		if (!empty($boardUids)) {
			// build boardUID sub-clauses
			$boardClauses = [];

			// get board keys
			$boardKeys = array_keys($boardUids);

			// build clause for each board UID
			foreach ($boardKeys as $index) {
				// use parameterized board UID
				$boardClauses[] = "p.boardUID = :board_{$index}";
			}
			// combine board clauses with OR
			$clauses[] = '(' . implode(' OR ', $boardClauses) . ')';
		}

		// combine all clauses with AND by default
		return implode(' AND ', $clauses);
	}
}
