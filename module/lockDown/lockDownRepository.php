<?php

namespace Kokonotsuba\Modules\lockDown;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\openDeletionExistsCondition;

/** The post-table reads that decide who may still post during a lock-down. */
final class lockDownRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $postTable,
		private readonly string $deletedPostsTable
	) {
		parent::__construct($databaseConnection, $postTable);
		self::validateTableName($deletedPostsTable);
	}

	/** Whether a live post on the board made before $before carries this visitor token hash. */
	public function tokenPostedBefore(int $boardUid, string $tokenHash, string $before): bool {
		return $this->postedBefore($boardUid, 'p.visitor_token_hash = :hash', [':hash' => $tokenHash], $before);
	}

	/** Whether a live post on the board made before $before came from one of these stored IP forms. */
	public function hostPostedBefore(int $boardUid, array $hostForms, string $before): bool {
		if ($hostForms === []) {
			return false;
		}

		$params = [];
		foreach (array_values($hostForms) as $i => $form) {
			$params[":host$i"] = $form;
		}

		return $this->postedBefore($boardUid, 'p.host IN (' . implode(', ', array_keys($params)) . ')', $params, $before);
	}

	private function postedBefore(int $boardUid, string $match, array $params, string $before): bool {
		$deleted = openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');

		return (bool) $this->queryColumn(
			"SELECT 1 FROM {$this->table} p
			 WHERE p.boardUID = :board AND $match AND p.root < :before AND NOT $deleted
			 LIMIT 1",
			$params + [':board' => $boardUid, ':before' => $before]
		);
	}
}
