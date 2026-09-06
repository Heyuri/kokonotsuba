<?php

namespace Kokonotsuba\ban;

/**
 * Signs the visitor token so only the engine's own tokens are honoured.
 *
 * The cookie carries `id.signature`, where the id is the 128 random bits everything else deals
 * in and the signature is an HMAC over it under a key nobody else has. Editing either half by
 * hand produces a value that verifies as nothing, so it is discarded and a fresh token issued -
 * exactly as if the cookie had been deleted.
 *
 * This is anti-tamper, not access control. Nothing is protected by holding a token, and shedding
 * one is a visitor's own business; what it stops is somebody *choosing* their token - picking a
 * value they know is unbanned, colliding with another visitor's, or keeping a hand-written one
 * that the engine would otherwise take on trust forever.
 *
 * Nothing about a token is stored server-side at all. A ban tied to a browser holds the
 * token hash below, recomputed from the cookie on every request, so rotating the key
 * invalidates cookies and the ties made under them together.
 */
final class visitorTokenSigner {
	/** Hex characters of HMAC kept. 64 bits is far more than a value nobody gains by forging needs. */
	private const SIGNATURE_LENGTH = 16;

	/**
	 * Hex characters of a token hash. 64 bits, because bans are matched on it: at 32 a visitor
	 * could collide with somebody else's ban. Staff are shown the first 8.
	 */
	private const TOKEN_HASH_LENGTH = 16;

	private readonly string $key;

	/**
	 * @param string $secret Key material; the install's own, and the same on every request.
	 */
	public function __construct(string $secret) {
		// Derived rather than used raw, so a secret shared with tripcodes or IDs cannot have this
		// signature read off it or the other way about.
		$this->key = hash('sha256', 'kokonotsuba/visitor-token/v1' . $secret, true);
	}

	/** A brand new token, signed and ready to be a cookie value. */
	public function mint(): string {
		return $this->sign(bin2hex(random_bytes(16)));
	}

	/** The cookie value for an id. */
	public function sign(string $id): string {
		return $id . '.' . $this->signatureFor($id);
	}

	/** How much of a token hash is shown to staff. */
	public const DISPLAY_LENGTH = 8;

	/**
	 * The id inside a properly signed value, or null.
	 *
	 * Compared with hash_equals so a wrong signature takes the same time as a right one.
	 */
	public function verify(string $value): ?string {
		$parts = explode('.', $value);

		if (count($parts) !== 2) {
			return null;
		}

		[$id, $signature] = $parts;

		if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1
			|| preg_match('/^[a-f0-9]{' . self::SIGNATURE_LENGTH . '}$/', $signature) !== 1) {
			return null;
		}

		return hash_equals($this->signatureFor($id), $signature) ? $id : null;
	}

	/**
	 * The value read as a bare id, signature not considered.
	 *
	 * What a token minted before signing existed looks like; banService only honours one of these
	 * when the database can vouch that the engine really did mint it.
	 */
	public static function idOf(string $value): ?string {
		return preg_match('/^[a-f0-9]{32}$/', $value) === 1 ? $value : null;
	}

	/**
	 * A short stable label for a token, for showing staff which browser a post came from.
	 *
	 * Keyed the same way the signature is, so the label cannot be turned back into the token or
	 * computed by anyone without the install's secret; the message is prefixed so a token hash
	 * can never coincide with a signature over the same id. That prefix is part of the stored
	 * format - every hash already on a post, a ban or a note was made with it - so it stays.
	 */
	public function tokenHash(string $id): string {
		return substr(hash_hmac('sha256', 'fingerprint:' . $id, $this->key), 0, self::TOKEN_HASH_LENGTH);
	}

	private function signatureFor(string $id): string {
		return substr(hash_hmac('sha256', $id, $this->key), 0, self::SIGNATURE_LENGTH);
	}
}
