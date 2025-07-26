<?php

class postRedirectRepository {
	public function __construct(
        private DatabaseConnection $databaseConnection,
        private readonly string $redirectsTable,
        private readonly string $threadTable,
    ) {}

	public function addRedirect(int $original_board_uid, int $new_board_uid, string $thread_uid): void {
		if(intval($original_board_uid) === intval($new_board_uid)) return;

		$deleteQuery = "DELETE FROM {$this->redirectsTable} WHERE thread_uid = :thread_uid";
		$this->databaseConnection->execute($deleteQuery, [':thread_uid' => $thread_uid]);

		$insertQuery = "INSERT INTO {$this->redirectsTable} (original_board_uid, new_board_uid, thread_uid, post_op_number) 
						VALUES(:original_board_uid, :new_board_uid, :thread_uid, 
						(SELECT post_op_number FROM {$this->threadTable} WHERE thread_uid = :thread_uid))";
		$params = [
			':original_board_uid' => intval($original_board_uid),
			':new_board_uid' => intval($new_board_uid),
			':thread_uid' => strval($thread_uid),
		];

		$this->databaseConnection->execute($insertQuery, $params);
	}

	public function getRedirectByID(int $id): ?threadRedirect{
		$query = "SELECT * FROM {$this->redirectsTable} WHERE redirect_id = :redirect_id";
		return $this->databaseConnection->fetchAsClass($query, [':redirect_id' => $id], 'threadRedirect');
	}

	public function deleteRedirectByID(int $id): void {
		$query = "DELETE FROM {$this->redirectsTable} WHERE redirect_id = :redirect_id";
		$this->databaseConnection->execute($query, [':redirect_id' => $id]);
	}

	public function deleteRedirectByThreadUID(string $thread_uid): void {
		$query = "DELETE FROM {$this->redirectsTable} WHERE thread_uid = :thread_uid";
		$this->databaseConnection->execute($query, [':thread_uid' => $thread_uid]);
	}

	public function getRedirectByBoardAndPostOpNumber(int $board_uid, int $post_op_number): ?threadRedirect {
		$query = "SELECT * FROM {$this->redirectsTable} WHERE original_board_uid = :board_uid AND post_op_number = :post_op_number";
	
		$result = $this->databaseConnection->fetchAsClass(
			$query,
			[':board_uid' => $board_uid, ':post_op_number' => $post_op_number],
			'threadRedirect'
		);

		return $result !== false ? $result : null;
	}

}
