<?php

class quoteLinkService {
	public function __construct(
		private readonly quoteLinkRepository $quoteLinkRepository,
		private readonly postRepository $postRepository
	) {}

	public function getQuoteLinksByPostUids(array $postUids): array {
		if (empty($postUids)) {
			return [];
		}

		$quoteLinks = $this->quoteLinkRepository->getQuoteLinksByTargetPostUids($postUids);

		if (empty($quoteLinks)) {
			return [];
		}

		$targetPostUids = [];
		$hostPostUids = [];

		foreach ($quoteLinks as $ql) {
			$targetPostUids[] = $ql->getTargetPostUid();
			$hostPostUids[] = $ql->getHostPostUid();
		}

		$allPostUids = array_unique(array_merge($targetPostUids, $hostPostUids));
		$posts = $this->quoteLinkRepository->getPostsByPostUids($allPostUids);

		$postMap = [];
		foreach ($posts as $post) {
			$postMap[$post['post_uid']] = $post;
		}

		$result = [];
		foreach ($quoteLinks as $ql) {
			$targetPost = $postMap[$ql->getTargetPostUid()] ?? null;
			$hostPost = $postMap[$ql->getHostPostUid()] ?? null;

			if ($targetPost && $hostPost) {
				$result[] = [
					[
						'target_post' => $targetPost,
						'host_post'   => $hostPost,
					],
					'quoteLink' => $ql
				];
			}
		}

		return $result;
	}

	public function getQuoteLinksByBoardUid(int $boardUid): array {
		$quoteLinks = $this->quoteLinkRepository->getQuoteLinksByBoardUid($boardUid);

		if (empty($quoteLinks)) {
			return [];
		}

		$postUids = [];
		foreach ($quoteLinks as $link) {
			$postUids[] = $link->getTargetPostUid();
			$postUids[] = $link->getHostPostUid();
		}

		$postUids = array_unique($postUids);
		$posts = $this->quoteLinkRepository->getPostsByPostUids($postUids);

		$postMap = [];
		$threadUids = [];

		foreach ($posts as $post) {
			$postMap[$post['post_uid']] = $post;
			if (!empty($post['thread_uid'])) {
				$threadUids[] = $post['thread_uid'];
			}
		}

		$threadUids = array_unique($threadUids);
		$threads = $this->quoteLinkRepository->getThreadsByUids($threadUids);

		$threadMap = [];
		foreach ($threads as $thread) {
			$threadMap[$thread['thread_uid']] = $thread;
		}

		$result = [];
		foreach ($quoteLinks as $link) {
			$hostUid = $link->getHostPostUid();
			$targetUid = $link->getTargetPostUid();

			$hostPost = $postMap[$hostUid] ?? null;
			$targetPost = $postMap[$targetUid] ?? null;

			if (!$hostPost || !$targetPost) {
				continue;
			}

			$hostThread = $threadMap[$hostPost['thread_uid']] ?? null;
			$targetThread = $threadMap[$targetPost['thread_uid']] ?? null;

			$result[$hostUid][] = [
				'target_post'   => $targetPost,
				'target_thread' => $targetThread,
				'host_post'     => $hostPost,
				'host_thread'   => $hostThread,
				'quoteLink'     => $link,
			];
		}

		return $result;
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

	public function getQuoteLinksFromPostUids(array $postUids): array {
		return $this->quoteLinkRepository->getQuoteLinksFromHostPostUids($postUids);
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
