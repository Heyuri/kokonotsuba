<?php

namespace Kokonotsuba\Modules\antiSpam;

use DatabaseConnection;

class antiSpamRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private string $spamStringRulesTable,
		private string $accountTable
	) {}

	public function getActiveSpamStringRules(
		?string $subject,
		?string $comment,
		?string $name,
		?string $email
	): false|array {
		// query to check for spam rules
		$query = "
			SELECT *
				FROM {$this->spamStringRulesTable}
			WHERE is_active = 1
			  AND (
				(apply_subject = 1 AND :subject IS NOT NULL) OR
				(apply_comment = 1 AND :comment IS NOT NULL) OR
				(apply_name = 1 AND :name IS NOT NULL) OR
				(apply_email = 1 AND :email IS NOT NULL)
			  )
		";

		// define the query parameters
		$params = [
			':subject' => $subject,
			':comment' => $comment,
			':name' => $name,
			':email' => $email,
		];

		// fetch the spam rules
		$spamRows = $this->databaseConnection->fetchAllAsArray($query, $params);

		// return results
		return $spamRows;
	}

	public function insertRow(
		string $pattern,
		string $matchType = 'contains',
		int $applySubject = 1,
		int $applyComment = 1,
		int $applyName = 1,
		int $applyEmail = 1,
		int $caseSensitive = 0,
		?string $userMessage = null,
		?string $description = null,
		string $action = 'reject',
		?int $maxDistance = null,
		?int $createdBy = null
	): bool {
		// insert spam rule query
		$query = "
			INSERT INTO {$this->spamStringRulesTable} (
				pattern,
				match_type,
				apply_subject,
				apply_comment,
				apply_name,
				apply_email,
				case_sensitive,
				user_message,
				description,
				action,
				max_distance,
				created_by
			) VALUES (
				:pattern,
				:match_type,
				:apply_subject,
				:apply_comment,
				:apply_name,
				:apply_email,
				:case_sensitive,
				:user_message,
				:description,
				:action,
				:max_distance,
				:created_by
			)
		";

		// define the query parameters
		$params = [
			':pattern' => $pattern,
			':match_type' => $matchType,
			':apply_subject' => $applySubject,
			':apply_comment' => $applyComment,
			':apply_name' => $applyName,
			':apply_email' => $applyEmail,
			':case_sensitive' => $caseSensitive,
			':user_message' => $userMessage,
			':description' => $description,
			':action' => $action,
			':max_distance' => $maxDistance,
			':created_by' => $createdBy,
		];

		// execute insert
		return $this->databaseConnection->execute($query, $params);
	}

	public function getEntries(int $limit, int $offset): false|array {
		// fetch spam string rule entries
		$query = "
			SELECT s.*, a.username AS created_by_username
				FROM {$this->spamStringRulesTable} s
			LEFT JOIN {$this->accountTable} a ON a.id = s.created_by
			ORDER BY s.id DESC
			LIMIT {$limit} OFFSET {$offset}
		";

		// fetch entries
		$rows = $this->databaseConnection->fetchAllAsArray($query);

		// return results
		return $rows;
	}

	public function getTotalEntries(): int {
		// total entries count query
		$query = "
			SELECT COUNT(*)
				FROM {$this->spamStringRulesTable}
		";

		// fetch total count
		$row = $this->databaseConnection->fetchValue($query);

		// return total
		return (int) ($row ?? 0);
	}

	public function deleteEntries(array $entryIDs): void {
		// generate placeholders
		$placeholders = pdoPlaceholdersForIn($entryIDs);

		// delete query
		$query = "
			DELETE FROM {$this->spamStringRulesTable}
			WHERE id IN $placeholders
		";

		// set parameters
		$params = $entryIDs;

		// execute delete
		$this->databaseConnection->execute($query, $params);
	}

	public function getEntryById(int $id): false|array {
		// form query
		$query = "SELECT s.*, a.username AS created_by_username
					FROM {$this->spamStringRulesTable} s
					LEFT JOIN {$this->accountTable} a ON a.id = s.created_by
					WHERE s.id = :id";

		// define params
		$params = [
			':id' => $id
		];

		// fetch entry
		$entry = $this->databaseConnection->fetchOne($query, $params);

		// return result
		return $entry;
	}

	public function updateRow(int $entryId, array $fields): bool {
		// nothing to update
		if (empty($fields)) {
			return false;
		}

		// build SET clauses and params
		$set = [];
		$params = [];

		foreach ($fields as $column => $value) {
			$placeholder = ':' . $column;
			$set[] = "{$column} = {$placeholder}";
			$params[$placeholder] = $value;
		}

		// add entry id parameter
		$params[':id'] = $entryId;

		// build update query
		$query = "
			UPDATE {$this->spamStringRulesTable}
			SET " . implode(', ', $set) . "
			WHERE id = :id
		";

		// execute update
		return $this->databaseConnection->execute($query, $params);
	}

}