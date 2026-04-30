<?php

namespace Kokonotsuba\Modules\soudane;

use Kokonotsuba\error\BoardException;

use function Kokonotsuba\libraries\_T;

/** Service for managing soudane (yeah/nope) voting on posts. */
class soudaneService {
	public function __construct(
		private soudaneRepository $soudaneRepository
	) {}

	/**
	 * Get yeah vote counts indexed by post UID.
	 *
	 * @param array $postUids Array of post UIDs to look up.
	 * @return array Map of post_uid => yeah count.
	 */
	public function getYeahCounts(array $postUids): array {
		return $this->getVoteCounts($postUids, true);
	}

	/**
	 * Get nope vote counts indexed by post UID.
	 *
	 * @param array $postUids Array of post UIDs to look up.
	 * @return array Map of post_uid => nope count.
	 */
	public function getNopeCounts(array $postUids): array {
		return $this->getVoteCounts($postUids, false);
	}

	private function getVoteCounts(array $postUids, bool $isYeah): array {
		// fetch raw counts from repository
		$rows = $this->soudaneRepository->getVoteCountsByPostUids(
			$postUids,
			$isYeah
		);

		// normalize into [post_uid => vote_count]
		$counts = [];
		foreach ($rows as $row) {
			$counts[(int) $row['post_uid']] = (int) $row['vote_count'];
		}

		return $counts;
	}

	/**
	 * Validates that the supplied vote type is allowed.
	 *
	 * @param string $type The vote type to validate.
	 *
	 * @throws BoardException If the vote type is not recognized or allowed.
	 *
	 * @return void
	 */
	function validateType(string $type): void {
		// list of allowed types
		$soudaneVoteTypes = ['yeah', 'nope'];

		// if the supplied type parameter is not included in the array then its invalid
		if (in_array($type, $soudaneVoteTypes) === false) {
			// throw user-facing error
			throw new BoardException(_T('soudane_invalid_type'));
		}
	}

	private function isYeahType(string $type): bool {
		return $type === 'yeah' ? true : false;
	}

	/**
	 * Fetch all votes of the given type for a specific post.
	 *
	 * @param int    $postUid UID of the post.
	 * @param string $type    Vote type: 'yeah' or 'nope'.
	 * @return array|false Array of vote rows, or false if none.
	 */
	public function getVotes(int $postUid, string $type): false|array {
		// validate the type
		$this->validateType($type);
	
		// fetch yeah votes or not
		$isYeah = $this->isYeahType($type);

		// fetch the votes
		$votes = $this->soudaneRepository->fetchVotes($postUid, $isYeah);

		// return them
		return $votes;
	}

	/**
	 * Record a vote on a post.
	 *
	 * @param int    $postUid   UID of the post to vote on.
	 * @param string $ipAddress IP address of the voter.
	 * @param string $type      Vote type: 'yeah' or 'nope'.
	 * @return void
	 */
	public function addVote(int $postUid, string $ipAddress, string $type): void {
		// validate the type for repo
		$this->validateType($type);

		// whether to check for  yeah (positive) votes as opposed to negative nope (negative)
		$isYeah = $this->isYeahType($type);

		// call the repo to add a new vote to the table
		$this->soudaneRepository->insertVote($postUid, $ipAddress, $isYeah);
	}

	/**
	 * Remove a vote from a post.
	 *
	 * @param int    $postUid   UID of the post to remove the vote from.
	 * @param string $ipAddress IP address of the voter.
	 * @param string $type      Vote type: 'yeah' or 'nope'.
	 * @return void
	 */
	public function removeVote(int $postUid, string $ipAddress, string $type): void {
		$this->validateType($type);
		$isYeah = $this->isYeahType($type);
		$this->soudaneRepository->deleteVote($postUid, $ipAddress, $isYeah);
	}

	/**
	 * Get paginated votes for a post.
	 *
	 * @param int $postUid UID of the post.
	 * @param int $limit   Entries per page.
	 * @param int $page    Page number (1-based).
	 * @return array Array of vote rows.
	 */
	public function getVotesPaginated(int $postUid, int $limit, int $page): array {
		$offset = ($page - 1) * $limit;
		return $this->soudaneRepository->fetchVotesPaginated($postUid, $limit, $offset);
	}

	/**
	 * Get total vote count for a post.
	 *
	 * @param int $postUid UID of the post.
	 * @return int Total votes.
	 */
	public function getTotalVotesForPost(int $postUid): int {
		return $this->soudaneRepository->countVotesForPost($postUid);
	}

	/**
	 * Delete votes by their IDs.
	 *
	 * @param array $ids Array of vote IDs.
	 * @return void
	 */
	public function deleteVotesByIds(array $ids): void {
		$this->soudaneRepository->deleteByIds(array_map('intval', $ids));
	}
}