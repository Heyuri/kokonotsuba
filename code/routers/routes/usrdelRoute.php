<?php

class usrdelRoute {
	public function __construct(
		private readonly array $config,
		private board $board,
		private moduleEngine $moduleEngine,
		private readonly actionLoggerService $actionLoggerService,
		private readonly postRepository $postRepository,
		private readonly postService $postService,
		private readonly attachmentService $attachmentService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly array $regularBoards,
		private mixed $FileIO
	) {}

	/* User post deletion */
	public function userPostDeletion(): void {
		$pwd = $_POST['pwd'] ?? '';
		$pwdc = $_COOKIE['pwdc'] ?? '';
		$onlyimgdel = $_POST['onlyimgdel'] ?? '';
		$delno = [];

		reset($_POST);
		foreach ($_POST as $key => $val) {
			if ($val === 'delete') {
				$delno[] = $key;
			}
		}

		$delno = array_map('intval', $delno);

		$havePerm = isActiveStaffSession();
		$this->moduleEngine->dispatch('Authenticate', [$pwd, 'userdel', &$havePerm]);

		if ($pwd === '' && $pwdc !== '') $pwd = $pwdc;
		$pwd_md5 = substr(md5($pwd), 2, 8);
		$host = gethostbyaddr(new IPAddress);
		$search_flag = false;
		$delPosts = [];
		$delPostUIDs = [];


		if (!count($delno)) {
			$this->softErrorHandler->errorAndExit(_T('del_notchecked'));
		}

		$delno = implode(',', $delno);

		$posts = $this->postRepository->getPostsByUids(strval($delno));

		foreach ($posts as $post) {
			if ($pwd_md5 == $post['pwd'] || $host == $post['host'] || $havePerm) {
				$search_flag = true;
				$delPostUIDs[] = intval($post['post_uid']);
				$delPosts[] = $post;
				$this->actionLoggerService->logAction("Deleted post No." . $post['no'] . ($onlyimgdel ? ' (file only)' : ''), $this->board->getBoardUID());
			}
		}

		if ($search_flag) {
			if (!$onlyimgdel) {
				$this->moduleEngine->dispatch('PostOnDeletion', [$delPosts, 'frontend']);
			}

			$files = createBoardStoredFilesFromArray($delPosts, $this->regularBoards);

			if ($onlyimgdel) {
				$this->attachmentService->removeAttachments($delPostUIDs);
			} else {
				$this->postService->removePosts($delPostUIDs);
			}

			$this->FileIO->deleteImagesByBoardFiles($files);
		} else {
			$this->softErrorHandler->errorAndExit(_T('del_wrongpwornotfound'));
		}

		$this->board->rebuildBoard();

		if (isset($_POST['func']) && $_POST['func'] === 'delete') {
			if (isset($_SERVER['HTTP_REFERER'])) {
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: ' . $_SERVER['HTTP_REFERER']);
			}
		} else {
			redirect($this->config['LIVE_INDEX_FILE']);
		}
	}
}

