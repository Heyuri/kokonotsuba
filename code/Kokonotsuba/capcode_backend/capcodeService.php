<?php

namespace Kokonotsuba\capcode_backend;

use Kokonotsuba\capcode_backend\capcodeRepository;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;
use Kokonotsuba\error\BoardException;
use PDOException;

/** Service for managing capcode (trip-based role badge) records. */
class capcodeService {
	use TransactionalTrait;

	public function __construct(
        private capcodeRepository $capcodeRepository,
		private transactionManager $transactionManager
    ) {}

	/**
	 * Retrieve a capcode record by primary key.
	 *
	 * @param int $id Capcode primary key.
	 * @return array|null Associative row, or null if not found.
	 */
	public function getCapcode(int $id): ?array {
		// Retrieve a single capcode record by ID
		return $this->capcodeRepository->getById($id);
	}

	/**
	 * List all capcode records.
	 *
	 * @return array Array of associative capcode rows.
	 */
	public function listCapcodes(): array {
		// Get all capcode records
		return $this->capcodeRepository->getAll();
	}

	/**
	 * List every capcode that is currently enabled.
	 *
	 * This is the list the front end renders from: a disabled capcode leaves the tripcode alone
	 * and shows no badge, while its row stays put for the admin page.
	 *
	 * @return array Array of associative capcode rows.
	 */
	public function listEnabledCapcodes(): array {
		// Get every capcode still switched on
		return $this->capcodeRepository->getAllEnabled();
	}

	/**
	 * Create a new capcode record.
	 *
	 * @param string $tripcode     Tripcode string this capcode applies to.
	 * @param bool   $isSecure     Whether the tripcode is a secure tripcode.
	 * @param int    $addedBy      Account ID of the creating staff member.
	 * @param string $colorHex     Badge display colour in hex.
	 * @param string $capcodeText  Badge label text.
	 * @return int|null New primary key, or null on failure.
	 * @throws BoardException If a duplicate tripcode is detected.
	 */
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
			$this->inTransaction(function () use (
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

	/**
	 * Update an existing capcode record's whitelisted fields.
	 *
	 * @param int   $id   Capcode primary key.
	 * @param array $data Map of column names to new values.
	 * @return void
	 */
	public function editCapcode(int $id, array $data): void {
		// run through transaction
		$this->inTransaction(function () use ($id, $data) {
			// Update the specified capcode record
			$this->capcodeRepository->update($id, $data);
		});
	}

	/**
	 * Switch a capcode on or off without removing its record.
	 *
	 * @param int  $id        Capcode primary key.
	 * @param bool $isEnabled Whether the capcode should render.
	 * @return void
	 */
	public function setCapcodeEnabled(int $id, bool $isEnabled): void {
		// Reuse the whitelisted update path
		$this->editCapcode($id, ['is_enabled' => (int)$isEnabled]);
	}

	/**
	 * Delete a capcode record by primary key.
	 *
	 * @param int $id Capcode primary key.
	 * @return void
	 */
	public function removeCapcode(int $id): void {
		// run transaction
		$this->inTransaction(function () use ($id) {
			// Delete a capcode record
			$this->capcodeRepository->delete($id);
		});
	}

	/**
	 * Retrieve the next AUTO_INCREMENT value for the capcode table.
	 *
	 * @return int|null Next auto-increment ID, or null if unavailable.
	 */
	public function getNextId(): ?int {
		// init id to be set in transaction
		$id = null;

		// run through transaction
		$this->inTransaction(function () use (&$id) {
			// Retrieve the next AUTO_INCREMENT value
			$id = $this->capcodeRepository->getNextAutoIncrement();
		});

		// return the id
		return $id;
	}
}
