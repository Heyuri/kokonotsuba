<?php
/*
* Post html functions for Kokonotsuba!
* To avoid having to define html in multiple places
*/

namespace Kokonotsuba\libraries\html;

use Kokonotsuba\board\board;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;

use function Puchiko\strings\containsHtmlTags;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\strings\sanitizeStr;

/**
 * Calculate which page a post belongs to based on its position in the thread.
 * OP is position 0 and always on page 0. Replies (position >= 1) are paginated
 * separately, so position is offset by 1 before dividing.
 */
function getPageForPostPosition(int $postPosition, int $repliesPerPage): int {
	return ($postPosition <= 0) ? 0 : (int)floor(($postPosition - 1) / $repliesPerPage);
}

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
		$escapedEmail = sanitizeStr($email);
		return '<a href="mailto:' . $escapedEmail . '">'. $nameHtml . '</a>';
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
 * @param Post $post The current post's data, including 'post_uid' and 'com'.
 * @param int $threadNumber Thread number (OP's post number) of the current thread.
 * @param bool $useQuoteSystem Whether quote links should be transformed.
 * @param board $board Board object used to generate URLs.
 * @param int $repliesPerPage Number of replies per page (e.g. 200).
 * @param int $totalReplies Total number of replies in the thread (not required for core logic).
 *
 * @return string The comment HTML with quote links replaced.
 */
function generateQuoteLinkHtml(
	array $quoteLinksFromBoard, 
	Post $post, 
	int $threadNumber, 
	bool $useQuoteSystem, 
	board $board, 
	int $repliesPerPage 
): string {
	// If quoting system disabled, or comment missing, or post missing an ID → return original content.
	if (
		empty($useQuoteSystem) ||
		empty($post->getComment()) ||
		empty($post->getUid())
	) {
		return $post->getComment() ?? '';
	}
	
	$comment = $post->getComment();
	$postUid = $post->getUid();

	// Get all quote references originating from this specific post.
	// This contains all metadata about the quoted posts.
	$quoteLinkEntries = $quoteLinksFromBoard[$postUid] ?? [];

	// Maps for same-board quote links:
	//   - quoted post number → thread OP number
	//   - quoted post number → post_position (Nth post inside thread)
	$targetPostToThreadNumber = [];
	$targetPostToPosition = [];
	$targetPostToUid = [];

	// Map for cross-board quote links: boardIdentifier → postNo → target data
	$crossBoardTargets = [];

	$currentBoardUid = $board->getBoardUID();

	// Extract metadata for all quoted posts referenced from this post.
	foreach ($quoteLinkEntries as $entry) {
		// Validate required fields exist and are numeric.
		if (
			isset($entry['target_post']['no'], $entry['target_post']['post_op_number']) &&
			is_numeric($entry['target_post']['no']) &&
			is_numeric($entry['target_post']['post_op_number'])
		) {
			$postNo = (int)$entry['target_post']['no'];
			$threadNo = (int)$entry['target_post']['post_op_number'];
			$targetBoardUid = (int)($entry['target_post']['board_uid'] ?? $currentBoardUid);

			if ($targetBoardUid === $currentBoardUid) {
				// Same-board entry
				$targetPostToThreadNumber[$postNo] = $threadNo;

				if (isset($entry['target_post']['post_uid'])) {
					$targetPostToUid[$postNo] = (int)$entry['target_post']['post_uid'];
				}

				if (isset($entry['target_post']['post_position']) && is_numeric($entry['target_post']['post_position'])) {
					$targetPostToPosition[$postNo] = (int)$entry['target_post']['post_position'];
				}
			} else {
				// Cross-board entry
				$targetBoard = searchBoardArrayForBoard($targetBoardUid);
				if ($targetBoard) {
					$identifier = $targetBoard->getBoardIdentifier();
					$crossBoardTargets[$identifier][$postNo] = [
						'threadNo' => $threadNo,
						'position' => (int)($entry['target_post']['post_position'] ?? 0),
						'uid' => isset($entry['target_post']['post_uid']) ? (int)$entry['target_post']['post_uid'] : null,
						'board' => $targetBoard,
					];
				}
			}
		}
	}

	$replacements = [];

	// Match cross-board patterns first: >>>/board/123
	if (preg_match_all('/((?:&gt;|＞){3})\/([a-zA-Z0-9]+)\/(\d+)/i', $comment, $crossMatches, PREG_SET_ORDER)) {
		foreach ($crossMatches as $match) {
			$fullMatch = $match[0];
			$boardIdentifier = $match[2];
			$postNumber = (int)$match[3];

			if (isset($replacements[$fullMatch])) {
				continue;
			}

			if (isset($crossBoardTargets[$boardIdentifier][$postNumber])) {
				$target = $crossBoardTargets[$boardIdentifier][$postNumber];
				$postPosition = (int)($target['position'] ?? 0);
				$targetRepliesPerPage = $target['board']->getConfigValue('REPLIES_PER_PAGE', 200);
				$page = getPageForPostPosition($postPosition, $targetRepliesPerPage);

				$url = htmlspecialchars(
					$target['board']->getBoardThreadURL($target['threadNo'], $postNumber, false, $page)
				);

				$linkClass = 'quotelink crossBoardLink';
				$uidAttr = isset($target['uid'])
					? ' data-post-uid="' . $target['uid'] . '"'
					: '';

				$replacements[$fullMatch] = '<a href="' . $url . '" class="' . $linkClass . '"' . $uidAttr . '>' . $fullMatch . '</a>';
			} elseif ($boardIdentifier === $board->getBoardIdentifier() && isset($targetPostToThreadNumber[$postNumber])) {
				// Cross-board syntax pointing to the current board — use same-board data
				$targetThreadNumber = $targetPostToThreadNumber[$postNumber];
				$isCrossThread = $targetThreadNumber !== $threadNumber;
				$postPosition = $targetPostToPosition[$postNumber] ?? 0;
				$page = getPageForPostPosition($postPosition, $repliesPerPage);

				$url = htmlspecialchars(
					$board->getBoardThreadURL($targetThreadNumber, $postNumber, false, $page)
				);

				$linkClass = 'quotelink crossBoardLink' . ($isCrossThread ? ' crossThreadLink' : '');
				$uidAttr = isset($targetPostToUid[$postNumber])
					? ' data-post-uid="' . $targetPostToUid[$postNumber] . '"'
					: '';

				$replacements[$fullMatch] = '<a href="' . $url . '" class="' . $linkClass . '"' . $uidAttr . '>' . $fullMatch . '</a>';
			} else {
				// Not resolved — strike through
				$replacements[$fullMatch] = '<a href="javascript:void(0);" class="quotelink crossBoardLink"><del>' . $fullMatch . '</del></a>';
			}
		}
	}

	// Match same-board patterns: >>123 or >>No.123
	if (!preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)) {
		if (empty($replacements)) {
			return $comment;
		}
		return strtr($comment, $replacements);
	}

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

			$page = getPageForPostPosition($postPosition, $repliesPerPage);

			// Build the final quoted post URL including the "&page=X" parameter.
			$url = htmlspecialchars(
				$board->getBoardThreadURL($targetThreadNumber, $postNumber, false, $page)
			);

			// Assign CSS classes — add crossThreadLink if needed.
			$linkClass = 'quotelink' . ($isCrossThread ? ' crossThreadLink' : '');

			// Include the target post UID as a data attribute for JS hover previews.
			$uidAttr = isset($targetPostToUid[$postNumber])
				? ' data-post-uid="' . $targetPostToUid[$postNumber] . '"'
				: '';

			// Final anchor tag replacement.
			$replacements[$fullMatch] = '<a href="' . $url . '" class="' . $linkClass . '"' . $uidAttr . '>' . $fullMatch . '</a>';

		} else {
			// No metadata found → quoted post does not exist or cannot be resolved.
			// Strike-through the quote.
			$replacements[$fullMatch] = '<a href="javascript:void(0);" class="quotelink"><del>' . $fullMatch . '</del></a>';
		}
	}
	
	// Perform all replacements at once.
	return strtr($comment, $replacements);
}