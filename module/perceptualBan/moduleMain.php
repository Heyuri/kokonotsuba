<?php

namespace Kokonotsuba\Modules\perceptualBan;

require_once __DIR__ . '/perceptualBanRepository.php';
require_once __DIR__ . '/perceptualBanService.php';
require_once __DIR__ . '/perceptualBanLib.php';
require_once __DIR__ . '/perceptualHasher.php';

use Kokonotsuba\action_log\actionLogReferences;
use Kokonotsuba\action_log\actionType;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;

use function Kokonotsuba\Modules\perceptualBan\getPerceptualBanService;
use function Kokonotsuba\libraries\_T;

class moduleMain extends abstractModuleMain {
	use AuditableTrait;
	use RegistBeginListenerTrait;

	private perceptualBanService $perceptualBanService;
	private perceptualHasher $perceptualHasher;

	public function getName(): string {
		return 'Perceptual file ban system';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->listenRegistBegin('onRegistBegin');

		$this->perceptualBanService = getPerceptualBanService($this->moduleContext->transactionManager);
		$this->perceptualHasher = getPerceptualHasher();
	}

	private function onRegistBegin(array &$registInfo): void {
		$files = $registInfo['files'];

		if (empty($files)) {
			return;
		}

		$threshold = $this->getModuleConfig('HAMMING_THRESHOLD', 10);

		foreach ($files as $fileMeta) {
			$file = $fileMeta['file'] ?? null;
			if (!$file) {
				continue;
			}

			$mimeType = $fileMeta['mimeType'] ?? '';
			if (!$this->perceptualHasher->isHashableMedia($mimeType)) {
				continue;
			}

			$tmpPath = $file->getTemporaryFileName();
			if (empty($tmpPath) || !file_exists($tmpPath)) {
				continue;
			}

			if ($this->perceptualHasher->needsFrameExtraction($mimeType)) {
				$match = $this->perceptualBanService->findMatchingBanAnimated($tmpPath, $threshold);
			} else {
				$match = $this->perceptualBanService->findMatchingBan($tmpPath, $threshold);
			}

			if ($match !== null) {
				$this->logTrigger((int) $match['id'], $match['phash_hex']);
				throw new BoardException(_T('file_ban_blocked', htmlspecialchars($match['phash_hex'])));
			}
		}
	}

	/** Log the blocked upload as the visitor, linking to the perceptual ban that stopped it. */
	private function logTrigger(int $entryId, string $hashHex): void {
		$reference = actionLogReferences::reference('perceptualban', $entryId, 'Perceptual file ban #' . $entryId);

		// the upload is rejected inside the posting transaction, so write once that rolls back
		$this->moduleContext->transactionManager->afterRollback(fn() => $this->logAction(
			"{$reference} blocked an upload ({$hashHex})",
			$this->moduleContext->board->getBoardUID(),
			actionType::FILE_BAN_TRIGGER,
			true
		));
	}
}
