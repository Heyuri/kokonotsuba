<?php

namespace Kokonotsuba\Modules\perceptualBan;

require_once __DIR__ . '/perceptualBanRepository.php';
require_once __DIR__ . '/perceptualBanService.php';
require_once __DIR__ . '/perceptualBanLib.php';
require_once __DIR__ . '/perceptualHasher.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;

use function Kokonotsuba\Modules\perceptualBan\getPerceptualBanService;
use function Kokonotsuba\libraries\_T;

class moduleMain extends abstractModuleMain {
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

		$threshold = $this->getConfig('ModuleSettings.perceptualBan.HAMMING_THRESHOLD', 10);

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
				$isBanned = $this->perceptualBanService->isPerceptuallyBannedAnimated($tmpPath, $threshold);
			} else {
				$isBanned = $this->perceptualBanService->isPerceptuallyBanned($tmpPath, $threshold);
			}

			if ($isBanned) {
				throw new BoardException(_T('file_ban_blocked'));
			}
		}
	}
}
