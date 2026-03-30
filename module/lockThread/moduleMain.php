<?php
// thread stop module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\lockThread;

require_once __DIR__ . '/lockThreadLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getRoleLevelFromSession;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'K! Stop Threads';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addListener('ViewedThread', function (array &$templateValues, array &$threadData) {
			$this->overRidePostForm($templateValues['{$FORMDAT}'], $threadData['posts'][0]);
		});

		$this->moduleContext->moduleEngine->addListener('OpeningPost', function (&$arrLabels, $post) {
			$this->renderLockIcon($arrLabels['{$POSTINFO_EXTRA}'], $post);
		});

		$this->moduleContext->moduleEngine->addListener('RegistBegin', function (&$registInfo) {
			// A thread submission can't be locked, so just skip
			if($registInfo['isThreadSubmit']) {
				return;
			}

			$this->onRegistBegin($registInfo['thread'], $registInfo['roleLevel']);  // Call the method to modify the form
		});
	}

	private function overRidePostForm(&$formDat, $openingPost): void {
		$roleLevel = getRoleLevelFromSession();

		// render the post form for mod-san
		if($roleLevel->isAtLeast($this->getConfig('AuthLevels.CAN_LOCK'))) {
			return;
		}

		$status = new FlagHelper($openingPost->getStatus());

		if($status->value('stop')) {
			$formDat = '
				<div class="centerText">
					<p class="error">This thread is locked!</p>
					<p class="error">It cannot be replied to at this time.</p>
				</div>';
		}
	}

	public function onRegistBegin(?array &$thread, userRole $roleLevel) {
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

	public function renderLockIcon(string &$postInfoExtra, Post $post): void {
		// post OP status
		$status = $post->getFlags();
		
		// get static url
		$staticUrl = $this->getConfig('STATIC_URL');

		// get lock icon html
		$lockIconHtml = getLockIndicator($staticUrl);

		if ($status->value('stop')) {
			$postInfoExtra .= $lockIconHtml;
		}
	}

}
