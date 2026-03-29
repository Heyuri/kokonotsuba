<?php

namespace Kokonotsuba\Modules\soudane;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/** Repository for soudane (yeah/nope) vote records. */
class soudaneRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $soudaneTable
	) {
		parent::__construct($databaseConnection, $soudaneTable);
	}

	/**
	 * Insert a vote record for the given post and IP address.
	 *
	 * @param int    $postUid   UID of the post being voted on.
	 * @param string $ipAddress IP address of the voter.
	 * @param bool   $isYeah    True for a yeah vote, false for a nope vote.
	 * @return void
	 */
	public function insertVote(int $postUid, string $ipAddress, bool $isYeah): void {
		$this->insert([
			'post_uid' => $postUid,
			'ip_address' => $ipAddress,
			'yeah' => (int)$isYeah,
		]);
	}

	/**
	 * Fetch all vote records for a post of the given type.
	 *
	 * @param int  $postUid UID of the post.
	 * @param bool $isYeah  True to fetch yeah votes, false for nope votes.
	 * @return array|false Array of vote rows, or false if none.
	 */
	public function fetchVotes(int $postUid, bool $isYeah): false|array {
		$query = "SELECT post_uid, ip_address, yeah FROM {$this->table} WHERE post_uid = :post_uid AND yeah = :yeah";
		return $this->queryAll($query, [':post_uid' => $postUid, ':yeah' => (int)$isYeah]);
	}
	
	/**
	 * Get the vote count for each of the given post UIDs.
	 *
	 * @param array $postUids Array of post UIDs to aggregate.
	 * @param bool  $isYeah   True to count yeah votes, false for nope votes.
	 * @return array|false Rows with post_uid and vote_count columns, or false if none.
	 */
	public function getVoteCountsByPostUids(array $postUids, bool $isYeah): false|array {
		$placeholders = pdoPlaceholdersForIn($postUids);

		$query = "
			SELECT post_uid, COUNT(*) AS vote_count FROM {$this->table} 
			WHERE post_uid IN $placeholders AND yeah = ?
			GROUP BY post_uid";

		$params = $postUids;
		$params[] = (int)$isYeah;

		return $this->queryAll($query, $params);
	}
}