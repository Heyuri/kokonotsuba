<?php

namespace Kokonotsuba\module_classes\traits;

/**
 * Trait providing shared ban file operations for modules.
 *
 * Requires the using class to extend abstractModule (which provides $this->moduleContext and $this->getConfig()).
 */
trait BanFileOperationsTrait {

	protected function getBanFilePath(): string {
		return $this->moduleContext->board->getBoardStoragePath() . 'bans.log.txt';
	}

	protected function getGlobalBanFilePath(): string {
		return getBackendGlobalDir() . $this->getConfig('GLOBAL_BANS');
	}

	protected function readBanLog(string $banFile): array {
		return is_file($banFile) ? array_map('rtrim', file($banFile)) : [];
	}

	protected function writeBanLog(string $banFile, array $entries): void {
		file_put_contents($banFile, implode(PHP_EOL, $entries));
	}

	protected function addBanEntry(string $banFile, string $ip, int $startTime, int $expires, string $reason): void {
		$log = $this->readBanLog($banFile);
		$reason = str_replace(["\r\n", "\n", "\r"], '<br />', $reason);
		$reason = str_replace(',', '&#44;', $reason);
		$log[] = "{$ip},{$startTime},{$expires},{$reason}";
		$this->writeBanLog($banFile, $log);
	}

	protected function removeBanEntries(string $banFile, array $indicesToRemove): array {
		$log = $this->readBanLog($banFile);
		$newLog = [];
		$removedIps = [];

		foreach ($log as $i => $entry) {
			if (in_array($i, $indicesToRemove, true)) {
				$parts = explode(',', $entry, 4);
				if (!empty($parts[0])) {
					$removedIps[] = $parts[0];
				}
			} else {
				$newLog[] = $entry;
			}
		}

		$this->writeBanLog($banFile, $newLog);
		return $removedIps;
	}

	protected function calculateBanDuration(string $duration): int {
		preg_match_all('/(\d+(\.\d+)?)([ywdhm])/', $duration, $matches, PREG_SET_ORDER);
		$seconds = 0;

		foreach ($matches as $match) {
			$value = floatval($match[1]);
			switch ($match[3]) {
				case 'y': $seconds += $value * 31536000; break;
				case 'm': $seconds += $value * 2597120; break;
				case 'w': $seconds += $value * 604800; break;
				case 'd': $seconds += $value * 86400; break;
				case 'h': $seconds += $value * 3600; break;
			}
		}

		return (int) $seconds;
	}

	protected function isIpBannedInFile(string $ip, string $banFile): bool {
		$log = $this->readBanLog($banFile);
		$now = time();

		foreach ($log as $entry) {
			if (empty($entry)) continue;
			$parts = explode(',', $entry, 4);
			if (count($parts) < 3) continue;

			[$pattern, $start, $expires] = $parts;

			// skip expired bans
			if ((int) $expires > 0 && (int) $expires < $now) continue;

			// exact match
			if ($ip === $pattern) return true;

			// wildcard match
			if (str_contains($pattern, '*')) {
				$regex = '/^' . str_replace('\*', '[0-9.]+', preg_quote($pattern, '/')) . '$/';
				if (preg_match($regex, $ip) === 1) return true;
			}
		}

		return false;
	}

	protected function isIpBanned(string $ip): bool {
		return $this->isIpBannedInFile($ip, $this->getBanFilePath())
			|| $this->isIpBannedInFile($ip, $this->getGlobalBanFilePath());
	}
}
