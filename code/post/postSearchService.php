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

	public function searchPosts(IBoard $board, array $keywords, bool $matchWholeWord, string $field = 'com', string $method = 'OR', int $limit = 20, int $offset = 0): array {
		$field = $this->sanitizeField($field);
		$boardUID = $board->getBoardUID();
		$rawInput = implode(' ', $keywords);

		if ($this->isQuotedPhraseOnly($keywords)) {
			return $this->searchByLike($field, $boardUID, $keywords[0], $limit, $offset);
		}

		if ($this->isJapaneseInput($keywords)) {
			return $this->searchByLike($field, $boardUID, $rawInput, $limit, $offset);
		}

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

	private function searchByLike(string $field, string $boardUID, string $phrase, int $limit, int $offset): array {
		$clean = mb_strtolower(trim($phrase, '"'));
		$encoded = htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5);

		if (mb_strlen($encoded) < 3) {
			return ['results_data' => [], 'total_posts' => 0];
		}

		$posts = $this->postSearchRepository->fetchPostsByLike($field, $boardUID, $encoded, $limit, $offset);
		$count = $this->postSearchRepository->countPostsByLike($field, $boardUID, $encoded);

		return $this->formatResults($posts, $count);
	}

	private function searchByFullText(string $field, string $boardUID, string $searchString, int $limit, int $offset): array {
		$posts = $this->postSearchRepository->fetchPostsByFullText($field, $boardUID, $searchString, $limit, $offset);
		$count = $this->postSearchRepository->countPostsByFullText($field, $boardUID, $searchString);

		return $this->formatResults($posts, $count);
	}

	private function buildFullTextSearchString(string $rawInput, string $method, bool $matchWholeWord): ?string {
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
				if (!$matchWholeWord) $token .= '*';
				$cleanedTerms[] = $token;
			}
		}

		return $cleanedTerms ? implode(' ', $cleanedTerms) : null;
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
}