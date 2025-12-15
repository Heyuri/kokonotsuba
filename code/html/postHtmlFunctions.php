<?php
/*
* Post html functions for Kokonotsuba!
* To avoid having to define html in multiple places
*/

/* Generate html for the post name dynamically */
function generatePostNameHtml(
	moduleEngine $moduleEngine, 
	string $name,
	string $tripcode,
	string $secure_tripcode,
	string $capcode,
	string $email,
	bool $noticeSage = false): string {
	// For compatability reasons, names already containing html will just be displayed without any further processing.
	// Because kokonotsuba previously stored name/trip/capcode html all in the name column, and this can cause double wrapped html
	if(containsHtmlTags($name)) return $name;

	// generate poster name
	$nameHtml = '<span class="postername">' . $name . '</span>';

	// run tripcode/capcode hook point
	$moduleEngine->dispatch('RenderTripcode', [&$nameHtml, &$tripcode, &$secure_tripcode, &$capcode]);

	// wrap name in email
	$nameHtml = generateEmailName($nameHtml, $email);
	
	// append SAGE!
	$nameHtml .= generateNoticeSage($noticeSage, $email);

	// return the name html
	return $nameHtml;
}

function generateEmailName(string $nameHtml, string $email): string {
	// if the email isn't empty then wrap it in an <a>
	if($email) {
		return '<a href="mailto:' . $email . '">'. $nameHtml . '</a>';
	} 
	// no email
	else {
		return $nameHtml;
	}
}

function generateNoticeSage(bool $noticeSage, string $email): string {
	// notice sage
	// return sageText span
	if ($noticeSage && str_contains($email, "sage")) {
		return ' <span class="sageText">SAGE!</span>';
	}
	// notice sage disabled or the post isn't a sage
	else {
		// so return nothing
		return '';
	}
}

/**
 * Generate HTML for quote links inside a post comment.
 *
 * This function scans a post's comment text for quote patterns such as ">>123"
 * and replaces them with clickable <a> links pointing to the quoted posts.
 *
 * Behavior:
 * - Uses quote link metadata from $quoteLinksFromBoard to determine which thread
 *   a quoted post belongs to, and what its position in that thread is.
 * - Calculates the correct page number for the quoted post using post_position,
 *   ensuring that links always include "&page=X" for imageboard-style pagination.
 * - Handles cross-thread quotes, strike-through of missing posts, and CSS classes.
 *
 * Requirements:
 * - $quoteLinksFromBoard must contain entries keyed by source post_uid, each
 *   containing an array of quote metadata including:
 *     - target_post['no']
 *     - target_post['post_op_number']
 *     - target_post['post_position'] (Nth post within the thread, 0-based)
 *
 * @param array $quoteLinksFromBoard All quote metadata for the thread, indexed by post_uid.
 * @param array $post The current post's data, including 'post_uid' and 'com'.
 * @param int $threadNumber Thread number (OP's post number) of the current thread.
 * @param bool $useQuoteSystem Whether quote links should be transformed.
 * @param board $board Board object used to generate URLs.
 * @param int $repliesPerPage Number of replies per page (e.g. 200).
 * @param int $totalReplies Total number of replies in the thread (not required for core logic).
 *
 * @return string The comment HTML with quote links replaced.
 */
function generateQuoteLinkHtml(array $quoteLinksFromBoard, array $post, int $threadNumber, bool $useQuoteSystem, board $board, int $repliesPerPage, int $totalReplies): string {
	// If quoting system disabled, or comment missing, or post missing an ID → return original content.
	if (
		empty($useQuoteSystem) ||
		empty($post['com']) ||
		empty($post['post_uid'])
	) {
		return $post['com'] ?? '';
	}
	
	$comment = $post['com'];
	$postUid = $post['post_uid'];

	// Get all quote references originating from this specific post.
	// This contains all metadata about the quoted posts.
	$quoteLinkEntries = $quoteLinksFromBoard[$postUid] ?? [];

	// Maps:
	//   - quoted post number → thread OP number
	//   - quoted post number → post_position (Nth post inside thread)
	$targetPostToThreadNumber = [];
	$targetPostToPosition = [];

	// Extract metadata for all quoted posts referenced from this post.
	foreach ($quoteLinkEntries as $entry) {
		// Validate required fields exist and are numeric.
		if (
			isset($entry['target_post']['no'], $entry['target_post']['post_op_number']) &&
			is_numeric($entry['target_post']['no']) &&
			is_numeric($entry['target_post']['post_op_number'])
		) {
			$postNo = (int)$entry['target_post']['no'];               // Quoted post number
			$threadNo = (int)$entry['target_post']['post_op_number']; // OP number of target thread
			$targetPostToThreadNumber[$postNo] = $threadNo;

			// If available, store the quoted post's position within its thread (0-based)
			if (isset($entry['target_post']['post_position']) && is_numeric($entry['target_post']['post_position'])) {
                $targetPostToPosition[$postNo] = (int)$entry['target_post']['post_position'];
            }
		}
	}

	// Find all ">>123" or "＞＞123" style quote markers inside the comment.
	if (!preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)) {
		return $comment; // No quote patterns found
	}
	
	// We'll build a map of original-string → replacement-string to replace them in one pass.
	$replacements = [];

	foreach ($matches as $match) {
		$fullMatch = $match[0];         // e.g. ">>123"
		$postNumber = (int)$match[2];   // Extracted "123"
	
		// Avoid duplicate replacements for the same ">>123" sequence.
		if (isset($replacements[$fullMatch])) {
			continue;
		}
	
		// Check whether we know the thread and metadata for the quoted post.
		if (isset($targetPostToThreadNumber[$postNumber])) {
			$targetThreadNumber = $targetPostToThreadNumber[$postNumber];

			// Determine whether the quoted post belongs to another thread.
			$isCrossThread = $targetThreadNumber !== $threadNumber;

			// Retrieve the post_position (Nth reply in its thread).
			// If not provided, default to 0 (OP).
			$postPosition = $targetPostToPosition[$postNumber] ?? 0;

			// Calculate page number:
			// - pages are 0-based
			// - page = floor(post_position / repliesPerPage)
			$page = ($postPosition < 0)
				? 0
				: (int)floor($postPosition / $repliesPerPage);

			// Build the final quoted post URL including the "&page=X" parameter.
			$url = htmlspecialchars(
				$board->getBoardThreadURL($targetThreadNumber, $postNumber, false, $page)
			);

			// Assign CSS classes — add crossThreadLink if needed.
			$linkClass = 'quotelink' . ($isCrossThread ? ' crossThreadLink' : '');

			// Final anchor tag replacement.
			$replacements[$fullMatch] = '<a href="' . $url . '" class="' . $linkClass . '">' . $fullMatch . '</a>';

		} else {
			// No metadata found → quoted post does not exist or cannot be resolved.
			// Strike-through the quote.
			$replacements[$fullMatch] = '<a href="javascript:void(0);" class="quotelink"><del>' . $fullMatch . '</del></a>';
		}
	}
	
	// Perform all replacements at once.
	return strtr($comment, $replacements);
}