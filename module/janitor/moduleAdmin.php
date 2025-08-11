<?php
// komeo 2023

namespace Kokonotsuba\Modules\janitor;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return userRole::LEV_JANITOR;
	}

	public function getName(): string {
		return 'Janitor tools';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}
	
	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->renderWarnButton($modControlSection, $post);
			}
		);
	}

	public function renderWarnButton(string &$modfunc, array $post) {
		$janitorWarnUrl = $this->getModulePageURL(
			[
				'post_uid' => $post['post_uid']
			]
		);
		
		$modfunc .= '<span class="adminFunctions adminWarnFunction">[<a href="' . $janitorWarnUrl . '" title="Warn">W</a>]</span>';
	}

	public function ModulePage() {
		$post_uid = $_REQUEST['post_uid'] ?? 0;
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($post_uid);

		
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$templateValues = [
				'{$FORM_ACTION}'			=> $this->getModulePageURL(),
				'{$POST_NUMBER}'			=> $postNumber ? htmlspecialchars($postNumber) : "No post selected.",
				'{$POST_UID}'				=> htmlspecialchars($post_uid),
				'{$REASON_DEFAULT}'	=> 'No reason given.'
			];

			$janitorWarnFormHtml = $this->moduleContext->adminPageRenderer->ParseBlock('JANITOR_WARN_FORM', $templateValues);
			echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $janitorWarnFormHtml], true);
			return;
		}

		$post = $this->moduleContext->postRepository->getPostByUid($post_uid);
		if (!$post) {
			throw new BoardException('ERROR: That post does not exist.');
			return;
		}

		$ip = $post['host'];
		$reason = str_replace(",", "&#44;", preg_replace("/[\r\n]/", '', nl2br($_POST['msg'] ?? '')));
		if (!$reason) $reason = 'No reason given.';

		if (!empty($_POST['public'])) {
			$post['com'] .= "<p class=\"warning\">($reason) <img class=\"banIcon icon\" alt=\"banhammer\" src=\"" . $this->getConfig('STATIC_URL') . "/image/hammer.gif\"></p>";
			$this->moduleContext->postRepository->updatePost($post_uid, $post);
		}

		$board = searchBoardArrayForBoard($post['boardUID']);

		$BANFILE = $board->getBoardStoragePath() . 'bans.log.txt';
		touch($BANFILE);

		$log = array_map('rtrim', file($BANFILE));
		$rtime = $_SERVER['REQUEST_TIME'];
		$log[] = "$ip,$rtime,$rtime,$reason";
		file_put_contents($BANFILE, implode(PHP_EOL, $log) . PHP_EOL);

		$this->moduleContext->actionLoggerService->logAction('Warned ' . $ip . ' for post No. ' . $postNumber, $board->getBoardUID());

		$board->rebuildBoard();
		redirect($board->getBoardURL());
	}
}
