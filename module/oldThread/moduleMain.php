<?php

namespace Kokonotsuba\Modules\oldThread;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ViewedThreadListenerTrait;
use Kokonotsuba\thread\Thread;
use Kokonotsuba\thread\ThreadData;
use function Kokonotsuba\libraries\_T;

class moduleMain extends abstractModuleMain {
	use ViewedThreadListenerTrait;
	use RegistBeginListenerTrait;

	private const DEFAULT_REPLY_LIMIT_HOURS = 336;

	public function getName(): string {
		return 'oldThread : Thread Reply Time Limit';
	}

	public function getVersion(): string {
		return '1.0.0';
	}

	public function initialize(): void {
		$this->listenViewedThread('overridePostFormForOldThread');
		$this->listenRegistBegin('onRegistBegin');
	}

	private function overridePostFormForOldThread(array &$templateValues, ThreadData &$threadData): void {
		if (!$this->isThreadTooOld($threadData->getThread())) {
			return;
		}

		$tooOldMessage = _T('regist_thread_too_old');

		$templateValues['{$FORMDAT}'] = '
			<div class="centerText">
				<p class="error">' . $tooOldMessage . '</p>
			</div>';
	}

	public function onRegistBegin(array &$registInfo): void {
		if (!empty($registInfo['isThreadSubmit'])) {
			return;
		}

		$threadData = $registInfo['thread'] ?? null;
		if (!($threadData instanceof ThreadData)) {
			return;
		}

		if ($this->isThreadTooOld($threadData->getThread())) {
			throw new BoardException(_T('regist_thread_too_old'));
		}
	}

	private function isThreadTooOld(Thread $thread): bool {
		$limitHours = intval($this->getConfig('ModuleSettings.THREAD_REPLY_TIME_LIMIT', self::DEFAULT_REPLY_LIMIT_HOURS));
		if ($limitHours <= 0) {
			return false;
		}

		$threadCreatedAt = strtotime($thread->getCreatedTime());
		if ($threadCreatedAt === false) {
			return false;
		}

		$ageSeconds = time() - $threadCreatedAt;
		$limitSeconds = $limitHours * 3600;

		return $ageSeconds >= $limitSeconds;
	}
}
