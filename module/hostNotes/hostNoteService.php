<?php

namespace Kokonotsuba\Modules\hostNotes;

use Kokonotsuba\ban\ipPatternMatcher;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\ip\ipAnonymizer;

/** Service for creating, editing, deleting and looking up staff notes on hosts. */
class hostNoteService {
	/** Notes keyed by the address they were resolved for, so a page of posts queries once per host. */
	private array $notesByAddress = [];

	/** Every wildcard note, loaded at most once per request. */
	private ?array $wildcardNotes = null;

	/** Notes keyed by the browser token hash they were resolved for. */
	private array $notesByToken = [];

	public function __construct(
		private hostNoteRepository $hostNoteRepository,
		private transactionManager $transactionManager
	) {}

	/**
	 * Add a note against a host pattern and return the new note's ID.
	 *
	 * @param string $ipPattern Exact address or wildcard range.
	 * @param string $noteText  Note content.
	 * @param int    $accountId Staff account filing it.
	 * @return int|null|false The new note ID, or null/false on failure.
	 */
	public function addNote(string $ipPattern, string $noteText, int $accountId): false|null|int {
		$noteId = null;

		$this->transactionManager->run(function() use ($ipPattern, $noteText, $accountId, &$noteId) {
			$this->hostNoteRepository->insertNote($ipPattern, $noteText, $accountId);
			$noteId = $this->hostNoteRepository->getLastInsertId();
		});

		$this->forgetCaches();

		return $noteId;
	}

	/**
	 * Update the text of an existing note.
	 *
	 * @param int    $noteId  Note primary key.
	 * @param string $newText Replacement text.
	 * @return void
	 */
	public function editNote(int $noteId, string $newText): void {
		$this->transactionManager->run(function() use ($noteId, $newText) {
			$this->hostNoteRepository->editNote($noteId, $newText);
		});

		$this->forgetCaches();
	}

	/**
	 * Delete a note by its primary key.
	 *
	 * @param int $noteId Note primary key.
	 * @return void
	 */
	public function deleteNote(int $noteId): void {
		$this->transactionManager->run(function() use ($noteId) {
			$this->hostNoteRepository->deleteNote($noteId);
		});

		$this->forgetCaches();
	}

	/**
	 * Every note that applies to an address: the ones filed on it exactly, plus any range
	 * covering it.
	 *
	 * @param string $ip Address to resolve notes for.
	 * @return array[] Note rows, oldest first.
	 */
	public function getNotesForAddress(string $ip): array {
		if ($ip === '') {
			return [];
		}

		if (isset($this->notesByAddress[$ip])) {
			return $this->notesByAddress[$ip];
		}

		$notes = $this->hostNoteRepository->getNotesForPattern($ip);

		// An anonymized host is a hash rather than an address, so no range can cover it.
		if (!ipAnonymizer::isAnonymized($ip)) {
			foreach ($this->getWildcardNotes() as $note) {
				if (ipPatternMatcher::matches($ip, (string) $note['ip_pattern'])) {
					$notes[] = $note;
				}
			}
		}

		return $this->notesByAddress[$ip] = $this->sortByAge($notes);
	}

	/**
	 * Notes shown beside a pattern on the manage-posts panel: the ones filed on the pattern
	 * itself, plus - when it is a single address - the ranges covering it.
	 *
	 * @param string $ipPattern Pattern the page is filtered on.
	 * @return array[] Note rows, oldest first.
	 */
	/**
	 * Load the notes for many addresses in one query, so a page of posts costs one lookup.
	 *
	 * @param string[] $ips
	 */
	public function warmAddresses(array $ips): void {
		$wanted = [];
		foreach ($ips as $ip) {
			if ($ip !== '' && !isset($this->notesByAddress[$ip])) {
				$wanted[$ip] = true;
			}
		}
		if ($wanted === []) {
			return;
		}

		$byPattern = [];
		foreach ($this->hostNoteRepository->getNotesForPatterns(array_keys($wanted)) as $note) {
			$byPattern[(string) $note['ip_pattern']][] = $note;
		}

		$anonymizer = ipAnonymizer::fromSettings();
		foreach (array_keys($wanted) as $ip) {
			$ip = (string) $ip;
			$notes = [];
			foreach ($anonymizer->storedForms($ip) as $form) {
				foreach ($byPattern[$form] ?? [] as $note) {
					$notes[] = $note;
				}
			}
			if (!ipAnonymizer::isAnonymized($ip)) {
				foreach ($this->getWildcardNotes() as $note) {
					if (ipPatternMatcher::matches($ip, (string) $note['ip_pattern'])) {
						$notes[] = $note;
					}
				}
			}
			$this->notesByAddress[$ip] = $this->sortByAge($notes);
		}
	}

	public function getNotesForPattern(string $ipPattern): array {
		if ($ipPattern === '') {
			return [];
		}

		if (ipPatternMatcher::isWildcard($ipPattern)) {
			return $this->hostNoteRepository->getNotesForPattern($ipPattern);
		}

		return $this->getNotesForAddress($ipPattern);
	}

	/**
	 * Check whether the given note belongs to the given account.
	 *
	 * @param int $accountId Account ID.
	 * @param int $noteId    Note ID to check.
	 * @return bool True if the account owns the note.
	 */
	public function noteOwnedByAccount(int $accountId, int $noteId): bool {
		return $this->hostNoteRepository->noteOwnedByAccount($accountId, $noteId);
	}

	/**
	 * Fetch a note by its primary key.
	 *
	 * @param int $noteId Note primary key.
	 * @return array|false Associative row array, or false if not found.
	 */
	public function getNoteById(int $noteId): false|array {
		return $this->hostNoteRepository->getNoteById($noteId);
	}

	/**
	 * Add a note against a browser token hash and return the new note's ID.
	 *
	 * @param string $tokenHash Token hash as stored on the post.
	 * @param string $noteText  Note content.
	 * @param int    $accountId Staff account filing it.
	 * @return int|null|false The new note ID, or null/false on failure.
	 */
	public function addTokenNote(string $tokenHash, string $noteText, int $accountId): false|null|int {
		$noteId = null;

		$this->transactionManager->run(function() use ($tokenHash, $noteText, $accountId, &$noteId) {
			$this->hostNoteRepository->insertTokenNote($tokenHash, $noteText, $accountId);
			$noteId = $this->hostNoteRepository->getLastInsertId();
		});

		$this->forgetCaches();

		return $noteId;
	}

	/**
	 * Every note filed against a browser token hash.
	 *
	 * No range can cover a token hash, so unlike an address this is the whole answer.
	 *
	 * @param string $tokenHash Token hash to resolve notes for.
	 * @return array[] Note rows, oldest first.
	 */
	public function getNotesForVisitorToken(string $tokenHash): array {
		if ($tokenHash === '') {
			return [];
		}

		return $this->notesByToken[$tokenHash] ??= $this->hostNoteRepository->getNotesForVisitorToken($tokenHash);
	}

	/** Drop every per-request cache, so a write is visible to the rest of the request. */
	private function forgetCaches(): void {
		$this->notesByAddress = [];
		$this->notesByToken = [];
		$this->wildcardNotes = null;
	}

	/** @return array[] */
	private function getWildcardNotes(): array {
		return $this->wildcardNotes ??= $this->hostNoteRepository->getWildcardNotes();
	}

	/**
	 * @param array[] $notes
	 * @return array[]
	 */
	private function sortByAge(array $notes): array {
		usort($notes, fn(array $a, array $b): int => [$a['note_submitted'], $a['id']] <=> [$b['note_submitted'], $b['id']]);
		return $notes;
	}
}
