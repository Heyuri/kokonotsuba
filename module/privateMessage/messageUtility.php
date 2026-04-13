<?php

namespace Kokonotsuba\Modules\privateMessage;

use Closure;
use function Kokonotsuba\libraries\generateTripcode;

class messageUtility {
	public function __construct(
        private Closure $getModulePageURLCallable,
		private string $tripSalt = '',
	) {}

	public function isValidTripCode(string $tripCode): bool {
		// accepts secure and regular tripcodes
		return (
			preg_match('/^◆.{10,}$/u', $tripCode) === 1 ||
			preg_match('/^★.{10,}$/u', $tripCode) === 1
		);
	}

	public function isValidTripCodeInput(string $tripCode): bool {
		return preg_match('/^#{1,2}.+$/', $tripCode) === 1;
	}

	public function getUsertripCode(): ?string {
		return $_SESSION['private_message_tripcode'] ?? null;
	}

	public function setUsertripCode(string $tripCode): void {
		$_SESSION['private_message_tripcode'] = $tripCode;
	}

	public function logoutUser(): void {
		unset($_SESSION['private_message_tripcode']);
	}

	public function isLoggedIn(): bool {
		$tripCode = $this->getUsertripCode();
		return $tripCode !== null && $this->isValidTripCode($tripCode);
	}

	public function loginUser(string $input): void {
		$tripcode = '';
		$secure_tripcode = '';
		if (str_starts_with($input, '##')) {
			$secure_tripcode = substr($input, 2);
		} else {
			$tripcode = substr($input, 1);
		}

		generateTripcode($tripcode, $secure_tripcode, $this->tripSalt);

		if ($secure_tripcode) {
			$this->setUsertripCode('★' . $secure_tripcode);
		} else {
			$this->setUsertripCode('◆' . $tripcode);
		}
	}

	public function getModulePageURL(array $additionalParams = [], bool $includeBaseUrl = false): string {
        return ($this->getModulePageURLCallable)($additionalParams, false, $includeBaseUrl);
	}

	public function parseName(string $rawName): array {
		[$nameOnly, $tripcode, $secureTripcode] = array_map('trim', explode('#', $rawName . '##'));

		generateTripcode($tripcode, $secureTripcode, $this->tripSalt);

		$tripcodeHash = '';
		if ($secureTripcode) {
			$tripcodeHash = '★' . $secureTripcode;
		} elseif ($tripcode) {
			$tripcodeHash = '◆' . $tripcode;
		}

		return [
			'name' => htmlspecialchars($nameOnly),
			'tripcode' => $tripcodeHash,
		];
	}
}