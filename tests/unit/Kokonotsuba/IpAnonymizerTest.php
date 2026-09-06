<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\ip\ipAnonymizer;

/**
 * Unit tests for the salted IP hasher shared by the anonymizer and every lookup that has to
 * match a row filed either side of an anonymization run.
 */
final class IpAnonymizerTest extends TestCase {

	private const SALT = 'a-long-random-secret';

	public function testHashIsSaltedTruncatedSha512(): void {
		$anonymizer = new ipAnonymizer(self::SALT);
		$expected = substr(hash('sha512', self::SALT . '203.0.113.7'), 0, 16);

		$this->assertSame($expected, $anonymizer->hash('203.0.113.7'));
	}

	public function testHashLengthMatchesTheSqlTruncation(): void {
		$hash = (new ipAnonymizer(self::SALT))->hash('203.0.113.7');

		$this->assertSame(ipAnonymizer::HASH_LENGTH, strlen($hash));
		$this->assertMatchesRegex('/^[0-9a-f]{16}$/', $hash);
	}

	public function testADifferentSaltGivesADifferentHash(): void {
		$this->assertNotSame(
			(new ipAnonymizer('one'))->hash('203.0.113.7'),
			(new ipAnonymizer('two'))->hash('203.0.113.7')
		);
	}

	public function testHashRefusesToRunWithoutASalt(): void {
		// An unsalted truncated hash of the IPv4 space is brute-forceable, so it is not
		// anonymization and must never be written.
		$this->assertThrows(
			fn() => (new ipAnonymizer(''))->hash('203.0.113.7'),
			\RuntimeException::class,
			'ANON_IP_SALT'
		);

		$this->assertFalse((new ipAnonymizer(''))->isConfigured());
		$this->assertTrue((new ipAnonymizer(self::SALT))->isConfigured());
	}

	public function testStoredFormsCoversBothTheRawAddressAndItsHash(): void {
		$anonymizer = new ipAnonymizer(self::SALT);

		$this->assertSame(
			['203.0.113.7', $anonymizer->hash('203.0.113.7')],
			$anonymizer->storedForms('203.0.113.7')
		);
	}

	public function testStoredFormsIsJustTheAddressWithNoSaltConfigured(): void {
		// Nothing has been anonymized without a salt, so there is no second form to match.
		$this->assertSame(['203.0.113.7'], (new ipAnonymizer(''))->storedForms('203.0.113.7'));
	}

	public function testIsAnonymizedRecognizesOnlyTheStoredHashShape(): void {
		$this->assertTrue(ipAnonymizer::isAnonymized((new ipAnonymizer(self::SALT))->hash('203.0.113.7')));
		$this->assertTrue(ipAnonymizer::isAnonymized('0123456789abcdef'));

		$this->assertFalse(ipAnonymizer::isAnonymized('203.0.113.7'));
		$this->assertFalse(ipAnonymizer::isAnonymized('2001:db8::1'));
		$this->assertFalse(ipAnonymizer::isAnonymized(''));
		$this->assertFalse(ipAnonymizer::isAnonymized('0123456789ABCDEF'), 'uppercase is not the stored form');
		$this->assertFalse(ipAnonymizer::isAnonymized('0123456789abcde'), 'too short');
		$this->assertFalse(ipAnonymizer::isAnonymized('0123456789abcdef0'), 'too long');
	}

	public function testSqlHelpersAgreeWithThePhpHash(): void {
		// The SQL builds the same value MariaDB-side; both sides must use the same length.
		$this->assertStringContains('LEFT(SHA2(CONCAT(:anon_salt, `host`), 512), 16)', ipAnonymizer::hashColumnSql('`host`'));
		$this->assertStringContains("`host` NOT REGEXP '^[0-9a-f]{16}$'", ipAnonymizer::notAnonymizedSql('`host`'));
	}
}
