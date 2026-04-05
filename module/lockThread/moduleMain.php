<?php
// thread stop module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\lockThread;

require_once __DIR__ . '/lockThreadLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\ViewedThreadListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\OpeningPostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;
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
		$this->listenOpeningPost('renderLockIcon', 20);
		$this->listenRegistBegin('onRegistBegin');
	}

	private function overRidePostForm(array &$templateValues, &$threadData): void {
		$roleLevel = getRoleLevelFromSession();

		// render the post form for mod-san
		if($roleLevel->isAtLeast($this->getConfig('AuthLevels.CAN_LOCK'))) {
			return;
		}

		$openingPost = $threadData->getOpeningPost();
		$status = $openingPost->getFlags();

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
			$openingPost = $thread->getOpeningPost();

			// return early if opening post wasn't found
			if(!$openingPost) {
				return;
			}

			$status = $openingPost->getFlags();

			if($status->value('stop')) {
				throw new BoardException('ERROR: This thread is locked.');
			}
		}
	}

	public function renderLockIcon(array &$templateValues, Post $post): void {
		$status = $post->getFlags();
		$lockIconHtml = getLockIndicator($this->getConfig('STATIC_URL'));
		$isActive = $status->value('stop');
		$hiddenClass = $isActive ? '' : ' indicatorHidden';

		$templateValues['{$POSTINFO_EXTRA}'] .= '<span class="indicator indicator-lock' . $hiddenClass . '">' . $lockIconHtml . '</span>';
	}

}
