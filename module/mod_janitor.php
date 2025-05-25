<?php
// komeo 2023
class mod_janitor extends moduleHelper {
	private $BANFILE = -1;
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->BANFILE = $this->board->getBoardStoragePath() . 'bans.log.txt';
		$this->mypage = $this->getModulePageURL();
		touch($this->BANFILE);
	}

	public function getModuleName() {
		return __CLASS__ . ' : Janitor tools';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		if ($staffSession->getRoleLevel() != \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR->value) return;

		$modfunc .= '<span class="adminFunctions adminWarnFunction">[<a href="' . $this->mypage . '&post_uid=' . $post['post_uid'] . '" title="Warn">W</a>]</span>';
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$actionLogger = ActionLogger::getInstance();
		$globalHTML = new globalHTML($this->board);
		$softErrorHandler = new softErrorHandler($globalHTML);

		$softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_JANITOR);

		$postUidFromGET = $_GET['post_uid'] ?? '';
		$postNumber = $PIO->resolvePostNumberFromUID($postUidFromGET);

		
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$templateValues = [
				'{$FORM_ACTION}'			=> $this->config['PHP_SELF'],
				'{$POST_NUMBER}'			=> $postNumber ? htmlspecialchars($postNumber) : "No post selected.",
				'{$POST_UID}'				=> htmlspecialchars($postUidFromGET),
				'{$REASON_DEFAULT}'	=> 'No reason given.'
			];

			$janitorWarnFormHtml = $this->adminPageRenderer->ParseBlock('JANITOR_WARN_FORM', $templateValues);
			echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $janitorWarnFormHtml], true);
			return;
		}

		// POST processing
		$post_uid = $_POST['post_uid'] ?? '';
		$post = $PIO->fetchPosts($post_uid)[0] ?? null;
		if (!$post) {
			(new globalHTML($this->board))->error('ERROR: That post does not exist.');
			return;
		}

		$ip = $post['host'];
		$reason = str_replace(",", "&#44;", preg_replace("/[\r\n]/", '', nl2br($_POST['msg'] ?? '')));
		if (!$reason) $reason = 'No reason given.';

		if (!empty($_POST['public'])) {
			$post['com'] .= "<p class=\"warning\">($reason) <img class=\"banIcon icon\" alt=\"banhammer\" src=\"" . $this->config['STATIC_URL'] . "/image/hammer.gif\"></p>";
			$PIO->updatePost($post_uid, $post);
		}

		$log = array_map('rtrim', file($this->BANFILE));
		$rtime = $_SERVER['REQUEST_TIME'];
		$log[] = "$ip,$rtime,$rtime,$reason";
		file_put_contents($this->BANFILE, implode(PHP_EOL, $log) . PHP_EOL);

		$actionLogger->logAction('Warned ' . $ip . ' for post: ' . $postNumber, $this->board->getBoardUID());

		$this->board->rebuildBoard();
		redirect('back', 0);
	}
}
