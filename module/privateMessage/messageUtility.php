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
		// accepts secure and regular tripcodes; '!'/'!!' are legacy
		// equivalents of the '◆'/'★' trip key prefixes
		return (
			preg_match('/^◆.{10,}$/u', $tripCode) === 1 ||
			preg_match('/^★.{10,}$/u', $tripCode) === 1 ||
			preg_match('/^!{1,2}.{10,}$/u', $tripCode) === 1
		);
	}

	public function isValidTripCodeInput(string $tripCode): bool {
		return preg_match('/^#{1,2}.+$/', $tripCode) === 1;
	}

	/**
	 * Build the prefixed tripcode identity used as a PM address from a post's
	 * raw tripcode columns. Secure tripcodes take priority over regular ones.
	 * Returns an empty string when the post has no tripcode.
	 */
	public function buildTripcodeIdentity(string $tripcode, string $secureTripcode): string {
		if ($secureTripcode !== '') {
			return '★' . $secureTripcode;
		}

		if ($tripcode !== '') {
			return '◆' . $tripcode;
		}

		return '';
	}

	public function getUsertripCode(): ?string {
		return $_SESSION['private_message_tripcode'] ?? null;
	}

	/**
	 * @param bool $automatic True when the identity was adopted from a post rather
	 *                        than chosen on the login form.
	 */
	public function setUsertripCode(string $tripCode, bool $automatic = false): void {
		$_SESSION['private_message_tripcode'] = $tripCode;
		$_SESSION['private_message_tripcode_automatic'] = $automatic;
	}

	public function logoutUser(): void {
		unset($_SESSION['private_message_tripcode'], $_SESSION['private_message_tripcode_automatic']);
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

	/**
	 * Whether a tripcode from a post may take over the session. A login chosen on the
	 * form wins, so an identity the user picked is never swapped out from under them.
	 */
	public function canAdoptPostTripcode(): bool {
		return !$this->isLoggedIn() || !empty($_SESSION['private_message_tripcode_automatic']);
	}

	/**
	 * Log in as the tripcode a post was just made with. Posting with a tripcode proves
	 * the same thing the login form asks for, so no second login is needed.
	 */
	public function loginFromPostTripcode(string $tripcode, string $secureTripcode): void {
		if (!$this->canAdoptPostTripcode()) {
			return;
		}

		$identity = $this->buildTripcodeIdentity($tripcode, $secureTripcode);
		if ($identity === '' || $identity === $this->getUsertripCode()) {
			return;
		}

		$this->setUsertripCode($identity, true);
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