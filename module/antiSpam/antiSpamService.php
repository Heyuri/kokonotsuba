<?php

namespace Kokonotsuba\Modules\antiSpam;

/** Service for managing the post spam-filter rule set. */
class antiSpamService {
	public function __construct(
		private antiSpamRepository $antiSpamRepository
	) {}

	/**
	 * Add a new spam filter rule.
	 *
	 * @param string      $pattern       Pattern to match.
	 * @param string      $matchType     Match strategy ('contains', 'exact', 'regex', etc.).
	 * @param int         $applySubject  Apply to post subject (1/0).
	 * @param int         $applyComment  Apply to post comment (1/0).
	 * @param int         $applyName     Apply to poster name (1/0).
	 * @param int         $applyEmail    Apply to poster email (1/0).
	 * @param int         $caseSensitive Case-sensitive match (1/0).
	 * @param string|null $userMessage   Message shown to the blocked user, or null.
	 * @param string|null $description   Internal description, or null.
	 * @param string      $action        Action on match ('reject', etc.).
	 * @param int|null    $maxDistance   Max edit distance for fuzzy matching, or null.
	 * @param int|null    $createdBy     Account ID of the creating staff member, or null.
	 * @return void
	 */
	public function addEntry(
		string $pattern,
		string $matchType = 'contains',
		int $applySubject = 1,
		int $applyComment = 1,
		int $applyName = 1,
		int $applyEmail = 1,
		int $applyFilename = 0,
		int $applyOpOnly = 0,
		int $silentReject = 0,
		int $caseSensitive = 0,
		?string $userMessage = null,
		?string $description = null,
		string $action = 'reject',
		?int $maxDistance = null,
		?int $createdBy = null
	): void {
		// insert row
		$this->antiSpamRepository->insertRow(
			$pattern,
			$matchType,
			$applySubject,
			$applyComment,
			$applyName,
			$applyEmail,
			$applyFilename,
			$applyOpOnly,
			$silentReject,
			$caseSensitive,
			$userMessage,
			$description,
			$action,
			$maxDistance,
			$createdBy
		);
	}
	
	/**
	 * Fetch a paginated list of spam rule entries.
	 *
	 * @param int $entriesPerPage Number of entries per page.
	 * @param int $page           One-based page index.
	 * @return array|false Array of rule rows, or false if none.
	 */
	public function getEntries(int $entriesPerPage, int $page): false|array {
		// calculate pagination value
		$offset = max(0, (max(1, $page) - 1) * $entriesPerPage);

		// return data
		return $this->antiSpamRepository->getEntries($entriesPerPage, $offset);
	}

	/**
	 * Count the total number of spam rule entries.
	 *
	 * @return int Entry count.
	 */
	public function getTotalEntries(): int {
		return $this->antiSpamRepository->getTotalEntries();
	}

	/**
	 * Delete a set of spam rule entries by their primary keys.
	 *
	 * @param array $entryIDs Array of integer primary keys.
	 * @return void
	 */
	public function deleteEntries(array $entryIDs): void {
		// delete the entries
		$this->antiSpamRepository->deleteEntries($entryIDs);
	}

	/**
	 * Fetch active spam rules applicable to the provided post fields.
	 *
	 * @param string|null $subject Post subject, or null.
	 * @param string|null $comment Post comment, or null.
	 * @param string|null $name    Poster name, or null.
	 * @param string|null $email   Poster email, or null.
	 * @return array|false Matching rule rows, or false if none.
	 */
	public function getActiveSpamStringRules(
		?string $subject,
		?string $comment,
		?string $name,
		?string $email,
		bool $hasFilenames = false,
		bool $isOp = false
	): false|array {
		return $this->antiSpamRepository->getActiveSpamStringRules($subject, $comment, $name, $email, $hasFilenames, $isOp);
	}

	/**
	 * Fetch a single spam rule entry by its primary key.
	 *
	 * @param int $id Entry primary key.
	 * @return array|false Associative row, or false if not found.
	 */
	public function getEntry(int $id): false|array {
		return $this->antiSpamRepository->getEntryById($id);
	}

	/**
	 * Update allowed fields on an existing spam rule entry.
	 *
	 * @param int   $entryId Entry primary key.
	 * @param array $fields  Map of camelCase field names to new values.
	 * @return void
	 */
	public function modifyEntry(int $entryId, array $fields): void {
		$update = [];

		// pattern
		if (array_key_exists('pattern', $fields)) {
			$update['pattern'] = (string)$fields['pattern'];
		}

		// match type
		if (array_key_exists('matchType', $fields)) {
			$update['match_type'] = (string)$fields['matchType'];
		}

		// max distance
		if (array_key_exists('maxDistance', $fields)) {
			$update['max_distance'] = $fields['maxDistance'] !== ''
				? (int)$fields['maxDistance']
				: null;
		}

		// apply fields
		if (array_key_exists('applySubject', $fields)) {
			$update['apply_subject'] = (int)$fields['applySubject'];
		}

		if (array_key_exists('applyComment', $fields)) {
			$update['apply_comment'] = (int)$fields['applyComment'];
		}

		if (array_key_exists('applyName', $fields)) {
			$update['apply_name'] = (int)$fields['applyName'];
		}

		if (array_key_exists('applyEmail', $fields)) {
			$update['apply_email'] = (int)$fields['applyEmail'];
		}

		if (array_key_exists('applyFilename', $fields)) {
			$update['apply_filename'] = (int)$fields['applyFilename'];
		}

		if (array_key_exists('applyOpOnly', $fields)) {
			$update['apply_op_only'] = (int)$fields['applyOpOnly'];
		}

		if (array_key_exists('silentReject', $fields)) {
			$update['silent_reject'] = (int)$fields['silentReject'];
		}

		// case sensitivity
		if (array_key_exists('caseSensitive', $fields)) {
			$update['case_sensitive'] = (int)$fields['caseSensitive'];
		}

		// user message
		if (array_key_exists('userMessage', $fields)) {
			$update['user_message'] = $fields['userMessage'] !== ''
				? (string)$fields['userMessage']
				: null;
		}

		// description
		if (array_key_exists('description', $fields)) {
			$update['description'] = $fields['description'] !== ''
				? (string)$fields['description']
				: null;
		}

		// action
		if (array_key_exists('action', $fields)) {
			$update['action'] = (string)$fields['action'];
		}

		// active
		if (array_key_exists('isActive', $fields)) {
			$update['is_active'] = (int)$fields['isActive'];
		}

		// nothing to update
		if (empty($update)) {
			return;
		}

		// persist changes
		$this->antiSpamRepository->updateRow($entryId, $update);
	}

}