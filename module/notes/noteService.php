<?php

namespace Kokonotsuba\Modules\notes;

use Kokonotsuba\database\transactionManager;

class noteService {
	public function __construct(
		private noteRepository $noteRepository,
		private transactionManager $transactionManager
	) {}

	public function addNote(
		int $postUid, 
		string $noteText, 
		int $accountId
	): void {
		// wrap note insert call in transaction just in case
		$this->transactionManager->run(function() use (
			$postUid, 
			$noteText, 
			$accountId) {
				$this->noteRepository->insertNote($postUid, $noteText, $accountId);
			});
	}

	public function editNote(int $noteId, string $newText): void {
		// wrap note update call in transaction just in case
		$this->transactionManager->run(function() use ($noteId, $newText) {
			$this->noteRepository->editNote($noteId, $newText);
		});
	}
	
	public function noteOwnedByAccount(int $accountId, int $noteId): bool {
		return $this->noteRepository->noteOwnedByAccount($accountId, $noteId);
	}

	public function deleteNote(int $noteId): void {
		// wrap note delete call in transaction just in case
		$this->transactionManager->run(function() use ($noteId) {
			$this->noteRepository->deleteNote($noteId);
		});
	}
}