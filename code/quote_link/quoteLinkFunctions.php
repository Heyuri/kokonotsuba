<?php
/*
* Quote link functions for Kokonotsuba!
* Business logic for managing quote links
*/


/**
 * Creates quote link entries from a given array of target post UIDs.
 *
 * This function builds and inserts quote link data indicating that a single host post
 * references multiple target posts on the same board.
 *
 * @param IBoard $board           The board object associated with the posts.
 * @param int $postUid            The UID of the post that contains the quotes (host).
 * @param array $targetPostUids   An array of UIDs for the posts being quoted (targets).
 */
function createQuoteLinksFromArray(IBoard $board, int $postUid, array $targetPostUids): void {
	$boardUid = $board->getBoardUID();

	$quoteLinksToInsert = [];
	foreach($targetPostUids as $targetPostUid) {
		$quoteLinksToInsert[] = [
			'board_uid' => $boardUid,
			'host_post_uid' => $postUid,
			'target_post_uid' => $targetPostUid,
		];
	}

	$quoteLinkSingleton = quoteLinkSingleton::getInstance();
	$quoteLinkSingleton->insertQuoteLinks($quoteLinksToInsert);
}

/**
 * Retrieves the QuoteLink associated with a given post.
 *
 * Validates the input post array and throws an exception if required fields are missing
 * or if the quote link cannot be found.
 *
 * @param array $post   The post data array containing at least a 'post_uid' key.
 * @return quoteLink    The quoteLink object corresponding to the given post.
 * @throws Exception    If the post array is empty, missing 'post_uid', or if no quote link is found.
 */
function getQuoteLinkFromPost(array $post): quoteLink {
	if (empty($post)) {
		throw new Exception(__FUNCTION__ . ": Post array is empty.");
	}

	if (!isset($post['post_uid'])) {
		throw new Exception(__FUNCTION__ . ": Missing 'post_uid' in post array.");
	}

	$postUid = $post['post_uid'];
	$quoteLinkSingleton = quoteLinkSingleton::getInstance();
	$quoteLink = $quoteLinkSingleton->getQuoteLinkByPostUid($postUid);

	if (!$quoteLink) {
		throw new Exception(__FUNCTION__ . ": Quote link not found for post_uid: {$postUid}");
	}

	return $quoteLink;
}

/**
 * Retrieves all quote links associated with a specific board.
 *
 * Uses the quoteLinkSingleton to fetch all quote links tied to the board's UID.
 *
 * @param IBoard $board   The board object to retrieve quote links for.
 * @return array          An array of quote link data associated with the board.
 */
function getQuoteLinksFromBoard(IBoard $board): array {
	$boardUid = $board->getBoardUID();
	
	$quoteLinkSingleton = quoteLinkSingleton::getInstance();
	
	return $quoteLinkSingleton->getQuoteLinksByBoardUid($boardUid);
}