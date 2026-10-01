<?php

namespace Kokonotsuba\Modules\fileBan;

require_once __DIR__ . '/fileBanRepository.php';
require_once __DIR__ . '/fileBanService.php';
require_once __DIR__ . '/fileBanLib.php';

use Kokonotsuba\action_log\actionLogReferences;
use Kokonotsuba\action_log\actionType;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\Modules\fileBan\getFileBanService;

class moduleMain extends abstractModuleMain {
	use AuditableTrait;
	use RegistBeginListenerTrait;

	private fileBanService $fileBanService;

	public function getName(): string {
		return 'File ban system';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->listenRegistBegin('onRegistBegin');

		$this->fileBanService = getFileBanService($this->moduleContext->transactionManager);
	}

	private function onRegistBegin(array &$registInfo): void {
		$files = $registInfo['files'];

		if (empty($files)) {
			return;
		}

		// collect all md5 hashes from uploaded files
		$md5Hashes = [];
		foreach ($files as $file) {
			if (!empty($file['md5'])) {
				$md5Hashes[] = $file['md5'];
			}
		}

		if (empty($md5Hashes)) {
			return;
		}

		// check all hashes in a single IN query
		$entry = $this->fileBanService->findFirstBannedEntry($md5Hashes);

		if ($entry !== null) {
			$this->logTrigger((int) $entry['id'], $entry['file_md5']);
			throw new BoardException(_T('file_ban_blocked', htmlspecialchars($entry['file_md5'])));
		}
	}

	/** Log the blocked upload as the visitor, linking to the file ban that stopped it. */
	private function logTrigger(int $entryId, string $md5): void {
		$reference = actionLogReferences::reference('fileban', $entryId, 'File ban #' . $entryId);

		// the upload is rejected inside the posting transaction, so write once that rolls back
		$this->moduleContext->transactionManager->afterRollback(fn() => $this->logAction(
			"{$reference} blocked an upload ({$md5})",
			$this->moduleContext->board->getBoardUID(),
			actionType::FILE_BAN_TRIGGER,
			true
		));
	}
}
