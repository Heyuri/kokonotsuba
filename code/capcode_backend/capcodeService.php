<?php

class capcodeService {
	public function __construct(
        private capcodeRepository $capcodeRepository,
		private transactionManager $transactionManager
    ) {}

	public function getCapcode(int $id): ?array {
		// Retrieve a single capcode record by ID
		return $this->capcodeRepository->getById($id);
	}

	public function listCapcodes(): array {
		// Get all capcode records
		return $this->capcodeRepository->getAll();
	}

	public function addCapcode(
		string $tripcode,
		bool $isSecure,
		int $addedBy,
		string $colorHex,
		string $capcodeText
	): ?int {
		// init last insert id result
		$idResult = null;

		try {
			// run query in a transaction
			$this->transactionManager->run(function () use (
					$tripcode,
					$isSecure,
					$addedBy,
					$colorHex,
					$capcodeText,
					&$idResult,
				) {
					// Insert the new record and return its ID
					$idResult = $this->capcodeRepository->create(
						$tripcode,
						$isSecure,
						$addedBy,
						$colorHex,
						$capcodeText
					);
				});
		} catch (PDOException $e) {
			// MySQL duplicate entry: SQLSTATE 23000, error code 1062
			if ($e->getCode() === '23000' && str_contains($e->getMessage(), '1062')) {
				throw new BoardException("You attempted to add a duplicate capcode!");
			}
		
			// Let the transaction manager handle rollback and rethrow other errors
			throw $e;
		}

		// return id result
		return $idResult;
	}

	public function editCapcode(int $id, array $data): void {
		// run through transaction
		$this->transactionManager->run(function () use ($id, $data) {
			// Update the specified capcode record
			$this->capcodeRepository->update($id, $data);
		});
	}

	public function removeCapcode(int $id): void {
		// run transaction
		$this->transactionManager->run(function () use ($id) {
			// Delete a capcode record
			$this->capcodeRepository->delete($id);
		});
	}

	public function getNextId(): ?int {
		// init id to be set in transaction
		$id = null;

		// run through transaction
		$this->transactionManager->run(function () use (&$id) {
			// Retrieve the next AUTO_INCREMENT value
			$id = $this->capcodeRepository->getNextAutoIncrement();
		});

		// return the id
		return $id;
	}
}
