<?php

namespace Kokonotsuba\Modules\soudane;

use BoardException;

class soudaneService {
	public function __construct(
		private soudaneRepository $soudaneRepository
	) {}

	public function getYeahCounts(array $postUids): array {
		return $this->getVoteCounts($postUids, true);
	}

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

	public function addVote(int $postUid, string $ipAddress, string $type): void {
		// validate the type for repo
		$this->validateType($type);

		// whether to check for  yeah (positive) votes as opposed to negative nope (negative)
		$isYeah = $this->isYeahType($type);

		// call the repo to add a new vote to the table
		$this->soudaneRepository->insertVote($postUid, $ipAddress, $isYeah);
	}
}