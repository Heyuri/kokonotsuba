<?php

namespace Kokonotsuba\Modules\privateMessage;

use Closure;

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
		if (str_starts_with($input, '##')) {
			// Secure tripcode
			$password = substr($input, 2);
			$sha = str_rot13(base64_encode(pack('H*', sha1($password . $this->tripSalt))));
			$tripCode = '★' . substr($sha, 0, 10);
		} else {
			// Regular tripcode
			$password = substr($input, 1);
			$password = mb_convert_encoding($password, 'Shift_JIS', 'UTF-8');
			$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($password . 'H.', 1, 2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
			$tripCode = '◆' . substr(crypt($password, $salt), -10);
		}

		$this->setUsertripCode($tripCode);
	}

	public function getModulePageURL(array $additionalParams = [], bool $includeBaseUrl = true): string {
        return ($this->getModulePageURLCallable)($additionalParams, false, $includeBaseUrl);
	}
}