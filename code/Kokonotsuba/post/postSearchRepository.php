<?php

namespace Kokonotsuba\post;

use function Kokonotsuba\libraries\openDeletionExistsCondition;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\getBasePostQuery;
use function Kokonotsuba\libraries\mergeMultiplePostRows;
use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/** Repository for full-text post search queries. */
class postSearchRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $postTable, 
		private readonly string $threadTable,
		private readonly string $deletedPostsTable,
		private readonly string $fileTable,
		private readonly string $soudaneTable,
		private readonly string $noteTable,
		private readonly string $accountTable,
		private readonly string $countryFlagTable = '',
		private readonly string $displayIpTable = '',
	) {
		parent::__construct($databaseConnection, $postTable);
		self::validateTableNames($threadTable, $deletedPostsTable, $fileTable, $soudaneTable, $noteTable, $accountTable);
	}
	
	private function buildParamters(array $fields, array $boardUids): array {
		// initialize parameters array
		$params = [];

		// set the parameter for each field
		foreach ($fields as $field => $value) {
			// set the parameter for each field
			$params[":{$field}"] = $value;
		}

		// duplicate general param for the file_name UNION branch
		if (isset($fields['general'])) {
			$params[':general_file'] = $fields['general'];
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

	/**
	 * Fetch a paginated list of posts matching the given full-text search fields.
	 *
	 * @param array  $fields           Map of field name => boolean full-text search string.
	 * @param array  $boardUids        Board UIDs to restrict the search to (empty = all boards).
	 * @param bool   $openingPostsOnly If true, return only OP posts.
	 * @param int    $limit            Maximum number of results to return.
	 * @param int    $offset           Pagination offset.
	 * @return array|false Array of merged post data arrays, or false if none found.
	 */
	public function fetchPostsByFullText(array $fields, array $boardUids, bool $openingPostsOnly, int $limit, int $offset): false|array {
		// For general search, run two fast FULLTEXT queries separately and merge in PHP
		if (isset($fields['general'])) {
			return $this->fetchGeneralSearch($fields, $boardUids, $openingPostsOnly, $limit, $offset);
		}

		$params = $this->buildParamters($fields, $boardUids);

		// Phase 1: find matching post UIDs with a lightweight query (no soudane/notes/account JOINs)
		$uidQuery = $this->buildLightweightSearchQuery('SELECT DISTINCT p.post_uid', $fields, $boardUids, $openingPostsOnly);
		$uidQuery .= " ORDER BY p.root DESC, p.post_uid DESC";
		$this->paginate($uidQuery, $params, $limit, $offset);

		$postUids = $this->queryFlatColumn($uidQuery, $params);
		if (empty($postUids)) {
			return false;
		}

		return $this->loadFullPostData($postUids);
	}

	/**
	 * Return the total count of posts matching the given full-text search fields.
	 *
	 * @param array $fields          Map of field name => boolean full-text search string.
	 * @param array $boardUids       Board UIDs to restrict the search to.
	 * @param bool  $openingPostsOnly If true, count only OP posts.
	 * @return int Total matching post count.
	 */
	public function countPostsByFullText(array $fields, array $boardUids, bool $openingPostsOnly): int {
		// For general search, run two fast counts and merge
		if (isset($fields['general'])) {
			return $this->countGeneralSearch($fields, $boardUids, $openingPostsOnly);
		}

		$params = $this->buildParamters($fields, $boardUids);
		$countQuery = $this->buildLightweightSearchQuery('SELECT COUNT(DISTINCT p.post_uid)', $fields, $boardUids, $openingPostsOnly);

		return (int) ($this->queryColumn($countQuery, $params) ?? 0);
	}

	/**
	 * Fetch general search results by running two independent FULLTEXT queries
	 * (post columns + file name) via UNION in SQL with pagination.
	 */
	private function fetchGeneralSearch(array $fields, array $boardUids, bool $openingPostsOnly, int $limit, int $offset): false|array {
		$result = $this->buildGeneralSearchUnion($fields, $boardUids, $openingPostsOnly);
		$unionQuery = $result['query'];
		$params = $result['params'];

		$query = "SELECT post_uid FROM ({$unionQuery}) AS search_hits ORDER BY root DESC, post_uid DESC";
		$this->paginate($query, $params, $limit, $offset);

		$postUids = $this->queryFlatColumn($query, $params);
		if (empty($postUids)) {
			return false;
		}

		return $this->loadFullPostData($postUids);
	}

	/**
	 * Count general search results via UNION of two FULLTEXT queries.
	 */
	private function countGeneralSearch(array $fields, array $boardUids, bool $openingPostsOnly): int {
		$result = $this->buildGeneralSearchUnion($fields, $boardUids, $openingPostsOnly);

		$query = "SELECT COUNT(*) FROM ({$result['query']}) AS search_hits";

		return (int) ($this->queryColumn($query, $result['params']) ?? 0);
	}

	/**
	 * Build a UNION of two FULLTEXT queries for general search.
	 *
	 * Query 1: MATCH(name, email, sub, com) on the posts table (uses ft_general index)
	 * Query 2: MATCH(file_name) on the files table (uses ft_file_name index)
	 *
	 * Uses separate parameter names per branch to avoid PDO named-param conflicts.
	 *
	 * @return array{query: string, params: array}
	 */
	private function buildGeneralSearchUnion(array $fields, array $boardUids, bool $openingPostsOnly): array {
		$nonGeneralFields = array_diff_key($fields, ['general' => true]);

		$deletedExclusion = 'NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');

		// Build extra clauses for non-general fields (board filter, opening posts, etc.)
		// We need two copies with different param names for UNION branches
		$extraClause1 = $this->buildSearchClauseWithSuffix($nonGeneralFields, $boardUids, $openingPostsOnly, '');
		$extraClause2 = $this->buildSearchClauseWithSuffix($nonGeneralFields, $boardUids, $openingPostsOnly, '_b');

		// Params for branch 1 (post text columns)
		$params1 = [':general' => $fields['general']];
		foreach ($nonGeneralFields as $field => $value) {
			$params1[":{$field}"] = $value;
		}
		if (!empty($boardUids)) {
			foreach ($boardUids as $index => $boardUid) {
				$params1[":board_{$index}"] = (int) $boardUid;
			}
		}

		// Params for branch 2 (file names) — suffixed to avoid conflicts
		$params2 = [':general_file' => $fields['general']];
		foreach ($nonGeneralFields as $field => $value) {
			$params2[":{$field}_b"] = $value;
		}
		if (!empty($boardUids)) {
			foreach ($boardUids as $index => $boardUid) {
				$params2[":board_{$index}_b"] = (int) $boardUid;
			}
		}

		// Query 1: composite FULLTEXT on post columns
		$postWhere = [$deletedExclusion, "MATCH(p.name, p.email, p.sub, p.com) AGAINST (:general IN BOOLEAN MODE)"];
		if ($extraClause1 !== '') {
			$postWhere[] = $extraClause1;
		}
		$postQuery = "SELECT p.post_uid, p.root FROM {$this->table} p";
		if (isset($nonGeneralFields['file_name'])) {
			$postQuery .= " INNER JOIN {$this->fileTable} f ON f.post_uid = p.post_uid";
		}
		$postQuery .= " WHERE " . implode(' AND ', $postWhere);

		// Query 2: file_name FULLTEXT
		$fileWhere = [$deletedExclusion, "MATCH(f.file_name) AGAINST (:general_file IN BOOLEAN MODE)"];
		if ($extraClause2 !== '') {
			$fileWhere[] = $extraClause2;
		}
		$fileQuery = "SELECT p.post_uid, p.root FROM {$this->table} p"
			. " INNER JOIN {$this->fileTable} f ON f.post_uid = p.post_uid"
			. " WHERE " . implode(' AND ', $fileWhere);

		$unionQuery = "({$postQuery}) UNION ({$fileQuery})";

		return [
			'query' => $unionQuery,
			'params' => array_merge($params1, $params2),
		];
	}

	/**
	 * Build a search clause with an optional parameter name suffix.
	 * Used to generate two copies of the same WHERE filters with different param names for UNION branches.
	 */
	private function buildSearchClauseWithSuffix(array $fields, array $boardUids, bool $openingPostsOnly, string $suffix): string {
		$clauses = [];

		$fieldColumns = [
			'com'       => 'p.com',
			'name'      => 'p.name',
			'email'     => 'p.email',
			'sub'       => 'p.sub',
			'no'        => 'p.no',
			'file_name' => 'f.file_name',
			'tag'       => 'p.tag',
		];

		foreach ($fields as $field => $value) {
			if (!isset($fieldColumns[$field])) continue;
			$columns = (array) $fieldColumns[$field];
			$subClauses = [];
			foreach ($columns as $column) {
				if ($field === 'no' || $field === 'tag') {
					$subClauses[] = "{$column} = :{$field}{$suffix}";
				} else {
					$subClauses[] = "MATCH({$column}) AGAINST (:{$field}{$suffix} IN BOOLEAN MODE)";
				}
			}

			$clauses[] = '(' . implode(' OR ', $subClauses) . ')';
		}

		// A pasted tripcode in the Name field matches the stored tripcode /
		// secure tripcode columns by exact equality. Kept as its own clause (never
		// OR-ed alongside a MATCH()) so the optimizer can index_merge the two
		// tripcode indexes instead of falling back to a full table scan.
		if (isset($fields['name_tripcode'])) {
			$clauses[] = "(p.tripcode = :name_tripcode{$suffix} OR p.secure_tripcode = :name_tripcode{$suffix})";
		}

		// Half-open date range over the (UTC) post timestamp, served by idx_post_root.
		if (isset($fields['root_after'])) {
			$clauses[] = "(p.root >= :root_after{$suffix})";
		}

		if (isset($fields['root_before'])) {
			$clauses[] = "(p.root < :root_before{$suffix})";
		}

		if (!empty($boardUids)) {
			$boardClauses = [];
			foreach (array_keys($boardUids) as $index) {
				$boardClauses[] = "p.boardUID = :board_{$index}{$suffix}";
			}
			$clauses[] = '(' . implode(' OR ', $boardClauses) . ')';
		}

		if ($openingPostsOnly) {
			$clauses[] = '(p.is_op = 1)';
		}

		return implode(' AND ', $clauses);
	}

	/**
	 * Load full post data (with attachments, votes, notes) for the given UIDs.
	 */
	private function loadFullPostData(array $postUids): false|array {
		$fullQuery = getBasePostQuery($this->table, $this->deletedPostsTable, $this->fileTable, $this->threadTable, $this->soudaneTable, $this->noteTable, $this->accountTable, false, false, $this->countryFlagTable, $this->displayIpTable);
		$inClause = pdoPlaceholdersForIn($postUids);
		$fullQuery .= " WHERE p.post_uid IN {$inClause}";
		$fullQuery .= " ORDER BY p.root DESC, p.post_uid DESC";

		$postsResults = $this->queryAll($fullQuery, $postUids);

		return mergeMultiplePostRows($postsResults);
	}

	/**
	 * Build a lightweight search query for non-general fields.
	 * Only JOINs the files table when searching by filename.
	 */
	private function buildLightweightSearchQuery(string $select, array $fields, array $boardUids, bool $openingPostsOnly): string {
		$needsFileJoin = isset($fields['file_name']);

		$query = "{$select} FROM {$this->table} p";

		if ($needsFileJoin) {
			$query .= " LEFT JOIN {$this->fileTable} f ON f.post_uid = p.post_uid";
		}

		$searchClause = $this->buildSearchClause($fields, $boardUids, $openingPostsOnly);

		$whereParts = [
			'NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid')
		];

		if ($searchClause !== '') {
			$whereParts[] = $searchClause;
		}

		$query .= " WHERE " . implode(' AND ', $whereParts);

		return $query;
	}

	private function buildSearchClause(array $fields, array $boardUids, bool $openingPostsOnly): string {
		// init caluses
		$clauses = [];

		// map of searchable columns
		// (the post timestamp is not here: it is range-compared below, not full-text matched)
		$fieldColumns = [
			'com'       => 'p.com',
			'name'      => 'p.name',
			'email'     => 'p.email',
			'sub'       => 'p.sub',
			'no'        => 'p.no',
			'file_name' => 'f.file_name',
			'tag'       => 'p.tag',
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
				// if its a post number or tag search, use equality
				if ($field === 'no' || $field === 'tag') {
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

		// A pasted tripcode in the Name field matches the stored tripcode /
		// secure tripcode columns by exact equality. Kept as its own clause (never
		// OR-ed alongside a MATCH()) so the optimizer can index_merge the two
		// tripcode indexes instead of falling back to a full table scan.
		if (isset($fields['name_tripcode'])) {
			$clauses[] = '(p.tripcode = :name_tripcode OR p.secure_tripcode = :name_tripcode)';
		}

		// build the date range clause (if any)
		// the range is half-open — inclusive lower bound, exclusive upper bound — and
		// compares against the UTC timestamp column, which idx_post_root covers
		if (isset($fields['root_after'])) {
			$clauses[] = '(p.root >= :root_after)';
		}

		if (isset($fields['root_before'])) {
			$clauses[] = '(p.root < :root_before)';
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

		// build opening posts filter clause
		// if onlyOpeningPosts is true then only OPs will be fetched
		if($openingPostsOnly === true) {
			$clauses[] = '(p.is_op = 1)';
		}

		// combine all clauses with AND by default
		return implode(' AND ', $clauses);
	}
}
