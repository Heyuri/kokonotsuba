<?php
// thread stop module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\lockThread;

require_once __DIR__ . '/lockThreadLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\ViewedThreadListenerTrait;
use Kokonotsuba\module_classes\listeners\OpeningPostListenerTrait;
use Kokonotsuba\module_classes\listeners\RegistBeginListenerTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getRoleLevelFromSession;

class moduleMain extends abstractModuleMain {
	use ViewedThreadListenerTrait;
	use OpeningPostListenerTrait;
	use RegistBeginListenerTrait;
	public function getName(): string {
		return 'K! Stop Threads';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->listenViewedThread('overRidePostForm');
		$this->listenOpeningPost('renderLockIcon');
		$this->listenRegistBegin('onRegistBegin');
	}

	private function overRidePostForm(array &$templateValues, array &$threadData): void {
		$roleLevel = getRoleLevelFromSession();

		// render the post form for mod-san
		if($roleLevel->isAtLeast($this->getConfig('AuthLevels.CAN_LOCK'))) {
			return;
		}

		$openingPost = $threadData['posts'][0];
		$status = new FlagHelper($openingPost->getStatus());

		if($status->value('stop')) {
			$templateValues['{$FORMDAT}'] = '
				<div class="centerText">
					<p class="error">This thread is locked!</p>
					<p class="error">It cannot be replied to at this time.</p>
				</div>';
		}
	}

	public function onRegistBegin(array &$registInfo) {
		// A thread submission can't be locked, so just skip
		if($registInfo['isThreadSubmit']) {
			return;
		}

		$thread = $registInfo['thread'];
		$roleLevel = $registInfo['roleLevel'];

		if (!empty($thread) && $roleLevel->isLessThan($this->getConfig('AuthLevels.CAN_LOCK'))) {
			$openingPost = $thread['posts'][0] ?? null;

			// return early if opening post wasn't found
			if(!$openingPost) {
				return;
			}

			$status = new FlagHelper($openingPost->getStatus());

			if($status->value('stop')) {
				throw new BoardException('ERROR: This thread is locked.');
			}
		}
	}

	public function renderLockIcon(array &$templateValues, Post $post): void {
		// post OP status
		$status = $post->getFlags();
		
		// get static url
		$staticUrl = $this->getConfig('STATIC_URL');

		// get lock icon html
		$lockIconHtml = getLockIndicator($staticUrl);

		if ($status->value('stop')) {
			$templateValues['{$POSTINFO_EXTRA}'] .= $lockIconHtml;
		}
	}

}
