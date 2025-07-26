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

	public function resolveRedirectedThreadLinkFromThreadUID($thread_uid) {
		$thread = $this->threadService->getThreadByUID($thread_uid)['thread'];
		
        $threadBoard = searchBoardArrayForBoard($thread['boardUID']);
		
        $url = $threadBoard->getBoardURL();

		return $url . $threadBoard->getConfigValue('LIVE_INDEX_FILE') . '?res=' . $thread['post_op_number'];
	}

	public function resolveRedirectedThreadLinkFromPostOpNumber($board, $resno) {
		$redirect = $this->postRedirectRepository->getRedirectByBoardAndPostOpNumber($board->getBoardUID(), $resno);
		if (!$redirect) return;

		$newBoard = searchBoardArrayForBoard($redirect->getNewBoardUID());
		$newURL = $newBoard->getBoardURL();

		$thread_uid = $redirect->getThreadUID();
		$thread = $this->threadService->getThreadByUID($thread_uid)['thread'];

		return $newURL . $newBoard->getConfigValue('LIVE_INDEX_FILE') . '?res=' . $thread['post_op_number'];
	}
}