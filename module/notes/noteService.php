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

//	public function editNote(int $noteId,)
}