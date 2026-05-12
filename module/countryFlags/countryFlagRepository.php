<?php

namespace Kokonotsuba\Modules\countryFlags;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class countryFlagRepository extends baseRepository {

	public function __construct(databaseConnection $databaseConnection, string $countryFlagTable) {
		parent::__construct($databaseConnection, $countryFlagTable);
	}

	public function insertFlag(int $postUid, string $countryCode): void {
		$query = "INSERT IGNORE INTO {$this->table} (post_uid, country) VALUES (:post_uid, :country)";
		$this->query($query, [':post_uid' => $postUid, ':country' => $countryCode]);
	}
}
