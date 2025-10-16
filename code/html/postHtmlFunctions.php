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
 * Replace quote links in a post comment with anchor tags.
 * If the quoted post exists (based on quote links), it links to that post.
 * If not, the quote is displayed as a deleted reference using <del>.
 */
function generateQuoteLinkHtml(array $quoteLinksFromBoard, array $post, int $threadNumber, bool $useQuoteSystem, board $board): string {
	if (
		empty($useQuoteSystem) ||
		empty($post['com']) ||
		empty($post['post_uid'])
	) {
		return $post['com'] ?? '';
	}
	
	$comment = $post['com'];
	$postUid = $post['post_uid'];

	// Safely get quoteLink entries for this specific post	
	$quoteLinkEntries = $quoteLinksFromBoard[$postUid] ?? [];

	// Index target post numbers to their thread number
	$targetPostToThreadNumber = [];
	foreach ($quoteLinkEntries as $entry) {
		if (
			isset($entry['target_post']['no'], $entry['target_post']['post_op_number']) &&
			is_numeric($entry['target_post']['no']) &&
			is_numeric($entry['target_post']['post_op_number'])
		) {
			$postNo = (int)$entry['target_post']['no'];
			$threadNo = (int)$entry['target_post']['post_op_number'];
			$targetPostToThreadNumber[$postNo] = $threadNo;
		}
	}

	// Match all quote-like strings in the comment
	if (!preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)) {
		return $comment;
	}
	
	// Build replacements in one pass
	$replacements = [];
	foreach ($matches as $match) {
		$fullMatch = $match[0];         // Full quoted text (e.g. ">>123")
		$postNumber = (int)$match[2];   // Extracted numeric part (e.g. 123)
	
		if (isset($replacements[$fullMatch])) {
			continue; // Skip duplicates
		}
	
		// Check if we have a known thread number for the quoted post number
		if (isset($targetPostToThreadNumber[$postNumber])) {
			// Get the thread number where the quoted post resides
			$targetThreadNumber = $targetPostToThreadNumber[$postNumber];

			// Determine if the quoted post is in a different thread (i.e. cross-thread)
			$isCrossThread = $targetThreadNumber !== $threadNumber;

			// Generate the full URL to the quoted post
			$url = htmlspecialchars($board->getBoardThreadURL($targetThreadNumber, $postNumber));

			// Assign a CSS class, adding 'crossThreadLink' if the post is in a different thread
			$linkClass = 'quotelink' . ($isCrossThread ? ' crossThreadLink' : '');

			// Build the final anchor tag for replacement
			$replacements[$fullMatch] = '<a href="' . $url . '" class="' . $linkClass . '">' . $fullMatch . '</a>';
		} else {
			// Post was not found — strike out
			$replacements[$fullMatch] = '<a href="javascript:void(0);" class="quotelink"><del>' . $fullMatch . '</del></a>';
		}
	}
	
	// Replace in one pass
	return strtr($comment, $replacements);
}