<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\ban\visitorTokenSigner;

/**
 * The signature that makes a visitor token the engine's rather than the visitor's.
 *
 * Everything here is about one question: does a value the engine did not mint verify? It must
 * not, however plausible it looks, because a value that verifies is one somebody chose.
 */
final class VisitorTokenSignerTest extends TestCase {

	private function signer(string $secret = 'test secret'): visitorTokenSigner {
		return new visitorTokenSigner($secret);
	}

	public function testMintedTokensVerify(): void {
		$signer = $this->signer();
		$token = $signer->mint();

		$id = $signer->verify($token);

		$this->assertNotNull($id);
		$this->assertSame($token, $signer->sign($id));
	}

	/** The half the engine works in is the id alone: 32 hex characters, no signature. */
	public function testTheIdIsTheHalfWithoutTheSignature(): void {
		$signer = $this->signer();
		$id = $signer->verify($signer->mint());

		$this->assertSame(1, preg_match('/^[a-f0-9]{32}$/', $id));
	}

	public function testMintsAreNotRepeated(): void {
		$signer = $this->signer();

		$this->assertNotSame($signer->mint(), $signer->mint());
	}

	/** The point of the exercise: a hand-written value verifies as nothing. */
	public function testAnInventedTokenIsRefused(): void {
		$signer = $this->signer();

		$this->assertNull($signer->verify(str_repeat('a', 32)));
		$this->assertNull($signer->verify(str_repeat('a', 32) . '.' . str_repeat('b', 16)));
		$this->assertNull($signer->verify('hello'));
		$this->assertNull($signer->verify(''));
	}

	public function testAnEditedIdIsRefused(): void {
		$signer = $this->signer();
		$token = $signer->mint();
		[$id, $signature] = explode('.', $token);

		$edited = ($id[0] === '0' ? '1' : '0') . substr($id, 1);

		$this->assertNull($signer->verify($edited . '.' . $signature));
	}

	public function testAnEditedSignatureIsRefused(): void {
		$signer = $this->signer();
		[$id, $signature] = explode('.', $signer->mint());

		$edited = ($signature[0] === '0' ? '1' : '0') . substr($signature, 1);

		$this->assertNull($signer->verify($id . '.' . $edited));
	}

	/** A signature from somewhere else is worth no more than one made up. */
	public function testAnotherInstallsSignatureIsRefused(): void {
		$elsewhere = $this->signer('a different install');
		$token = $elsewhere->mint();

		$this->assertNull($this->signer()->verify($token));
	}

	/** Trailing junk must not ride along on a good signature. */
	public function testExtraPartsAreRefused(): void {
		$signer = $this->signer();
		$token = $signer->mint();

		$this->assertNull($signer->verify($token . '.extra'));
		$this->assertNull($signer->verify($token . 'a'));
		$this->assertNull($signer->verify(' ' . $token));
	}

	/** Uppercase is not the hex the engine writes, so it is somebody else's value. */
	public function testCaseIsNotForgiven(): void {
		$signer = $this->signer();

		$this->assertNull($signer->verify(strtoupper($signer->mint())));
	}

	/** An unsigned value is nobody's token, however well formed - the legacy path is gone. */
	public function testAnUnsignedIdIsNotAToken(): void {
		$signer = $this->signer();
		$id = $signer->verify($signer->mint());

		$this->assertNull($signer->verify($id));
	}

	/** Bans are matched on the token hash, so it has to be stable, keyed and long enough. */
	public function testTokenHashesAreStableAndKeyed(): void {
		$id = str_repeat('a', 32);

		$this->assertSame($this->signer()->tokenHash($id), $this->signer()->tokenHash($id));
		$this->assertSame(1, preg_match('/^[a-f0-9]{16}$/', $this->signer()->tokenHash($id)));
		$this->assertNotSame(
			$this->signer()->tokenHash($id),
			(new visitorTokenSigner('a different install'))->tokenHash($id)
		);
	}

	/**
	 * A token hash is what a browser-tied ban is matched on, so two browsers sharing one would
	 * put a stranger under somebody else's ban. Sweep a realistic population for a collision.
	 */
	public function testTokenHashesDoNotCollideAcrossManyBrowsers(): void {
		$signer = $this->signer();
		$seen = [];

		for ($i = 0; $i < 20000; $i++) {
			$tokenHash = $signer->tokenHash(bin2hex(random_bytes(16)));

			$this->assertFalse(isset($seen[$tokenHash]), 'two browsers were given the same token hash');
			$seen[$tokenHash] = true;
		}

		$this->assertCount(20000, $seen);
	}

	/** Nothing a visitor holds is ever the empty string, which is what 'kept no cookie' means. */
	public function testATokenHashIsNeverEmpty(): void {
		$signer = $this->signer();

		foreach ([str_repeat('0', 32), str_repeat('f', 32), $signer->verify($signer->mint())] as $id) {
			$this->assertNotSame('', $signer->tokenHash((string) $id), 'a token hash came out empty');
		}
	}

	/** A token hash must not be the signature over the same id wearing a different hat. */
	public function testTokenHashesAreNotSignatures(): void {
		$signer = $this->signer();
		$id = str_repeat('b', 32);

		$this->assertNotSame(substr($signer->sign($id), 33), $signer->tokenHash($id));
	}
}
