<?php

namespace Kokonotsuba\Modules\fileBan;

require_once __DIR__ . '/fileBanRepository.php';
require_once __DIR__ . '/fileBanService.php';
require_once __DIR__ . '/fileBanLib.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\RegistBeginListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\Modules\fileBan\getFileBanService;

class moduleMain extends abstractModuleMain {
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
		$bannedHashes = $this->fileBanService->findBannedHashes($md5Hashes);

		if (!empty($bannedHashes)) {
			throw new BoardException(_T('file_ban_blocked'));
		}
	}
}
