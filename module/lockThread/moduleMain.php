<?php
// thread stop module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\lockThread;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleMain;
use Kokonotsuba\Root\Constants\userRole;

class moduleMain extends abstractModuleMain {
	private $LOCKICON = '';

	public function getName(): string {
		return 'K! Stop Threads';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->LOCKICON = $this->getConfig('STATIC_URL') . '/image/locked.png';

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

		$status = new FlagHelper($openingPost['status']);

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
			$openingPost = $thread['posts'][0];
			$status = new FlagHelper($openingPost['status']);

			if($status->value('stop')) {
				throw new BoardException('ERROR: This thread is locked.');
			}
		}
	}

	public function renderLockIcon(string &$postInfoExtra, $post): void {
		$status = new FlagHelper($post['status']);
		
		if ($status->value('stop')) {
			$postInfoExtra .= '<img src="'.$this->LOCKICON.'" class="icon" width="16" height="16" title="Locked">';
		}
	}

}
