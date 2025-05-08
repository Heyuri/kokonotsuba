<?php

// default route - live front end / redirect to static html

class defaultRoute {
	private readonly array $config;
	private readonly board $board;
	private readonly mixed $threadSingleton;
	private readonly mixed $PIO;
	private readonly globalHTML $globalHTML;

	/**
	 * Constructor to initialize required dependencies.
	 */
	public function __construct(
		array $config,
		board $board,
		mixed $threadSingleton,
		mixed $PIO,
		globalHTML $globalHTML
	) {
		$this->config = $config;
		$this->board = $board;
		$this->threadSingleton = $threadSingleton;
		$this->PIO = $PIO;
		$this->globalHTML = $globalHTML;
	}

	/**
	 * Main entry point to handle default board access.
	 * Decides whether to show a thread, a specific page, or redirect to a cached board index.
	 */
	public function handleDefault(): void {
		header('Content-Type: text/html; charset=utf-8');

		// Check for ?res= (thread view)
		$res = intval($_GET['res'] ?? 0);

		// Check for ?page= (specific page number)
		$pageParam = $_GET['page'] ?? null;

		if ($res > 0) {
			// Handle thread view (with potential redirection)
			$this->handleThreadRedirect($res);
			$this->board->drawThread($res);
		} elseif ($pageParam !== null && intval($pageParam) > -1) {
			// Handle specific board page
			$this->board->drawPage(intval($pageParam));
		} else {
			// If the static index page is missing, regenerate it
			if (!is_file($this->config['PHP_SELF2'])) {
				$this->board->updateBoardPathCache();
				$this->board->rebuildBoard(true);
			}

			// Redirect to static index page with cache-busting timestamp
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: ' . $this->globalHTML->fullURL() . $this->config['PHP_SELF2'] . '?' . $_SERVER['REQUEST_TIME']);
		}
	}

	/**
	 * Handle redirect logic when accessing a thread by post number.
	 * This includes resolving moved threads or trying to find threads by child posts.
	 */
	private function handleThreadRedirect(int $resno) {
		$postRedirectIO = postRedirectIO::getInstance();

		// Check if the thread has been moved (redirect registered)
		$movedThreadRedirect = $postRedirectIO->resolveRedirectedThreadLinkFromPostOpNumber($this->board, $resno);
		if ($movedThreadRedirect) {
			redirect($movedThreadRedirect);
		}

		// Try to resolve the thread UID directly from the post number
		$thread_uid = $this->threadSingleton->resolveThreadUidFromResno($this->board, $resno);

		// If the thread UID is not valid, try to resolve from a child post
		if (!$this->threadSingleton->isThread($thread_uid)) {
			$post_uid = $this->PIO->resolvePostUidFromPostNumber($this->board, $resno);

			// Fetch the thread UID from the post's data
			$thread_uid_new = $this->PIO->fetchPosts($post_uid)[0]['thread_uid'] ?? false;

			// If still not valid, show error
			if (!$this->threadSingleton->isThread($thread_uid_new)) {
				$this->globalHTML->error("Thread not found!");
			}

			// Otherwise, redirect to the correct thread page and scroll to post
			$resnoNew = $this->threadSingleton->resolveThreadNumberFromUID($thread_uid_new); 
			$redirectString = $this->board->getBoardThreadURL($resnoNew, $resno);
			redirect($redirectString);
		}
	}
}
