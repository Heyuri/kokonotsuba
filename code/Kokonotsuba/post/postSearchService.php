<?php

namespace Kokonotsuba\post;

use DateTimeImmutable;
use DateTimeZone;

/** Service for full-text post searching: field sanitization, boolean query building, and result pagination. */
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
	 * Converts user input into a MySQL FULLTEXT boolean search string.
	 *
	 * The input is tokenized, stopwords and short words are removed, and each
	 * remaining token is compiled according to the chosen combine mode:
	 *   - 'and' (default): every term is required (+term).
	 *   - 'or': terms are optional, so a post matching any of them is returned.
	 * A term the user prefixes with '-' is excluded (-term) in both modes.
	 * When $matchWholeWord is false, prefix wildcards allow partial matches.
	 *
	 * Additionally, for tokens that may contain apostrophes, an HTML-encoded
	 * variant is included, so searches match both plain text and stored HTML
	 * entity forms (e.g., don't vs don&#39;t).
	 *
	 * @param string $input Raw user search input
	 * @param bool $matchWholeWord Whether to match exact words without wildcards
	 * @param array $stopWords List or lookup table of FULLTEXT stopwords
	 * @param int $minWordLength Minimum token length to include
	 * @param string $searchMode How to combine terms: 'and' (all required) or 'or' (any)
	 * @return string FULLTEXT-compatible boolean search string
	 */
	private function parseToBooleanFulltext(
		string $input,
		bool $matchWholeWord,
		array $stopWords,
		int $minWordLength = 3,
		string $searchMode = 'and'
	): string {
		// Split the raw input on whitespace first so a leading '-' (exclusion) can be
		// detected before the sanitizer strips operator characters from the word body.
		$rawWords = preg_split('/\s+/u', trim($input)) ?: [];

		// Ensure stopwords are a lookup table for fast O(1) checking
		$stopWordLookup = array_keys($stopWords) !== range(0, count($stopWords) - 1)
			? $stopWords
			: array_flip(array_map('mb_strtolower', $stopWords));

		$tokens = [];

		foreach ($rawWords as $rawWord) {
			if ($rawWord === '') {
				continue;
			}

			// A leading '-' marks the word for exclusion (works in AND and OR modes).
			$exclude = str_starts_with($rawWord, '-');

			// Sanitizing may split one raw token into several words (internal
			// punctuation becomes whitespace); apply the exclusion flag to each.
			$sanitized = $this->sanitizeFulltextInput($rawWord);
			if ($sanitized === '') {
				continue;
			}

			foreach (explode(' ', $sanitized) as $word) {
				// Skip short words and stopwords
				if (mb_strlen($word) < $minWordLength || isset($stopWordLookup[mb_strtolower($word)])) {
					continue;
				}

				$tokens[] = $this->buildFulltextToken($word, $matchWholeWord, $exclude, $searchMode);

				// HTML entity variant for apostrophes
				if (str_contains($word, "'")) {
					$encodedWord = str_replace("'", '&#39;', $word);
					$tokens[] = $this->buildFulltextToken($encodedWord, $matchWholeWord, $exclude, $searchMode);
				}
			}
		}

		return implode(' ', $tokens);
	}

	/**
	 * Compile a single sanitized word into a FULLTEXT boolean token.
	 *
	 * @param string $word Sanitized word body
	 * @param bool $matchWholeWord Whether to append a prefix wildcard
	 * @param bool $exclude Whether the word should be excluded from results
	 * @param string $searchMode 'and' (required) or 'or' (optional) for non-excluded words
	 * @return string A single boolean-mode token, e.g. "+cat*", "cat*" or "-dog*"
	 */
	private function buildFulltextToken(string $word, bool $matchWholeWord, bool $exclude, string $searchMode): string {
		// '-' excludes; '+' requires (AND); OR mode uses no operator so any term may match.
		$prefix = $exclude ? '-' : ($searchMode === 'or' ? '' : '+');
		$suffix = $matchWholeWord ? '' : '*';

		return $prefix . $word . $suffix;
	}


	/**
	 * Sanitize search field values, compile them into boolean full-text tokens, and return paginated results.
	 *
	 * @param array  $stopWords       Map of stop words to exclude from the search.
	 * @param array  $fields          Map of field name => raw search value.
	 * @param array  $boardUids       Board UIDs to restrict the search to (empty = all boards).
	 * @param bool   $matchWholeWords If true, match whole words rather than using prefix wildcards.
	 * @param bool   $openingPostOnly If true, return only OP posts.
	 * @param int    $page            Zero-based page number.
	 * @param int    $postsPerPage    Number of posts per page.
	 * @param string $searchMode      How to combine keywords: 'and' (all required) or 'or' (any). '-word' always excludes.
	 * @param string|null $dateAfter  Inclusive lower bound on the post timestamp, as a UTC 'Y-m-d H:i:s' string.
	 * @param string|null $dateBefore Exclusive upper bound on the post timestamp, as a UTC 'Y-m-d H:i:s' string.
	 * @return array|null Associative array with 'results_data' and 'total_posts', or null if no results.
	 */
	public function searchPosts(
		array $stopWords,
		array $fields,
		array $boardUids,
		bool $matchWholeWords,
		bool $openingPostOnly = false,
		int $page = 1,
		int $postsPerPage = 20,
		string $searchMode = 'and',
		?string $dateAfter = null,
		?string $dateBefore = null
	): ?array {
		// Normalize the combine mode; anything other than 'or' falls back to 'and'.
		$searchMode = strtolower($searchMode) === 'or' ? 'or' : 'and';

		// sanitize fields
		$fields = $this->sanitizeFields($fields);

		// Capture the raw name value before full-text tokenization mangles it.
		// A user may paste a tripcode (e.g. "!Ep8pui8Vw2") into the Name field, and we
		// want that to also match posts by their stored tripcode / secure tripcode.
		$tripcodeCandidate = isset($fields['name']) ? $this->extractTripcodeCandidate($fields['name']) : '';

		// tokenize and compile each field for boolean full-text search
		foreach ($fields as $field => $value) {
			// dont parse post number or tag (exact match fields)
			if($field === 'no' || $field === 'tag') {
				continue;
			}

			$fields[$field] = $this->parseToBooleanFulltext($value, $matchWholeWords, $stopWords, 3, $searchMode);
		}

		// A genuine pasted tripcode is a trip search, not a name search: the name
		// FULLTEXT token would just be the trip hash and match nothing useful. Drop
		// it and hand the repository only the trip candidate, which it matches against
		// the indexed tripcode / secure_tripcode columns. Kept separate from the
		// full-text token because tripcodes contain characters the sanitizer strips.
		if ($tripcodeCandidate !== '' && isset($fields['name'])) {
			unset($fields['name']);
			$fields['name_tripcode'] = $tripcodeCandidate;
		}

		// The date range is compared against the timestamp column rather than tokenized,
		// so it is added after the full-text pass. It arrives as its own arguments (not
		// through $fields) so a caller cannot smuggle a raw value into the comparison.
		$fields += $this->buildDateRangeFields($dateAfter, $dateBefore);

		// calculate pagination parameters
		$offset = ($page - 1) * $postsPerPage;

		return $this->searchByFullText($fields, $boardUids, $openingPostOnly, $postsPerPage, $offset);
	}

	/**
	 * Extract a tripcode candidate from a raw Name-field value.
	 *
	 * Display renders a trip as the poster's name followed by a marker and the
	 * stored hash (e.g. "test◆ViBjFlRv5." for a regular trip, "★…" for a secure
	 * one). When a user pastes that whole string into the Name search field we
	 * take everything after the marker as the trip hash to match against the
	 * tripcode / secure_tripcode columns:
	 *
	 *   "test◆ViBjFlRv5."  -> "ViBjFlRv5."
	 *   "◆ViBjFlRv5."      -> "ViBjFlRv5."
	 *   "Anonymous"         -> ""   (no marker: ordinary name search)
	 *
	 * Only '◆' (regular) and '★' (secure) are treated as markers — they cannot
	 * legitimately appear in a name (they are flagged as fraud symbols on post),
	 * so finding one unambiguously signals a pasted trip. The legacy '!' posting
	 * marker is intentionally excluded: it can occur in real names, and what
	 * follows it when posting is the secret, not the stored hash.
	 *
	 * A plain name (no marker) returns ''. This also keeps such searches on the
	 * fast ft_name FULLTEXT path: a candidate makes the repository add an exact
	 * tripcode comparison, which only the trip indexes (not ft_name) can serve.
	 *
	 * @param string $name Raw name search value.
	 * @return string Hash to compare against tripcode / secure_tripcode columns, or '' if the input has no trip marker.
	 */
	private function extractTripcodeCandidate(string $name): string {
		// Take everything after the first trip marker (which may sit after a name).
		if (preg_match('/[◆♦★]+(.*)$/u', trim($name), $matches) !== 1) {
			return '';
		}

		return trim($matches[1]);
	}

	/**
	 * Validate the date-range bounds and turn them into repository search fields.
	 *
	 * The range is half-open: 'root_after' is compared with >= and 'root_before' with
	 * <, so a caller wanting a whole calendar day included passes midnight of the day
	 * after it. Both bounds are UTC, matching how post timestamps are stored — the
	 * caller is responsible for converting from whatever time zone it displays. A
	 * bound that is not a well-formed datetime is dropped rather than rejected, so a
	 * mangled URL narrows the search instead of erroring out.
	 *
	 * @param string|null $dateAfter  Lower bound as a UTC 'Y-m-d H:i:s' string.
	 * @param string|null $dateBefore Upper bound as a UTC 'Y-m-d H:i:s' string.
	 * @return array Map with optional 'root_after' / 'root_before' keys.
	 */
	private function buildDateRangeFields(?string $dateAfter, ?string $dateBefore): array {
		$after = $this->normalizeSqlDateTime($dateAfter);
		$before = $this->normalizeSqlDateTime($dateBefore);

		// A reversed range can never match; read it as the range the user meant.
		if ($after !== null && $before !== null && $after > $before) {
			[$after, $before] = [$before, $after];
		}

		$dateFields = [];

		if ($after !== null) {
			$dateFields['root_after'] = $after;
		}

		if ($before !== null) {
			$dateFields['root_before'] = $before;
		}

		return $dateFields;
	}

	/**
	 * Accept a UTC 'Y-m-d H:i:s' string, rejecting anything that is not exactly that.
	 *
	 * Values that only look like dates (impossible days such as 2026-02-31, or extra
	 * trailing text) fail the round-trip check and return null.
	 *
	 * @param string|null $value Raw bound value.
	 * @return string|null The normalized datetime, or null if the input is empty or malformed.
	 */
	private function normalizeSqlDateTime(?string $value): ?string {
		$value = trim((string) $value);

		if ($value === '') {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

		if ($date === false || $date->format('Y-m-d H:i:s') !== $value) {
			return null;
		}

		return $value;
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

			// tag (exact match abbreviation)
			'tag',
		];

		// note: the post timestamp ('root') is deliberately absent — it is a datetime
		// with no FULLTEXT index, and is filtered through the $dateAfter / $dateBefore
		// arguments of searchPosts() instead.

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
			$post_uid = $post->getUid();

			$results[$post_uid] = [
				'post' => $post,
			];
		}
		return ['results_data' => $results, 'total_posts' => $totalPostCount];
	}
}