<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\ban\ipPatternMatcher;

/**
 * How far a range ban reaches.
 *
 * Every test here asks the same question from one side or the other: can this pattern reach an
 * address its author did not write down? A range ban is the widest instrument the ban system
 * has, and the cost of it reaching too far is not a nuisance - it is somebody who did nothing
 * being told they are banned, with no way to tell that it was a stray character in a pattern.
 *
 * So the matcher takes the narrow reading of '*' and these pin it: trailing means the rest,
 * anywhere else means exactly one segment and the rest still has to line up.
 */
final class IpPatternMatcherTest extends TestCase {

	/** @param list<string> $ips */
	private function assertCatchesNone(string $pattern, array $ips, string $message): void {
		foreach ($ips as $ip) {
			$this->assertFalse(
				ipPatternMatcher::matches($ip, $pattern),
				"{$message}: {$pattern} caught {$ip}"
			);
		}
	}

	/** @param list<string> $ips */
	private function assertCatchesAll(string $pattern, array $ips, string $message): void {
		foreach ($ips as $ip) {
			$this->assertTrue(
				ipPatternMatcher::matches($ip, $pattern),
				"{$message}: {$pattern} let {$ip} through"
			);
		}
	}

	// ─── The regression that started this ─────────────────────────

	/**
	 * A stray leading '*' used to end the comparison then and there, so a ban written against
	 * one address matched every IPv6 address in existence. It has to mean one hextet.
	 */
	public function testALeadingWildcardDoesNotBanTheWholeInternet(): void {
		$this->assertCatchesNone('*:db8::1', [
			'::1',
			'2001:dead::1',
			'2600:1f18::4',
			'fe80::1',
			'2001:db8::2',
		], 'a one-address pattern reached past its own shape');

		$this->assertCatchesAll('*:db8::1', [
			'2001:db8::1',
			'fe80:db8::1',
		], 'the hextet the wildcard stands for stopped matching');
	}

	/** A wildcard in the middle is one hextet: everything after it is still compared. */
	public function testAMiddleWildcardStillComparesTheTail(): void {
		$this->assertCatchesAll('2001:*:3::1', ['2001:dead:3::1'], 'the pattern stopped matching its own range');

		$this->assertCatchesNone('2001:*:3::1', [
			'2001:dead::1',
			'2001:dead:4::1',
			'2001:dead:3::2',
		], 'the tail after the wildcard was never checked');
	}

	/** The same rule on the IPv4 side: one octet, not the rest of the address. */
	public function testAMiddleWildcardIsOneOctet(): void {
		$this->assertCatchesAll('1.2.*.4', ['1.2.3.4', '1.2.30.4'], 'the wildcard octet stopped matching');

		$this->assertCatchesNone('1.2.*.4', [
			'1.2.3.9',
			'1.2.3.40',
			'9.2.3.4',
		], 'a middle wildcard stretched across the address');
	}

	// ─── Prefix confusion ─────────────────────────────────────────

	/**
	 * The classic way a range ban goes wrong: '192.168.1.*' reading as a string prefix and
	 * taking 192.168.10.x through 192.168.19.x with it - ten times the range, all of it
	 * strangers.
	 */
	public function testAWholeSegmentWildcardDoesNotLeakIntoNeighbouringRanges(): void {
		$this->assertCatchesAll('192.168.1.*', [
			'192.168.1.0',
			'192.168.1.5',
			'192.168.1.255',
		], 'the banned /24 stopped matching');

		$this->assertCatchesNone('192.168.1.*', [
			'192.168.10.5',
			'192.168.11.5',
			'192.168.19.200',
			'192.168.100.1',
			'192.168.2.5',
		], 'a /24 ban leaked into its neighbours');
	}

	public function testAShortPatternDoesNotLeakIntoLongerLeadingOctets(): void {
		$this->assertCatchesAll('1.*', ['1.2.3.4', '1.20.3.4'], 'the banned /8 stopped matching');

		$this->assertCatchesNone('1.*', [
			'11.2.3.4',
			'10.0.0.1',
			'100.2.3.4',
			'21.2.3.4',
		], 'a /8 ban leaked into addresses merely starting with the same digit');
	}

	public function testAHextetPrefixDoesNotLeakIntoLongerHextets(): void {
		$this->assertCatchesAll('2001:db8:*', ['2001:db8::1', '2001:db8:1::9'], 'the banned range stopped matching');

		$this->assertCatchesNone('2001:db8:*', [
			'2001:db80::1',
			'2001:dead::1',
			'2002:db8::1',
		], 'an IPv6 range ban leaked into its neighbours');
	}

	// ─── Nothing at all ───────────────────────────────────────────

