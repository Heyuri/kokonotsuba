<?php

namespace Kokonotsuba\Modules\lockDown;

include_once __DIR__ . '/lockDownLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\ip\ipAnonymizer;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;

use function Kokonotsuba\libraries\_T;

/** While a board is locked down, only browsers or addresses that posted there before it began may post. */
class moduleMain extends abstractModuleMain {
	use RegistBeginListenerTrait;

	public function getName(): string {
		return 'lockDown : Board lock-down';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		$this->listenRegistBegin('onRegistBegin');
	}

	public function onRegistBegin(array &$registInfo): void {
		if ($registInfo['roleLevel']->isStaff()) {
			return;
		}

		$lockDown = makeLockDownFlag($this->moduleContext)->read();

		if ($lockDown === null) {
			return;
		}

		$repository = makeLockDownRepository($this->moduleContext);
		$boardUid = $this->moduleContext->board->getBoardUID();

		$since = (string) $lockDown['started_at'];
		$tokenHash = $this->moduleContext->banService->hashIncomingToken();

		if ($tokenHash !== '' && $repository->tokenPostedBefore($boardUid, $tokenHash, $since)) {
			return;
		}

		$hostForms = ipAnonymizer::fromSettings()->storedForms((string) $registInfo['ip']);

		if ($repository->hostPostedBefore($boardUid, $hostForms, $since)) {
			return;
		}

		throw new BoardException(_T('lockdown_blocked'), 403);
	}
}
