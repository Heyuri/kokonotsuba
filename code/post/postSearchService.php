<?php
/**
 * postSearchService
 *
 * Singleton service that performs secure post searches.
 * - Uses full-text BOOLEAN MODE for most searches
 * - Falls back to LIKE only for single quoted phrases (e.g., "unit-01")
 * All search terms are sanitized and encoded to match stored post data.
 */

class postSearchService {
	private string $postTable;
	private string $threadTable;
	private DatabaseConnection $databaseConnection;
	private static $instance;

	private function __construct(string $postTable, string $threadTable, DatabaseConnection $databaseConnection) {
		$this->postTable = $postTable;
		$this->threadTable = $threadTable;
		$this->databaseConnection = $databaseConnection;
	}

	public static function getInstance() {
		return self::$instance;
	}

	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			$postTable = $dbSettings['POST_TABLE'];
			$threadTable = $dbSettings['THREAD_TABLE'];
			$databaseConnection = DatabaseConnection::getInstance();

			self::$instance = new self($postTable, $threadTable, $databaseConnection);
		}
		return self::$instance;
	}

	/**
	 * Performs a post search using either full-text or LIKE (if quoted phrase).
	 */
	public function searchPosts(IBoard $board, array $keywords, bool $matchWholeWord, string $field = 'com', string $method = 'OR', int $limit = 20, int $offset = 0): array {
		$field = $this->sanitizeField($field);
		$boardUID = $board->getBoardUID();
		$rawInput = implode(' ', $keywords);
	
		// Quoted phrase -> use LIKE
		if ($this->isQuotedPhraseOnly($keywords)) {
			return $this->searchByLike($field, $boardUID, $keywords[0], $limit, $offset);
		}
	
		// Japanese-only input -> use LIKE
		if ($this->isJapaneseInput($keywords)) {
			return $this->searchByLike($field, $boardUID, $rawInput, $limit, $offset);
		}
	
		// Fallback to full-text search
		$searchString = $this->buildFullTextSearchString($rawInput, $method, $matchWholeWord);
		if (!$searchString) {
			return ['results_data' => [], 'total_posts' => 0];
		}
	
		return $this->searchByFullText($field, $boardUID, $searchString, $limit, $offset);
	}	

	private function sanitizeField(string $field): string {
		$allowedFields = ['com', 'name', 'sub', 'no'];
		return in_array($field, $allowedFields) ? $field : 'com';
	}

	/**
	 * Checks if the input is a single quoted phrase (e.g., "unit-01").
	 */
	private function isQuotedPhraseOnly(array $keywords): bool {
		return count($keywords) === 1 && preg_match('/^".+"$/', $keywords[0]);
	}

	/**
	 * Handles exact phrase search using LIKE for quoted input.
	 */
	private function searchByLike(string $field, string $boardUID, string $quoted, int $limit, int $offset): array {
		$clean = mb_strtolower(trim($quoted, '"'));
		$encoded = htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5);
	
		if (mb_strlen($encoded) < 3) {
			return ['results_data' => [], 'total_posts' => 0];
		}
	
		// Use REGEXP to enforce whole word matching
		$params = [
			':phrase' => '[[:<:]]' . $encoded . '[[:>:]]',
			':board_uid' => $boardUID
		];
	
		$query = "
			SELECT p.*, t.*
			FROM {$this->postTable} p
			LEFT JOIN {$this->threadTable} t ON p.thread_uid = t.thread_uid
			WHERE p.$field REGEXP :phrase AND p.boardUID = :board_uid
			ORDER BY p.no DESC
			LIMIT $limit OFFSET $offset
		";
	
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		if (empty($posts)) {
			return [];
		}
	
		$countQuery = "
			SELECT COUNT(*) AS total_posts
			FROM {$this->postTable}
			WHERE $field REGEXP :phrase AND boardUID = :board_uid
		";
	
		$totalPostCount = $this->databaseConnection->fetchOne($countQuery, $params)['total_posts'] ?? 0;
	
		return $this->formatResults($posts, $totalPostCount);
	}	

	/**
	 * Constructs a full-text search string from raw user input.
	 */
	private function buildFullTextSearchString(string $rawInput, string $method, bool $matchWholeWord): ?string {
		// Remove all non-letter/number characters except quotes and whitespace
		$rawInput = preg_replace('/[^\p{L}\p{N}\s"]+/u', '', $rawInput);
	
		preg_match_all('/"([^"]+)"|[\p{L}\p{N}]+/u', $rawInput, $matches);
		$terms = $matches[0] ?? [];
	
		$cleanedTerms = [];
		foreach ($terms as $term) {
			$isQuoted = $term[0] === '"';
			$clean = mb_strtolower(trim($term, '"'));
	
			if (mb_strlen($clean) < 3) continue;
	
			if ($isQuoted) {
				$cleanedTerms[] = '"' . $clean . '"';
			} else {
				$token = strtoupper($method) === 'AND' ? '+' . $clean : $clean;
				
				if($matchWholeWord === false) {
					$token .= '*';
				}

				$cleanedTerms[] = $token;
			}
		}
	
		return $cleanedTerms ? implode(' ', $cleanedTerms) : null;
	}	

	/**
	 * Executes a full-text boolean search on the selected field.
	 */
	private function searchByFullText(string $field, string $boardUID, string $searchString, int $limit, int $offset): array {
		$params = [
			':search' => $searchString,
			':board_uid' => $boardUID
		];

		$countQuery = "
			SELECT COUNT(*) AS total_posts
			FROM {$this->postTable}
			WHERE MATCH($field) AGAINST (:search IN BOOLEAN MODE)
			AND boardUID = :board_uid
		";
		$totalPostCount = $this->databaseConnection->fetchOne($countQuery, $params)['total_posts'] ?? 0;

		$query = "
			SELECT p.*, t.*
			FROM {$this->postTable} p
			LEFT JOIN {$this->threadTable} t ON p.thread_uid = t.thread_uid
			WHERE MATCH(p.$field) AGAINST (:search IN BOOLEAN MODE)
			AND p.boardUID = :board_uid
			ORDER BY p.no DESC
			LIMIT $limit OFFSET $offset
		";

		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
		return $this->formatResults($posts, $totalPostCount);
	}

	private function formatResults(array $posts, int $totalPostCount): array {
		$results = [];
		foreach ($posts as $post) {
			$post_uid = $post['post_uid'];
			$thread = [
				'thread_uid' => $post['thread_uid'],
				'thread_created_time' => $post['thread_created_time'],
				'last_bump_time' => $post['last_bump_time'],
				'last_reply_time' => $post['last_reply_time'],
				'post_op_number' => $post['post_op_number']
			];

			$results[$post_uid] = [
				'post' => $post,
				'thread' => $thread,
			];
		}
		return ['results_data' => $results, 'total_posts' => $totalPostCount];
	}

	/**
 	* Detects if the entire search input is Japanese (CJK).
	*/
	private function isJapaneseInput(array $keywords): bool {
		foreach ($keywords as $term) {
			// If any term contains Hiragana, Katakana, or Kanji, treat it as Japanese
			if (preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $term)) {
				return true;
			}
		}
		return false;
	}	

}
