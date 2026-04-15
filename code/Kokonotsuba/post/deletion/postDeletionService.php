<?php

namespace Kokonotsuba\post\deletion;

use Kokonotsuba\board\board;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\request\request;
use Kokonotsuba\thread\threadRepository;

use function Kokonotsuba\libraries\getBoardsByUIDs;

/** Handles soft-deletion of posts with thread cascade and bump restoration. */
class postDeletionService {
	use TransactionalTrait;

	public function __construct(
		private readonly postRepository $postRepository,
		private readonly transactionManager $transactionManager,
		private readonly threadRepository $threadRepository,
		private readonly deletedPostsService $deletedPostsService,
		private readonly request $request
	) {}

	/**
	 * Soft-delete one or more posts, flag their attachments for purgatory,
	 * and restore bump ordering on affected threads.
	 *
	 * When an OP is deleted, all replies in that thread are cascade-deleted too.
	 *
	 * @param array|int $posts     Post UID(s) to delete.
	 * @param int|null  $accountId Account ID performing the deletion, or 0 for anonymous.
	 */
	public function removePosts(array|int $posts, ?int $accountId = 0): void {
		if (!is_array($posts)) {
			$posts = [$posts];
		}
		if (count($posts) === 0) return;

		$posts = array_filter($posts, fn($v) => filter_var($v, FILTER_VALIDATE_INT) !== false);

		$this->inTransaction(function () use ($posts, $accountId) {
			$postsData = $this->postRepository->getPostsByUids($posts);
			if (!$postsData) return;

			// Build board map for bump restoration
			$boardMap = $this->buildBoardMap($postsData);

			// Collect per-thread deletion info (skipping already-deleted posts)
			$deletionRows = $this->buildDeletionRows($postsData, $boardMap);

			// Flag the posts as deleted
			// Thread cascade (proxy-deleting replies) is handled by flagPostsAsDeleted
			$this->deletedPostsService->flagPostsAsDeleted($postsData, $accountId);

			// Restore bump ordering on affected threads
			$this->restoreThreadBumps($deletionRows);
		});
	}

	/**
	 * Map boardUIDs from the given posts to their board objects.
	 *
	 * @return array<int, board> boardUID => board
	 */
	private function buildBoardMap(array $postsData): array {
		$boardUIDs = array_unique(array_map(fn($p) => $p->getBoardUID(), $postsData));
		$boards = getBoardsByUIDs($boardUIDs);

		$boardMap = [];
		foreach ($boards as $board) {
			$boardMap[$board->getBoardUID()] = $board;
		}
		return $boardMap;
	}

	/**
	 * Build per-thread deletion context rows, skipping already-deleted posts.
	 */
	private function buildDeletionRows(array $postsData, array $boardMap): array {
		$deletionRows = [];
		foreach ($postsData as $row) {
			if ($row->getOpenFlag() === 1) continue;

			$deletionRows[] = [
				'thread_uid' => $row->getThreadUid(),
				'board' => $boardMap[$row->getBoardUID()],
			];
		}
		return $deletionRows;
	}

	/**
	 * After deletion, restore the correct bump time for each affected thread
	 * by finding the newest non-sage reply still visible.
	 */
	private function restoreThreadBumps(array $deletionRows): void {
		// Deduplicate by thread_uid so we only process each thread once
		$seen = [];
		foreach ($deletionRows as $deletionRow) {
			$threadUID = $deletionRow['thread_uid'];
			if (isset($seen[$threadUID])) continue;
			$seen[$threadUID] = true;

			$board = $deletionRow['board'];

			$replies = $this->threadRepository->getAllPostsFromThread($threadUID, true);
			if (is_null($replies) || $replies === false) continue;

			$replies = $this->removeSagedReplies($replies);
			$newReplyData = end($replies);
			if (!$newReplyData) continue;

			$suppressBump = $this->shouldSuppressBump($threadUID, $board);

			if ($suppressBump) {
				$this->threadRepository->updateThreadReplyTime($threadUID, $newReplyData->getRoot());
			} else {
				$this->threadRepository->updateThreadBumpAndReplyTime($threadUID, $newReplyData->getRoot());
			}
		}
	}

	/**
	 * Determine whether bump restoration should be suppressed for a thread
	 * (auto-sage flag or thread age expiry).
	 */
	private function shouldSuppressBump(string $threadUID, board $board): bool {
		$opPostCheck = $this->postRepository->getOpeningPostFromThread($threadUID, true);
		$threadData = $this->threadRepository->getThreadByUid($threadUID, true);

		if (!$opPostCheck || !$threadData) return false;

		$status = $opPostCheck->getFlags();
		$threadCreatedTime = strtotime($threadData->getCreatedTime());
		$maxAgeLimit = $board->getConfigValue('MAX_AGE_TIME');
		$threadExpired = ($maxAgeLimit && ($this->request->getRequestTime() - $threadCreatedTime > ($maxAgeLimit * 60 * 60)));

		return $status->value('as') || $threadExpired;
	}

	private function removeSagedReplies(array $threadReplies): array {
		$replies = [];
		foreach ($threadReplies as $reply) {
			if (str_contains($reply->getEmail(), 'sage')) continue;
			$replies[] = $reply;
		}
		return $replies;
	}
}
