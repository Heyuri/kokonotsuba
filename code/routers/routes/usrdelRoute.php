<?php

class usrdelRoute {
	private readonly array $config;
	private readonly board $board;
	private readonly staffAccountFromSession $staffSession;
	private readonly globalHTML $globalHTML;
	private readonly moduleEngine $moduleEngine;
	private readonly actionLogger $actionLogger;
	private readonly mixed $PIO;
	private readonly mixed $FileIO;

	public function __construct(
		array $config,
		board $board,
		staffAccountFromSession $staffSession,
		globalHTML $globalHTML,
		moduleEngine $moduleEngine,
		actionLogger $actionLogger,
		mixed $PIO,
		mixed $FileIO
	) {
		$this->config = $config;
		$this->board = $board;
		$this->staffSession = $staffSession;
		$this->globalHTML = $globalHTML;
		$this->moduleEngine = $moduleEngine;
		$this->actionLogger = $actionLogger;
		$this->PIO = $PIO;
		$this->FileIO = $FileIO;
	}

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

		$haveperm = $this->staffSession->getRoleLevel() >= $this->config['roles']['LEV_JANITOR'];
		$this->moduleEngine->useModuleMethods('Authenticate', [$pwd, 'userdel', &$haveperm]);

		if ($pwd === '' && $pwdc !== '') $pwd = $pwdc;
		$pwd_md5 = substr(md5($pwd), 2, 8);
		$host = gethostbyaddr(new IPAddress);
		$search_flag = false;
		$delPosts = [];
		$delPostUIDs = [];

		if (!count($delno)) {
			$this->globalHTML->error(_T('del_notchecked'));
		}

		$posts = $this->PIO->fetchPosts($delno);

		foreach ($posts as $post) {
			if ($pwd_md5 == $post['pwd'] || $host == $post['host'] || $haveperm) {
				$search_flag = true;
				$delPostUIDs[] = intval($post['post_uid']);
				$delPosts[] = $post;
				$this->actionLogger->logAction("Delete post No." . $post['no'] . ($onlyimgdel ? ' (file only)' : ''), $this->board->getBoardUID());
			}
		}

		if ($search_flag) {
			if (!$onlyimgdel) {
				$this->moduleEngine->useModuleMethods('PostOnDeletion', [$delPosts, 'frontend']);
			}

			$files = createBoardStoredFilesFromArray($delPosts);

			if ($onlyimgdel) {
				$this->PIO->removeAttachments($delPostUIDs);
			} else {
				$this->PIO->removePosts($delPostUIDs);
			}

			$this->FileIO->deleteImagesByBoardFiles($files);
		} else {
			$this->globalHTML->error(_T('del_wrongpwornotfound'));
		}

		$this->board->rebuildBoard();

		if (isset($_POST['func']) && $_POST['func'] === 'delete') {
			if (isset($_SERVER['HTTP_REFERER'])) {
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: ' . $_SERVER['HTTP_REFERER']);
			}
		} else {
			redirect($this->config['PHP_SELF']);
		}
	}
}

