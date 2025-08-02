<?php
/*
* Post html functions for Kokonotsuba!
* To avoid having to define html in multiple places
*/

/* Generate html for the post name dynamically */
function generatePostNameHtml(array $staffCapcodes,
	array $userCapcodes,
	?string $name = '', 
	?string $tripcode = '', 
	?string $secure_tripcode = '', 
	?string $capcode = '',
	string $email = '',
	bool $clearSage = false): string {
	// For compatability reasons, names already containing html will just be displayed without any further processing.
	// Because kokonotsuba previously stored name/trip/capcode html all in the name column, and this can cause double wrapped html
	if(containsHtmlTags($name)) return $name;

	$nameHtml = '<span class="postername">'.$name.'</span>';
	
	// Check for secure tripcode first; use ★ symbol if present
	if($secure_tripcode) {
		$nameHtml = $nameHtml.'<span class="postertrip">★'.$secure_tripcode.'</span>';
	}
	// Check for regular tripcode with ◆ symbol
	else if($tripcode) {
		$nameHtml = $nameHtml.'<span class="postertrip">◆'.$tripcode.'</span>';
	}

	// Check if either tripcode or secure tripcode has a defined capcode
	if (array_key_exists($tripcode, $userCapcodes) || array_key_exists($secure_tripcode, $userCapcodes)) {
		// Retrieve the corresponding capcode mapping (tripcode first, fallback to secure tripcode)
		$capcodeMap = $userCapcodes[$tripcode] ?? $userCapcodes[$secure_tripcode];
	
		// Extract the capcode color
		$capcodeColor = $capcodeMap['color'];

		// Extract the capcode text
		$capcodeText = $capcodeMap['cap'];

		// Wrap the name HTML and append capcode text, applying the capcode color
		$nameHtml = '<span class="capcodeSection" style="color:'.$capcodeColor.';">'.$nameHtml. '<span class="postercap">' .$capcodeText.'</span> </span>';
	}


	// If a capcode is provided, format the name accordingly
	if($capcode) {
		// Handle staff capcodes if defined in the config
		if(array_key_exists($capcode,$staffCapcodes)) {
			// Retrieve the corresponding capcode HTML template
			$capcodeMap = $staffCapcodes[$capcode];
			$capcodeHtml = $capcodeMap['capcodeHtml'];

			// Apply the capcode formatting (usually wraps or replaces nameHtml)
			$nameHtml = '<span class="postername">'.sprintf($capcodeHtml, $name).'</span>';
		}

	}

	// wrap name in email
	if($email) {
		$nameHtml = '<a href="mailto:'.$email.'">'. $nameHtml .'</a>';
	}
	
	// append SAGE!
	if (!$clearSage && str_contains($email, "sage")) {
		$nameHtml .= ' <span class="sageText">SAGE!</span>';
	}

	return $nameHtml;
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
	$quoteLinkEntries = $quoteLinksFromBoard[$postUid];

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