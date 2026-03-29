<?php

namespace Kokonotsuba\thread;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for thread redirect records (used after a thread is moved to another board). */
class postRedirectRepository extends baseRepository {
	public function __construct(
        databaseConnection $databaseConnection,
        string $redirectsTable,
        private readonly string $threadTable,
    ) {
		parent::__construct($databaseConnection, $redirectsTable);
	}

	/**
	 * Add a redirect record for a moved thread.
	 * Any existing redirect for the same thread_uid is replaced first.
	 *
	 * @param int    $original_board_uid Board UID the thread moved from.
	 * @param int    $new_board_uid      Board UID the thread moved to.
	 * @param string $thread_uid         Thread UID of the moved thread.
	 * @return void
	 */
	public function addRedirect(int $original_board_uid, int $new_board_uid, string $thread_uid): void {
		if(intval($original_board_uid) === intval($new_board_uid)) return;

		$this->deleteWhere('thread_uid', $thread_uid);

		$insertQuery = "INSERT INTO {$this->table} (original_board_uid, new_board_uid, thread_uid, post_op_number) 
						VALUES(:original_board_uid, :new_board_uid, :thread_uid, 
						(SELECT post_op_number FROM {$this->threadTable} WHERE thread_uid = :thread_uid))";
		$params = [
			':original_board_uid' => intval($original_board_uid),
			':new_board_uid' => intval($new_board_uid),
			':thread_uid' => strval($thread_uid),
		];

		$this->query($insertQuery, $params);
	}

	/**
	 * Fetch a redirect record by its primary key.
	 *
	 * @param int $id Primary key.
	 * @return threadRedirect|null Null if not found.
	 */
	public function getRedirectByID(int $id): ?threadRedirect {
		return $this->findBy('redirect_id', $id, 'Kokonotsuba\thread\threadRedirect');
	}

	/**
	 * Delete a redirect record by its primary key.
	 *
	 * @param int $id Primary key.
	 * @return void
	 */
	public function deleteRedirectByID(int $id): void {
		$this->deleteWhere('redirect_id', $id);
	}

	/**
	 * Delete a redirect record by the thread UID it refers to.
	 *
	 * @param string $thread_uid Thread UID.
	 * @return void
	 */
	public function deleteRedirectByThreadUID(string $thread_uid): void {
		$this->deleteWhere('thread_uid', $thread_uid);
	}

	/**
	 * Fetch a redirect by the originating board UID and OP post number.
	 *
	 * @param int $board_uid      Original board UID.
	 * @param int $post_op_number OP post number on the original board.
	 * @return threadRedirect|null Null if no matching redirect found.
	 */
	public function getRedirectByBoardAndPostOpNumber(int $board_uid, int $post_op_number): ?threadRedirect {
		$query = "SELECT * FROM {$this->table} WHERE original_board_uid = :board_uid AND post_op_number = :post_op_number";
	
		$result = $this->queryAsClass(
			$query,
			[':board_uid' => $board_uid, ':post_op_number' => $post_op_number],
			'Kokonotsuba\thread\threadRedirect'
		);

		return $result !== false ? $result : null;
	}

}
