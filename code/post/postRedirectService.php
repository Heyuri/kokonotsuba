<?php

class postRedirectService {
    public function __construct(
        private readonly postRedirectRepository $postRedirectRepository,
        private readonly threadService $threadService
    ) {}

	public function addNewRedirect($original_board_uid, $new_board_uid, $thread_uid) {
		$this->postRedirectRepository->addRedirect($original_board_uid, $new_board_uid, $thread_uid);
	}

	public function getRedirectByID($id) {
		return $this->postRedirectRepository->getRedirectByID($id);
	}

	public function deleteRedirectByID($id) {
		$this->postRedirectRepository->deleteRedirectByID($id);
	}

	public function deleteRedirectByThreadUID($thread_uid) {
		$this->postRedirectRepository->deleteRedirectByThreadUID($thread_uid);
	}

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