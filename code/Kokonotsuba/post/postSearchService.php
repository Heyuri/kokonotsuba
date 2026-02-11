<?php

namespace Kokonotsuba\post;

class postSearchService {
	public function __construct(
		private readonly postSearchRepository $postSearchRepository
	) {}

	/**
	 * Sanitizes user input for use in MySQL FULLTEXT (BOOLEAN MODE) searches.
	 *
	 * Removes MySQL boolean operators and special characters, keeps only
	 * letters, numbers, and whitespace (UTF-8 safe), and normalizes spacing.
	 *
	 * @param string $input Raw user search input
	 * @return string Sanitized string safe for FULLTEXT processing
	 */
	private function sanitizeFulltextInput(string $input): string {
		// Normalize encoding & trim
		$input = trim($input);

		// Remove MySQL boolean operators and special chars
		// + - > < ( ) ~ * " @
		$input = preg_replace('/[+\-><\(\)~*"@]/u', ' ', $input);

		// Keep letters, numbers, and spaces (UTF-8 safe)
		$input = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $input);

		// Collapse multiple spaces
		$input = preg_replace('/\s+/u', ' ', $input);

		return trim($input);
	}

	/**
	 * Converts sanitized user input into a MySQL FULLTEXT boolean search string.
	 *
	 * The input is tokenized, stopwords and short words are removed, and each
	 * remaining token is required in the search. When $matchWholeWord is false,
	 * prefix wildcards are applied to allow partial matches.
	 *
	 * Additionally, for tokens that may contain apostrophes, an HTML-encoded
	 * variant is included as an optional token, so searches match both
	 * plain text and stored HTML entity forms (e.g., don't vs don&#39;t).
	 *
	 * @param string $input Raw user search input
	 * @param bool $matchWholeWord Whether to match exact words without wildcards
	 * @param array $stopWords List or lookup table of FULLTEXT stopwords
	 * @param int $minWordLength Minimum token length to include
	 * @return string FULLTEXT-compatible boolean search string
	 */
	private function parseToBooleanFulltext(
		string $input,
		bool $matchWholeWord,
		array $stopWords,
		int $minWordLength = 3
	): string {
		$processedInput = $this->sanitizeFulltextInput($input);
		$words = explode(' ', $processedInput);

		// Ensure stopwords are a lookup table for fast O(1) checking
		$stopWordLookup = array_keys($stopWords) !== range(0, count($stopWords) - 1)
			? $stopWords
			: array_flip(array_map('mb_strtolower', $stopWords));

		// Filter out short words and stopwords
		$words = array_filter(
			$words,
			fn($word) =>
				mb_strlen($word) >= $minWordLength &&
				!isset($stopWordLookup[mb_strtolower($word)])
		);

		$tokens = [];

		foreach ($words as $word) {
			// Normal token
			$token = $matchWholeWord ? '+' . $word : '+' . $word . '*';
			$tokens[] = $token;

			// HTML entity variant for apostrophes
			if (str_contains($word, "'")) {
				$encodedWord = str_replace("'", '&#39;', $word);
				$encodedToken = $matchWholeWord ? '+' . $encodedWord : '+' . $encodedWord . '*';
				$tokens[] = $encodedToken;
			}
		}

		return implode(' ', $tokens);
	}


	public function searchPosts(
		array $stopWords, 
		array $fields, 
		array $boardUids, 
		bool $matchWholeWords, 
		bool $openingPostOnly = false,
		int $page = 0, 
		int $postsPerPage = 20
	): ?array {
		// sanitize fields
		$fields = $this->sanitizeFields($fields);

		// tokenize and compile each field for boolean full-text search
		foreach ($fields as $field => $value) {
			// dont parse post number
			if($field === 'no') {
				continue;
			}

			$fields[$field] = $this->parseToBooleanFulltext($value, $matchWholeWords, $stopWords);
		}

		// calculate pagination parameters
		$offset = $page * $postsPerPage;

		return $this->searchByFullText($fields, $boardUids, $openingPostOnly, $postsPerPage, $offset);
	}

	private function sanitizeFields(array $fields): array {
		// Define allowed fields
		$allowedFields = [
			// general, searches all text fields
			'general', 

			// comment field
			'com', 
			
			// name field
			'name', 
			
			// email field
			'email',
			
			// subject field
			'sub', 
			
			// post number
			'no', 
			
			// file name field for any files attached to the post
			'file_name', 
			
			// timestamp of the post
			'root'
		];

		// Remove any fields that are not allowed
		$fields = array_intersect_key($fields, array_flip($allowedFields));

		// loop through and remove empty fields
		$fields = array_filter($fields, fn($field) => !empty($field));

		return $fields;
	}

	private function searchByFullText(array $fields, array $boardUids, bool $openingPostsOnly, int $limit, int $offset): ?array {
		$posts = $this->postSearchRepository->fetchPostsByFullText($fields, $boardUids, $openingPostsOnly, $limit, $offset);
		$count = $this->postSearchRepository->countPostsByFullText($fields, $boardUids, $openingPostsOnly);

		// no posts found - return null
		if(!$posts || $count === 0) {
			return null;
		}

		return $this->formatResults($posts, $count);
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