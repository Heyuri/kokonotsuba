<?php

namespace Kokonotsuba\Modules\displayIp;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class displayIpRepository extends baseRepository {
    public function __construct(databaseConnection $databaseConnection, string $displayIpTable) {
        parent::__construct($databaseConnection, $displayIpTable);
    }

    public function insertIpPart(int $postUid, string $ipPart): void {
        $query = "INSERT IGNORE INTO {$this->table} (post_uid, ip_part) VALUES (:post_uid, :ip_part)";
        $this->query($query, [':post_uid' => $postUid, ':ip_part' => $ipPart]);
    }
}
