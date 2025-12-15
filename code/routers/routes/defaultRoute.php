<?php

// default route - live front end / redirect to static html

class defaultRoute {
	/**
	 * Constructor to initialize required dependencies.
	 */
	public function __construct(
		private readonly array $config,
		private board $board,
		private readonly threadRepository $threadRepository,
		private readonly postRepository $postRepository,
		private readonly postRedirectService $postRedirectService
	) {}

	/**
	 * Main entry point to handle default board access.
	 * Decides whether to show a thread, a specific page, or redirect to a cached board index.
	 */
	public function handleDefault(): void {
		header('Content-Type: text/html; charset=utf-8');

		// Check for ?res= (thread view)
		$res = intval($_GET['res'] ?? 0);

		// Check for ?page= (specific page number)
		$pageParam = $_GET['page'] ?? 0;

		if ($res > 0) {
			// Handle thread view (with potential redirection)
			$this->handleThreadRedirect($res);

			// get recent replies mode from GET request.
			// thread mode is typically blank just for regular thread rendering
			$recentReplies = $_GET['recentReplies'] ?? null;

			// Render the last X amount of replies
			if($recentReplies) {
				// fetch the amount of replies to render
				$amountOfRepliesToRender = $this->board->getConfigValue('LAST_AMOUNT_OF_REPLIES', 50);

				// prevent values going higher than the config value
				$recentReplies = min($recentReplies, $amountOfRepliesToRender);

				// also prevent it from being negative
				$recentReplies = max($recentReplies, 1);

				// then draw the last X replies page
				$this->board->drawRecentReplies($res, $recentReplies);
			}
			elseif ($pageParam !== null && intval($pageParam) > -1) {
				// draw the regular thread page	
				$this->board->drawThread($res, $pageParam);
			}
		} elseif ($pageParam !== null && intval($pageParam) > -1) {
			// Handle specific board page
			$this->board->drawPage(intval($pageParam));
		} else {
			// If the static index page is missing, regenerate it
			if (!is_file($this->config['STATIC_INDEX_FILE'])) {
				$this->board->updateBoardPathCache();
				$this->board->rebuildBoard(true);
			}

			// Redirect to static index page with cache-busting timestamp
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: ' . $this->board->getBoardURL(false, true) . '?' . $_SERVER['REQUEST_TIME']);
		}
	}

	/**
	 * Handle redirect logic when accessing a thread by post number.
	 * This includes resolving moved threads or trying to find threads by child posts.
	 */
	private function handleThreadRedirect(int $resno) {
		// Check if the thread has been moved (redirect registered)
		$movedThreadRedirect = $this->postRedirectService->resolveRedirectUrlByPostNumber($this->board, $resno);
		if ($movedThreadRedirect) {
			redirect($movedThreadRedirect);
		}

		// Try to resolve the thread UID directly from the post number
		$thread_uid = $this->threadRepository->resolveThreadUidFromResno($this->board, $resno);

		// If the thread UID is not valid, try to resolve from a child post
		if (!$this->threadRepository->isThread($thread_uid)) {
			$post_uid = $this->postRepository->resolvePostUidFromPostNumber($this->board, $resno);

			// get the post
			$post = $this->postRepository->getPostByUid($post_uid);

			// throw error if the post still isn't found
			if (!$post) {
				throw new BoardException(_T('thread_not_found'));
			}

			// Fetch the thread UID from the post's data
			$newThread = $this->threadRepository->getThreadByUid($post['thread_uid']) ?? false;

			// new thread uid
			$newThreadUid = $newThread['thread_uid'];

			// If still not valid, show error
			if (!$this->threadRepository->isThread($newThreadUid)) {
				throw new BoardException(_T('thread_not_found'));
			}

			// then get replies per page config value
			$repliesPerPage = $this->board->getConfigValue('REPLIES_PER_PAGE', 200);

			// get the page of the post
			$page = floor($post['post_position'] / $repliesPerPage);

			// Otherwise, redirect to the correct thread page and scroll to post
			$resnoNew = $this->threadRepository->resolveThreadNumberFromUID($newThreadUid); 
			$redirectString = $this->board->getBoardThreadURL($resnoNew, $resno, false, $page);
			redirect($redirectString);
		}
	}
}
