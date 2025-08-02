<?php

class quoteLinkService {
	public function __construct(
		private readonly quoteLinkRepository $quoteLinkRepository,
		private readonly postRepository $postRepository
	) {}

	public function getQuoteLinksByPostUids(array $postUids): array {
		$quoteLinks = $this->quoteLinkRepository->getQuoteLinksByPostUids($postUids);
	
		return $quoteLinks;
	}

	public function getQuoteLinksByBoardUid(int $boardUid): array {
		$quoteLinks = $this->quoteLinkRepository->getQuoteLinksByBoardUid($boardUid);

		return $quoteLinks;
	}

	public function insertQuoteLinks(array $quoteLinks): int {
		return $this->quoteLinkRepository->insertQuoteLinks($quoteLinks);
	}

	public function moveQuoteLinksToBoard(array $quoteLinks, int $boardUid): void {
		$ids = [];

		foreach ($quoteLinks as $ql) {
			if ($ql instanceof quoteLink) {
				$ids[] = $ql->getQuoteLinkId();
			}
		}

		$this->quoteLinkRepository->updateQuoteLinkBoardUids($ids, $boardUid);
	}

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

	public function moveQuoteLinksFromThread(string $threadUid, int $boardUid): void {
		$postUids = $this->postRepository->getPostUidsFromThread($threadUid);
		$threadQuoteLinks = $this->quoteLinkRepository->getQuoteLinksFromHostPostUids($postUids);
		$this->moveQuoteLinksToBoard($threadQuoteLinks, $boardUid);
	}

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

	public function getQuoteLinkFromPost(array $post): quoteLink {
		if (empty($post)) {
			throw new Exception(__FUNCTION__ . ": Post array is empty.");
		}

		if (!isset($post['post_uid'])) {
			throw new Exception(__FUNCTION__ . ": Missing 'post_uid' in post array.");
		}

		$postUid = $post['post_uid'];
		$links = $this->quoteLinkRepository->getQuoteLinksFromHostPostUids([$postUid]);

		if (empty($links)) {
			throw new Exception(__FUNCTION__ . ": Quote link not found for post_uid: {$postUid}");
		}

		return $links[0]; // If multiple links exist, returns the first one.
	}

	public function getQuoteLinksFromBoard(int $boardUid): array {
		return $this->getQuoteLinksByBoardUid($boardUid);
	}

}
