<?php

namespace Kokonotsuba\quote_link;

use Exception;
use Kokonotsuba\post\postRepository;

/** Service for creating, moving, copying, and querying post quote-link records. */
class quoteLinkService {
	public function __construct(
		private readonly quoteLinkRepository $quoteLinkRepository,
		private readonly postRepository $postRepository
	) {}

	/**
	 * Fetch quote-links for the given post UIDs.
	 *
	 * @param array $postUids                     Array of post UIDs (host or target).
	 * @param bool  $includeDeletedPostQuotelinks Whether to include links from deleted posts.
	 * @return array Quote-link results indexed by host_post_uid.
	 */
	public function getQuoteLinksByPostUids(array $postUids, bool $includeDeletedPostQuotelinks = false): array {
		$quoteLinks = $this->quoteLinkRepository->getQuoteLinksByPostUids($postUids, $includeDeletedPostQuotelinks);
	
		return $quoteLinks;
	}

	/**
	 * Fetch quote-links belonging to the given board UID.
	 *
	 * @param int  $boardUid                     Board UID.
	 * @param bool $includeDeletedPostQuotelinks Whether to include links from deleted posts.
	 * @return array Quote-link results.
	 */
	public function getQuoteLinksByBoardUid(int $boardUid, bool $includeDeletedPostQuotelinks = false): array {
		$quoteLinks = $this->quoteLinkRepository->getQuoteLinksByBoardUid($boardUid, $includeDeletedPostQuotelinks);

		return $quoteLinks;
	}

	/**
	 * Batch-insert quote-link records.
	 *
	 * @param array $quoteLinks Array of link data (board_uid, host_post_uid, target_post_uid).
	 * @return int Number of rows inserted.
	 */
	public function insertQuoteLinks(array $quoteLinks): int {
		return $this->quoteLinkRepository->insertQuoteLinks($quoteLinks);
	}

	/**
	 * Update the board UID of the given quoteLink objects to the destination board.
	 *
	 * @param quoteLink[] $quoteLinks Array of quoteLink objects to move.
	 * @param int         $boardUid   Destination board UID.
	 * @return void
	 */
	public function moveQuoteLinksToBoard(array $quoteLinks, int $boardUid): void {
		$ids = [];

		foreach ($quoteLinks as $ql) {
			if ($ql instanceof quoteLink) {
				$ids[] = $ql->getQuoteLinkId();
			}
		}

		$this->quoteLinkRepository->updateQuoteLinkBoardUids($ids, $boardUid);
	}

	/**
	 * Duplicate the given quoteLink objects under a different board UID.
	 *
	 * @param quoteLink[] $quoteLinks Array of quoteLink objects to copy.
	 * @param int         $boardUid   Destination board UID for the copies.
	 * @return void
	 */
	public function copyQuoteLinksToBoard(array $quoteLinks, int $boardUid): void {
		$newLinks = [];

		foreach ($quoteLinks as $ql) {
			if (!$ql instanceof quoteLink) {
				continue;
			}

			$newLinks[] = [
				'board_uid'       => $boardUid,
				'host_post_uid'   => $ql->getHostPostUid(),
				'target_post_uid' => $ql->getTargetPostUid(),
			];
		}

		$this->quoteLinkRepository->insertQuoteLinks($newLinks);
	}

	/**
	 * Create quote-link records for a post replying to multiple target posts.
	 *
	 * @param int   $boardUid       Board UID.
	 * @param int   $postUid        UID of the post that contains the quote links (host).
	 * @param array $targetPostUids Array of post UIDs being quoted.
	 * @return void
	 */
	public function createQuoteLinksFromArray(int $boardUid, int $postUid, array $targetPostUids): void {
		$quoteLinksToInsert = [];
		foreach ($targetPostUids as $targetPostUid) {
			$quoteLinksToInsert[] = [
				'board_uid' => $boardUid,
				'host_post_uid' => $postUid,
				'target_post_uid' => $targetPostUid,
			];
		}

		$this->quoteLinkRepository->insertQuoteLinks($quoteLinksToInsert);
	}

	/**
	 * Move all quote-links belonging to posts in a thread to a different board.
	 *
	 * @param string $threadUid Thread UID whose posts' quote-links will be moved.
	 * @param int    $boardUid  Destination board UID.
	 * @return void
	 */
	public function moveQuoteLinksFromThread(string $threadUid, int $boardUid): void {
		$postUids = $this->postRepository->getPostUidsFromThread($threadUid);
		$threadQuoteLinks = $this->quoteLinkRepository->getQuoteLinksFromHostPostUids($postUids);
		$this->moveQuoteLinksToBoard($threadQuoteLinks, $boardUid);
	}

	/**
	 * Copy all quote-links from a thread's posts to a new board, remapping post UIDs.
	 *
	 * @param string $threadUid      Original thread UID.
	 * @param int    $boardUid       Destination board UID.
	 * @param array  $postUidMapping Map of old post UID => new post UID.
	 * @return void
	 */
	public function copyQuoteLinksFromThread(string $threadUid, int $boardUid, array $postUidMapping): void {
		$postUids = $this->postRepository->getPostUidsFromThread($threadUid);
		$quoteLinks = $this->quoteLinkRepository->getQuoteLinksFromHostPostUids($postUids);

		$newLinks = [];

		foreach ($quoteLinks as $ql) {
			$oldHostUid = $ql->getHostPostUid();
			$oldTargetUid = $ql->getTargetPostUid();

			if (isset($postUidMapping[$oldHostUid]) && isset($postUidMapping[$oldTargetUid])) {
				$newLinks[] = [
					'board_uid'       => $boardUid,
					'host_post_uid'   => $postUidMapping[$oldHostUid],
					'target_post_uid' => $postUidMapping[$oldTargetUid],
				];
			}
		}

		$this->quoteLinkRepository->insertQuoteLinks($newLinks);
	}

	/**
	 * Convenience wrapper: fetch all quote-links for the given board UID.
	 *
	 * @param int $boardUid Board UID.
	 * @return array Quote-link results.
	 */
	public function getQuoteLinksFromBoard(int $boardUid): array {
		return $this->getQuoteLinksByBoardUid($boardUid);
	}

}
