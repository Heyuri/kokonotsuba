<?php
// sticky module made for kokonotsuba by deadking
class mod_sticky extends moduleHelper {
	private $mypage;
	private $STICKYICON = '';

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);

		$this->STICKYICON = $this->config['STATIC_URL'] . 'image/sticky.png';
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(): string {
		return __CLASS__ . ' : K! Sticky Threads';
	}

	public function getModuleVersionInfo(): string {
		return 'Koko BBS Release 1';
	}

	public function autoHookThreadPost(array &$arrLabels, array $post, bool $isReply): void {
		$fh = new FlagHelper($post['status']);

		if ($fh->value('sticky')) {
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<img src="' . $this->STICKYICON . '" class="icon" height="18" width="18" title="Sticky">';
		}
	}

	public function autoHookAdminList(string &$modfunc, array $post, bool $isres): void {
		$staffSession = new staffAccountFromSession;

		if ($staffSession->getRoleLevel() < $this->config['AuthLevels']['CAN_STICKY']) return;

		if (!$isres) {
			$fh = new FlagHelper($post['status']);
			$toggleLabel = $fh->value('sticky') ? 'Unsticky' : 'Sticky post';
			$modfunc .= '<span class="adminStickyFunction">[<a href="' . $this->mypage . '&post_uid=' . $post['post_uid'] . '" title="' . $toggleLabel . '">S</a>]</span>';
		}
	}

		public function autoHookRegistAfterCommit($lastno, $resto, $name, $email, $sub, $com): void {
			$threadSingleton = threadSingleton::getInstance();
			
			$threads = $threadSingleton->getThreadListFromBoard($this->board);
			if (empty($threads)) return;

			$opPosts = $threadSingleton->getFirstPostsFromThreads($threads);

			foreach ($opPosts as $post) {
				$flags = new FlagHelper($post['status']);
				if ($flags->value('sticky')) {
					$threadSingleton->bumpThread($post['thread_uid'], true);
				}
			}
		}

	public function ModulePage(): void {
		$PIO = PIOPDO::getInstance();
		$threadSingleton = threadSingleton::getInstance();
		$softErrorHandler = new softErrorHandler($this->board);
		$actionLogger = ActionLogger::getInstance();
		$globalHTML = new globalHTML($this->board);

		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_STICKY']);

		$post_uid = $_GET['post_uid'] ?? null;
		$post = $PIO->fetchPosts($post_uid)[0] ?? null;

		if (!$post) {
			$globalHTML->error('ERROR: Post does not exist.');
		}

		if (!$PIO->isThreadOP($post['post_uid'])) {
			$globalHTML->error('ERROR: Cannot sticky a reply.');
		}

		$flags = $PIO->getPostStatus($post['post_uid']);
		$flags->toggle('sticky');
		$PIO->setPostStatus($post['post_uid'], $flags->toString());

		// Reset bump if sticky is removed
		if (!$flags->value('sticky')) {
			$threadSingleton->bumpThread($post['thread_uid']);
		}

		$actionLogger->logAction(
			'Changed sticky status on post No.' . $post['no'] . ' (' . ($flags->value('sticky') ? 'true' : 'false') . ')',
			$this->board->getBoardUID()
		);

		$this->board->rebuildBoard();
		redirect('back', 1);
	}
}
