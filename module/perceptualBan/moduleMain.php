<?php

namespace Kokonotsuba\Modules\perceptualBan;

require_once __DIR__ . '/perceptualBanRepository.php';
require_once __DIR__ . '/perceptualBanService.php';
require_once __DIR__ . '/perceptualBanLib.php';
require_once __DIR__ . '/perceptualHasher.php';

use Kokonotsuba\module_classes\abstractModuleMain;

use function Kokonotsuba\Modules\perceptualBan\getPerceptualBanService;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;

class moduleMain extends abstractModuleMain {
	private perceptualBanService $perceptualBanService;
	private perceptualHasher $perceptualHasher;
	private string $globalBansPath;

	public function getName(): string {
		return 'Perceptual file ban system';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addListener('RegistBegin', function (&$registInfo) {
			$this->onRegistBegin($registInfo['files'], (string) $registInfo['ip']);
		});

		$this->perceptualBanService = getPerceptualBanService($this->moduleContext->transactionManager);
		$this->perceptualHasher = getPerceptualHasher();
		$this->globalBansPath = getBackendGlobalDir() . $this->getConfig('GLOBAL_BANS');
	}

	private function onRegistBegin(array $files, string $ip): void {
		if (empty($files)) {
			return;
		}

		// hamming distance threshold from config, default 10
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
				$this->banIp($ip);
				$this->silentReject();
			}
		}
	}

	private function banIp(string $ip): void {
		$startTime = $_SERVER['REQUEST_TIME'];
		$banDuration = $this->getConfig('ModuleSettings.perceptualBan.BAN_DURATION', 86400);
		// jitter ±10% so the duration doesn't look automated
		$jitter = (int) ($banDuration * 0.1);
		$banDuration += mt_rand(-$jitter, $jitter);
		$expires = $startTime + $banDuration;
		$reason = str_replace(',', '&#44;', 'ban evasion');

		$banEntry = "{$ip},{$startTime},{$expires},{$reason}";

		$needsNewline = file_exists($this->globalBansPath) && filesize($this->globalBansPath) > 0;

		$f = fopen($this->globalBansPath, 'a');
		if ($f) {
			if ($needsNewline) {
				fwrite($f, "\n");
			}
			fwrite($f, $banEntry);
			fclose($f);
		}
	}

	private function silentReject(): void {
		$boardUrl = $this->moduleContext->board->getBoardURL();

		if (isJavascriptRequest()) {
			sendJsonResponse(['redirectUrl' => $boardUrl]);
			exit;
		}

		redirect($boardUrl);
	}
}
