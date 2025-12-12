<?php
/**
 * postSearchService
 *
 *  Service class responsible for performing secure and efficient post searches.
 *
 * Features:
 * - Utilizes MySQL full-text search in BOOLEAN MODE for general keyword searches.
 * - Falls back to REGEXP/LIKE queries for:
 *   - Single quoted phrases (e.g., "unit-01")
 *   - Japanese (CJK) text inputs
 *
 * All input is sanitized and encoded to ensure safety and compatibility with stored post data.
 * Supports field targeting, word boundary enforcement, and flexible keyword logic (AND/OR).
 */

class postSearchService {
	public function __construct(
		private readonly postSearchRepository $postSearchRepository
	) {}

	public function searchPosts(IBoard $board, array $keywords, bool $matchWholeWord, string $field = 'com', string $method = 'OR', int $limit = 20, int $offset = 0): ?array {
		$field = $this->sanitizeField($field);
		$boardUID = $board->getBoardUID();
		$rawInput = implode(' ', $keywords);

		if ($this->isQuotedPhraseOnly($keywords)) {
			return $this->searchByLike($field, $boardUID, $keywords[0], $limit, $offset);
		}

		if ($this->isJapaneseInput($keywords)) {
			return $this->searchByLike($field, $boardUID, $rawInput, $limit, $offset);
		}

		$searchString = $this->buildFullTextSearchString($rawInput, $method, $matchWholeWord, true);
		if (!$searchString) {
			return ['results_data' => [], 'total_posts' => 0];
		}

		return $this->searchByFullText($field, $boardUID, $searchString, $limit, $offset);
	}

	private function sanitizeField(string $field): string {
		$allowedFields = ['com', 'name', 'sub', 'no'];
		return in_array($field, $allowedFields) ? $field : 'com';
	}

	private function isQuotedPhraseOnly(array $keywords): bool {
		return count($keywords) === 1 && preg_match('/^".+"$/', $keywords[0]);
	}

	private function isJapaneseInput(array $keywords): bool {
		foreach ($keywords as $term) {
			if (preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $term)) {
				return true;
			}
		}
		return false;
	}

	private function searchByLike(string $field, string $boardUID, string $phrase, int $limit, int $offset): ?array {
		$clean = mb_strtolower(trim($phrase, '"'));
		$encoded = htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5);

		if (mb_strlen($encoded) < 3) {
			return ['results_data' => [], 'total_posts' => 0];
		}

		$posts = $this->postSearchRepository->fetchPostsByLike($field, $boardUID, $encoded, $limit, $offset);
		$count = $this->postSearchRepository->countPostsByLike($field, $boardUID, $encoded);

		// no posts found
		if(!$posts || $count === 0) {
			return null;
		}

		return $this->formatResults($posts, $count);
	}

	private function searchByFullText(string $field, string $boardUID, string $searchString, int $limit, int $offset): ?array {
		$posts = $this->postSearchRepository->fetchPostsByFullText($field, $boardUID, $searchString, $limit, $offset);
		$count = $this->postSearchRepository->countPostsByFullText($field, $boardUID, $searchString);

		// no posts found - return null
		if(!$posts || $count === 0) {
			return null;
		}

		return $this->formatResults($posts, $count);
	}

	/**
	 * Build a FULLTEXT search string for BOOLEAN MODE or NATURAL LANGUAGE MODE.
	 *
	 * NATURAL MODE cannot contain boolean operators (+, -, *).
	 * BOOLEAN MODE allows all of them.
	 *
	 * This method detects whether BOOLEAN syntax is being used by checking:
	 * - $matchWholeWord (which controls '*' wildcard)
	 * - $method (which controls '+' for AND)
	 *
	 * If NATURAL MODE is used later, the caller must pass $forceNatural = true.
	 */
	private function buildFullTextSearchString(
		string $rawInput,
		string $method,
		bool $matchWholeWord,
		bool $forceNaturalMode = false // <-- NEW argument
	): ?string {

		// Remove unwanted characters while keeping quotes and word chars
		$rawInput = preg_replace('/[^\p{L}\p{N}\s"]+/u', '', $rawInput);

		// Extract quoted phrases or individual word tokens
		preg_match_all('/"([^"]+)"|[\p{L}\p{N}]+/u', $rawInput, $matches);
		$terms = $matches[0] ?? [];

		$cleanedTerms = [];

		foreach ($terms as $term) {

			$isQuoted = $term[0] === '"';

			// Normalize inside-phrase content
			$clean = mb_strtolower(trim($term, '"'));

			// Ignore tiny terms that MySQL won't index anyway (< 3 chars)
			if (mb_strlen($clean) < 3) continue;

			// ---------------------------------------------------------------------
			// CASE 1: Quoted phrase — leave as-is (MySQL supports it in both modes)
			// ---------------------------------------------------------------------
			if ($isQuoted) {
				$cleanedTerms[] = '"' . $clean . '"';
				continue;
			}

			// ---------------------------------------------------------------------
			// CASE 2: NATURAL LANGUAGE MODE
			// - BOOLEAN symbols are NOT allowed in this mode.
			// - We strip + and * to avoid SQL errors.
			// ---------------------------------------------------------------------
			if ($forceNaturalMode) {
				// NATURAL MODE requires plain terms only
				$cleanedTerms[] = $clean;
				continue;
			}

			// ---------------------------------------------------------------------
			// CASE 3: BOOLEAN MODE (default behavior)
			// - '+' for AND
			// - '*' wildcard when whole-word matching is off
			// ---------------------------------------------------------------------
			$token = strtoupper($method) === 'AND' ? '+' . $clean : $clean;

			if (!$matchWholeWord) {
				// '*' wildcard allowed only in BOOLEAN mode
				$token .= '*';
			}

			$cleanedTerms[] = $token;
		}

		// Return cleaned token list or null if empty
		return $cleanedTerms ? implode(' ', $cleanedTerms) : null;
	}

	private function formatResults(array $posts, int $totalPostCount): array {
		$results = [];
		foreach ($posts as $post) {
			$post_uid = $post['post_uid'];

			$results[$post_uid] = [
				'post' => $post,
			];
		}
		return ['results_data' => $results, 'total_posts' => $totalPostCount];
	}
}