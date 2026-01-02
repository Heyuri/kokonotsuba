<?php

namespace Kokonotsuba\Modules\antiSpam;

class antiSpamService {
	public function __construct(
		private antiSpamRepository $antiSpamRepository
	) {}

	public function addEntry(
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
	): void {
		// insert row
		$this->antiSpamRepository->insertRow(
			$pattern,
			$matchType,
			$applySubject,
			$applyComment,
			$applyName,
			$applyEmail,
			$caseSensitive,
			$userMessage,
			$description,
			$action,
			$maxDistance,
			$createdBy
		);
	}
	
	public function getEntries(int $entriesPerPage, int $page): false|array {
		// calculate pagination value
		$offset = max(0, ($page - 1) * $entriesPerPage);

		// return data
		return $this->antiSpamRepository->getEntries($entriesPerPage, $offset);
	}

	public function getTotalEntries(): int {
		return $this->antiSpamRepository->getTotalEntries();
	}

	public function deleteEntries(array $entryIDs): void {
		// delete the entries
		$this->antiSpamRepository->deleteEntries($entryIDs);
	}

	public function getActiveSpamStringRules(
		?string $subject,
		?string $comment,
		?string $name,
		?string $email
	): false|array {
		return $this->antiSpamRepository->getActiveSpamStringRules($subject, $comment, $name, $email);
	}

	public function getEntry(int $id): false|array {
		return $this->antiSpamRepository->getEntryById($id);
	}

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