<?php

namespace Kokonotsuba\Modules\soudane;

use DatabaseConnection;

class soudaneRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private string $soudaneTable
	) {}

	public function insertVote(int $postUid, string $ipAddress, bool $isYeah): void {
		// query to insert a vote into the database
		$query = "INSERT INTO {$this->soudaneTable} (post_uid, ip_address, yeah) VALUES(:post_uid, :ip_address, :yeah)";

		// build parameters
		$params = [
			':post_uid' => $postUid,
			':ip_address' => $ipAddress,
			':yeah' => (int)$isYeah,
		];

		// execute insert
		$this->databaseConnection->execute($query, $params);
	}

	public function fetchVotes(int $postUid, bool $isYeah): false|array {
		// build the query to fetch votes
		$query = "SELECT post_uid, ip_address, yeah FROM {$this->soudaneTable} WHERE post_uid = :post_uid AND yeah = :yeah";

		// build parameters
		$params = [
			':post_uid' => $postUid,
			':yeah' => (int)$isYeah
		];

		// fetch the votes
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}
	
	public function getVoteCountsByPostUids(array $postUids, bool $isYeah): false|array {
		// build pdo placeholders
		$placeholders = pdoPlaceholdersForIn($postUids);

		// build query to fetch
		$query = "
			SELECT post_uid, COUNT(*) AS vote_count FROM {$this->soudaneTable} 
			WHERE post_uid IN $placeholders AND yeah = ?
			GROUP BY post_uid";

		// set parameters to post uids
		$params = $postUids;

		// append yeah value
		$params[] = (int)$isYeah;

		// fetch values
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}
}