<?php

namespace Kokonotsuba\thread;

use Kokonotsuba\board\board;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;

/** Service for creating and resolving thread redirect records. */
class postRedirectService {
    public function __construct(
        private readonly postRedirectRepository $postRedirectRepository,
        private readonly threadService $threadService
    ) {}

	/**
	 * Add a new thread redirect record.
	 *
	 * @param int    $original_board_uid Board UID the thread moved from.
	 * @param int    $new_board_uid      Board UID the thread moved to.
	 * @param string $thread_uid         Thread UID.
	 * @return void
	 */
	public function addNewRedirect($original_board_uid, $new_board_uid, $thread_uid) {
		$this->postRedirectRepository->addRedirect($original_board_uid, $new_board_uid, $thread_uid);
	}

	/**
	 * Fetch a redirect record by its primary key.
	 *
	 * @param int $id Primary key.
	 * @return threadRedirect|null
	 */
	public function getRedirectByID($id) {
		return $this->postRedirectRepository->getRedirectByID($id);
	}

	/**
	 * Delete a redirect record by its primary key.
	 *
	 * @param int $id Primary key.
	 * @return void
	 */
	public function deleteRedirectByID($id) {
		$this->postRedirectRepository->deleteRedirectByID($id);
	}

	/**
	 * Delete a redirect record by the thread UID it refers to.
	 *
	 * @param string $thread_uid Thread UID.
	 * @return void
	 */
	public function deleteRedirectByThreadUID($thread_uid) {
		$this->postRedirectRepository->deleteRedirectByThreadUID($thread_uid);
	}

	/**
	 * Resolve the destination URL for a thread that was moved, looked up by thread UID.
	 *
	 * @param string $threadUid UID of the moved thread.
	 * @return string Destination thread URL.
	 */
	public function resolveRedirectUrlFromThreadUID(string $threadUid) {
		// fetch redirected thread from database
		$thread = $this->threadService->getThreadData($threadUid);
		
		// get thread board
        $threadBoard = searchBoardArrayForBoard($thread['boardUID']);
		
		// get thread number
		$threadNumber = $thread['post_op_number'];

		// build thread url
        $url = $threadBoard->getBoardThreadURL($threadNumber);

		// return url
		return $url;
	}

	/**
	 * Resolve the destination URL for a thread that was moved, looked up by board and OP post number.
	 *
	 * @param board $board  Board the request was made on (original board).
	 * @param int   $resno  OP post number on the original board.
	 * @return string|null Destination URL, or null if no redirect exists.
	 */
	public function resolveRedirectUrlByPostNumber(board $board, int $resno) {
		$redirect = $this->postRedirectRepository->getRedirectByBoardAndPostOpNumber($board->getBoardUID(), $resno);
		if (!$redirect) return;

		$newBoard = searchBoardArrayForBoard($redirect->getNewBoardUID());

		$thread_uid = $redirect->getThreadUID();
		$thread = $this->threadService->getThreadData($thread_uid);

		// get thread number
		$threadNumber = $thread['post_op_number'];

		// generate url
		$newURL = $newBoard->getBoardThreadURL($threadNumber);

		// return new url
		return $newURL;
	}
}