<?php

namespace Kokonotsuba\Modules\nameRandomizer;

use Kokonotsuba\module_classes\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private array $names;
	private string $claimsFile;
	private int $claimTtl;

	public function getName(): string {
		return 'Name Randomizer';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		$this->names = $this->getConfig('ModuleSettings.NAME_RANDOMIZER_NAMES', []);

		if (empty($this->names)) {
			return;
		}

		$this->claimsFile = getBackendGlobalDir() . 'name_randomizer_claims.dat';
		$this->claimTtl = $this->getConfig('ModuleSettings.NAME_RANDOMIZER_TTL', 43200);

		// Priority -1: runs after the tripcode module (priority 0) so we can overwrite its values
		$this->moduleContext->moduleEngine->addListener('RegistBegin', function (array &$registInfo) {
			$this->onRegistBegin($registInfo);
		}, -1);
	}

	private function onRegistBegin(array &$registInfo): void {
		$nameIndex = $this->getOrCreateSessionIndex();

		$registInfo['name'] = $this->names[$nameIndex];
		$registInfo['tripcode'] = empty($registInfo['capcode']) ? $this->generateTripcode($nameIndex) : '';
		$registInfo['secure_tripcode'] = '';
		$registInfo['tripcode_input'] = '';
		$registInfo['secure_tripcode_input'] = '';
	}

	private function getOrCreateSessionIndex(): int {
		$sessionKey = 'name_randomizer_id';
		$max = count($this->names);
		$sessionId = session_id();

		$fp = fopen($this->claimsFile, 'c+');
		if (!$fp) {
			return $this->fallbackIndex($sessionKey, $max);
		}

		flock($fp, LOCK_EX);

		$claims = $this->readClaims($fp);
		$now = time();

		// Purge expired claims
		$claims = array_filter($claims, fn(array $c) => ($now - $c['time']) < $this->claimTtl);

		// Check if this session already has a valid claim
		foreach ($claims as &$claim) {
			if ($claim['sid'] === $sessionId && $claim['idx'] < $max) {
				$claim['time'] = $now;
				$this->writeClaims($fp, $claims);
				flock($fp, LOCK_UN);
				fclose($fp);
				$_SESSION[$sessionKey] = $claim['idx'];
				return $claim['idx'];
			}
		}
		unset($claim);

		// Find unclaimed indices
		$claimedIndices = array_column($claims, 'idx');
		$available = array_diff(range(0, $max - 1), $claimedIndices);

		if (!empty($available)) {
			$available = array_values($available);
			$idx = $available[random_int(0, count($available) - 1)];
		} else {
			// All claimed — fall back to random (uniqueness impossible)
			$idx = random_int(0, $max - 1);
		}

		$claims[] = ['sid' => $sessionId, 'idx' => $idx, 'time' => $now];
		$this->writeClaims($fp, $claims);
		flock($fp, LOCK_UN);
		fclose($fp);

		$_SESSION[$sessionKey] = $idx;
		return $idx;
	}

	private function readClaims($fp): array {
		rewind($fp);
		$claims = [];
		while (($line = fgets($fp)) !== false) {
			$line = trim($line);
			if ($line === '') continue;
			$parts = explode('|', $line, 3);
			if (count($parts) === 3) {
				$claims[] = ['sid' => $parts[0], 'idx' => (int)$parts[1], 'time' => (int)$parts[2]];
			}
		}
		return $claims;
	}

	private function writeClaims($fp, array $claims): void {
		ftruncate($fp, 0);
		rewind($fp);
		foreach ($claims as $claim) {
			fwrite($fp, $claim['sid'] . '|' . $claim['idx'] . '|' . $claim['time'] . "\n");
		}
		fflush($fp);
	}

	private function fallbackIndex(string $sessionKey, int $max): int {
		if (!isset($_SESSION[$sessionKey]) || $_SESSION[$sessionKey] >= $max) {
			$_SESSION[$sessionKey] = random_int(0, $max - 1);
		}
		return $_SESSION[$sessionKey];
	}

	private function generateTripcode(int $nameIndex): string {
		$salt = $this->getConfig('TRIPSALT', '');
		$raw = hash('sha256', 'name_randomizer_' . $nameIndex . '_' . $salt);
		return substr(base64_encode(hex2bin($raw)), 0, 10);
	}
}
