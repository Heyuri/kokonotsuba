<?php

/**
 * Sanitizes user input for use in MySQL FULLTEXT (BOOLEAN MODE) searches.
 *
 * Removes MySQL boolean operators and special characters, keeps only
 * letters, numbers, and whitespace (UTF-8 safe), and normalizes spacing.
 *
 * @param string $input Raw user search input
 * @return string Sanitized string safe for FULLTEXT processing
 */
function sanitizeFulltextInput(string $input): string {
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
function parseToBooleanFulltext(
	string $input,
	bool $matchWholeWord,
	array $stopWords,
	int $minWordLength = 3
): string {
	$processedInput = sanitizeFulltextInput($input);
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