	/** A bare '*' is not a licence to ban everyone; it names no address family and matches none. */
	public function testABareWildcardMatchesNothing(): void {
		$this->assertCatchesNone('*', [
			'1.2.3.4',
			'192.168.1.1',
			'2001:db8::1',
			'::1',
		], 'a bare wildcard banned the world');
	}

	public function testAnEmptyPatternMatchesNothing(): void {
		$this->assertCatchesNone('', ['1.2.3.4', '2001:db8::1'], 'an empty pattern matched');
	}

	/** Whatever is in the host column, a value that is not an address matches nothing. */
	public function testSomethingThatIsNotAnAddressNeverMatches(): void {
		foreach (['', 'not-an-ip', '1.2.3', '1.2.3.4.5', '999.1.1.1', 'localhost', '../../etc'] as $notAnIp) {
			foreach (['*.*.*.*', '1.2.3.*', '2001:db8:*', '1.2.3.4'] as $pattern) {
				$this->assertFalse(
					ipPatternMatcher::matches($notAnIp, $pattern),
					"'{$notAnIp}' matched {$pattern}"
				);
			}
		}
	}

	// ─── The families do not mix ──────────────────────────────────

	public function testAnIpv4PatternDoesNotReachIpv6(): void {
		$this->assertCatchesNone('1.2.3.*', ['2001:db8::1', '::1', '::ffff:1.2.3.4'], 'a v4 range caught a v6 address');
		$this->assertCatchesNone('*.*.*.*', ['2001:db8::1', '::1'], 'a v4 catch-all caught a v6 address');
	}

	public function testAnIpv6PatternDoesNotReachIpv4(): void {
		$this->assertCatchesNone('2001:db8:*', ['1.2.3.4', '192.168.1.1'], 'a v6 range caught a v4 address');
		$this->assertCatchesNone('*:*', ['1.2.3.4'], 'a v6 catch-all caught a v4 address');
	}

	// ─── Same address, written differently ────────────────────────

	/** A visitor is not caught or released by how their address happens to be spelled. */
	public function testCompressedAndExpandedFormsAreTheSameAddress(): void {
		foreach (['2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001'] as $spelling) {
			$this->assertTrue(ipPatternMatcher::matches($spelling, '2001:db8:*'), "{$spelling} was not matched");
			$this->assertFalse(ipPatternMatcher::matches($spelling, '2001:dead:*'), "{$spelling} was wrongly matched");
		}
	}

	public function testHextetCaseIsNotWhatDecides(): void {
		$this->assertTrue(ipPatternMatcher::matches('2001:db8::1', '2001:DB8:*'), 'an uppercase pattern stopped matching');
		$this->assertTrue(ipPatternMatcher::matches('2001:DB8::1', '2001:db8:*'), 'an uppercase address stopped matching');
	}

	// ─── Exact patterns ───────────────────────────────────────────

	/**
	 * Exact bans are looked up by index and do not come through here, but the equality
	 * short-circuit still has to be exact when they do.
	 */
	public function testAnExactPatternMatchesOnlyItself(): void {
		$this->assertTrue(ipPatternMatcher::matches('1.2.3.4', '1.2.3.4'), 'an address did not match itself');

		$this->assertCatchesNone('1.2.3.4', [
			'1.2.3.40',
			'1.2.3.5',
			'11.2.3.4',
		], 'an exact ban caught a neighbour');
	}

	public function testIsWildcardOnlyCountsAStar(): void {
		$this->assertTrue(ipPatternMatcher::isWildcard('1.2.3.*'), 'a wildcard was not recognised');
		$this->assertTrue(ipPatternMatcher::isWildcard('2001:db8:*'), 'a v6 wildcard was not recognised');
		$this->assertFalse(ipPatternMatcher::isWildcard('1.2.3.4'), 'an exact address was taken for a wildcard');
		$this->assertFalse(ipPatternMatcher::isWildcard('2001:db8::1'), 'an exact v6 address was taken for a wildcard');
	}

	// ─── A known looseness, pinned so it cannot spread ────────────

	/**
	 * A '*' that finishes a partial segment still swallows the rest, so '1.2.3*' reaches the
	 * whole of 1.2.30.x-1.2.39.x as well as 1.2.3.x. Narrowing it would void bans already
	 * written that way rather than tighten them, so it stands - but it is pinned here so it
	 * cannot quietly widen further, and it is why whole-segment patterns are the ones to write.
	 */
	public function testAPartialSegmentWildcardStillReachesTheRest(): void {
		$this->assertCatchesAll('1.2.3*', ['1.2.3.4', '1.2.30.4'], 'the documented reach changed');
		$this->assertCatchesNone('1.2.3*', ['1.2.4.4', '1.2.40.4'], 'a partial segment reached past its own digits');
	}
}
