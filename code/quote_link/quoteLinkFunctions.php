<?php
/*
* Quote link functions for Kokonotsuba!
* Business logic for managing quote links
*/


function createQuoteLinksFromArray(IBoard $board, int $postUid, array $targetPostUids): void {
	$boardUid = $board->getBoardUID();

	$quoteLinksToInsert = [];
	foreach($targetPostUids as $targetPostUid) {
		$quoteLinksToInsert[] = ['board_uid' => $boardUid,
			'host_post_uid' => $postUid,
			'target_post_uid' => $targetPostUid,
		];
	}

	// Retrieve the singleton instance responsible for quote links
	$quoteLinkSingleton = quoteLinkSingleton::getInstance();

	$quoteLinkSingleton->insertQuoteLinks($quoteLinksToInsert);
}

function moveQuoteLink(int $postUid, IBoard $board): void {
// to do
}


// Retrieves the QuoteLink associated with a given post.
function getQuoteLinkFromPost(array $post): quoteLink {
	// Ensure the post array is not empty
	if (empty($post)) {
		throw new Exception(__FUNCTION__ . ": Post array is empty.");
	}

	// Ensure the 'post_uid' key exists
	if (!isset($post['post_uid'])) {
		throw new Exception(__FUNCTION__ . ": Missing 'post_uid' in post array.");
	}

	$postUid = $post['post_uid'];

	// Retrieve the singleton instance responsible for quote links
	$quoteLinkSingleton = quoteLinkSingleton::getInstance();

	// Attempt to retrieve the quote link by post UID
	$quoteLink = $quoteLinkSingleton->getQuoteLinkByPostUid($postUid);

	// If no quote link found, throw an error
	if (!$quoteLink) {
		throw new Exception(__FUNCTION__ . ": Quote link not found for post_uid: {$postUid}");
	}

	return $quoteLink;
}

/**
 * Retrieves all quote links associated with a specific board.
 *
 * This function uses the quoteLinkSingleton to fetch quote links for the board,
 * including associated host and target post data.
 *
 */
function getQuoteLinksFromBoard(IBoard $board): array {
	// Extract board UID from the IBoard object
	$boardUid = $board->getBoardUID();

	// Get the singleton instance responsible for quote link operations
	$quoteLinkSingleton = quoteLinkSingleton::getInstance();

	// Fetch all quote links for this board UID
	$quoteLinks = $quoteLinkSingleton->getQuoteLinksByBoardUid($boardUid);
	
	return $quoteLinks;
}
