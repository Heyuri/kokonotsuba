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
 * Moves all quote links associated with a thread to the specified board.
 *
 * @param string $threadUid The unique identifier of the thread.
 * @param IBoard $board The target board object to which quote links should be moved.
 */
function moveQuoteLinksFromThread(string $threadUid, IBoard $board): void {
	// Get the singleton instance responsible for managing quote links
	$quoteLinkSingleton = quoteLinkSingleton::getInstance();

	// Retrieve the unique identifier of the board
	$boardUid = $board->getBoardUID();

	// Fetch the list of post UIDs that belong to the given thread
	$postUids = getPostUidsFromThread($threadUid);

	// Get all quote links associated with the thread's posts
	$threadQuoteLinks = $quoteLinkSingleton->getQuoteLinksFromPostUids($postUids);

	// Move the retrieved quote links to the specified board
	$quoteLinkSingleton->moveQuoteLinksToBoard($threadQuoteLinks, $boardUid);
}

/**
 * Copies all quote links associated with a thread to the specified board,
 * remapping host and target post UIDs using the provided mapping.
 *
 * Only quote links where both the host and target post UIDs exist in the mapping
 * will be copied to ensure foreign key integrity.
 *
 * @param string $threadUid        The unique identifier of the source thread.
 * @param IBoard $board            The target board object to which quote links should be copied.
 * @param array  $postUidMapping   An associative array mapping original post UIDs to new post UIDs.
 */
function copyQuoteLinksFromThread(string $threadUid, IBoard $board, array $postUidMapping): void {
	$quoteLinkSingleton = quoteLinkSingleton::getInstance();
	$boardUid = $board->getBoardUID();

	// Step 1: Get the list of original post UIDs from the source thread
	$postUids = getPostUidsFromThread($threadUid);

	// Step 2: Get all quote links where the host post is in the thread
	$quoteLinks = $quoteLinkSingleton->getQuoteLinksFromPostUids($postUids);

	$newLinks = [];

	foreach ($quoteLinks as $ql) {
		$oldHostUid = $ql->getHostPostUid();
		$oldTargetUid = $ql->getTargetPostUid();

		// Ensure both old UIDs were mapped to new UIDs
		if (isset($postUidMapping[$oldHostUid]) && isset($postUidMapping[$oldTargetUid])) {
			$newLinks[] = [
				'board_uid'       => $boardUid,
				'host_post_uid'   => $postUidMapping[$oldHostUid],
				'target_post_uid' => $postUidMapping[$oldTargetUid],
			];
		}
	}

	// Step 3: Insert the copied quote links with the new post UIDs
	$quoteLinkSingleton->insertQuoteLinks($newLinks);
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