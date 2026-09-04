<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\ban\banDuration;
use Kokonotsuba\ban\banEntry;
use Kokonotsuba\ban\ipPatternMatcher;

/**
 * The ban row's own questions: is it still in force, what does it block, how long was it for.
 *
 * Every one of these decides whether somebody is stopped or let through, and they all run off a
 * raw database row, so the hydration is exercised through the same path the app uses.
 */
final class BanEntryTest extends TestCase {

	private const NOW = 1800000000;

	/** @param array<string, mixed> $overrides */
	private function makeBan(array $overrides = []): banEntry {
		return banEntry::fromRow(array_merge([
			'ban_id' => 1,
			'board_uid' => 2,
			'ip_pattern' => '127.0.0.1',
			'is_wildcard' => 0,
			'visitor_token_hash' => null,
			'post_uid' => null,
			'reason' => 'Spamming',
			'public_reason' => '',
			'private_reason' => '',
			'checkpoints' => 'post,report',
			'is_warning' => 0,
			'is_mute' => 0,
			'rejects_appeals' => 0,
			'filed_at' => date('Y-m-d H:i:s', self::NOW - 3600),
			'expires_at' => date('Y-m-d H:i:s', self::NOW + 3600),
			'filed_by' => 5,
			'filed_by_username' => 'mod_a',
			'seen_at' => null,
			'seen_cookies' => null,
			'revoked_at' => null,
			'revoked_by' => null,
			'revoked_by_username' => null,
		], $overrides));
	}

	// ---- what the ban is tied to --------------------------------------------

	/**
	 * A ban carries no browser unless one was ticked, and 'no browser' has to stay null.
	 *
	 * The service only tests the tie when it is not null, so a row carrying the empty string -
	 * which is what a post from a cookie-refusing browser records - must not read back as a
	 * value. If it did, every visitor keeping no cookie would answer to it at once.
	 */
	public function testAnUntiedBanCarriesNoBrowser(): void {
		$this->assertNull($this->makeBan()->visitorTokenHash, 'an untied ban came back carrying something');
		$this->assertNull($this->makeBan(['visitor_token_hash' => null])->visitorTokenHash, 'null did not survive the row');
	}

	/** An empty string is a value, not an absence, and reads back as exactly what was stored. */
	public function testAnEmptyTieIsNotMistakenForNoTie(): void {
		$ban = $this->makeBan(['visitor_token_hash' => '']);

		$this->assertSame('', $ban->visitorTokenHash, 'an empty tie was quietly turned into something else');
		$this->assertNotNull($ban->visitorTokenHash, 'an empty tie was read as no tie at all');
	}

	public function testATiedBanKeepsItsTokenHashVerbatim(): void {
		$ban = $this->makeBan(['visitor_token_hash' => 'a3f9c1d2b4e60718']);

		$this->assertSame('a3f9c1d2b4e60718', $ban->visitorTokenHash, 'the token hash was altered in transit');
	}

	// ---- lifecycle ----------------------------------------------------------

	public function testAnUnexpiredUnrevokedBanIsActive(): void {
		$ban = $this->makeBan();

		$this->assertTrue($ban->isActive(self::NOW));
		$this->assertSame('active', $ban->statusKey(self::NOW));
	}

	public function testExpiryIsInclusiveOfTheExpiryMoment(): void {
		$ban = $this->makeBan(['expires_at' => date('Y-m-d H:i:s', self::NOW)]);

		$this->assertTrue($ban->isExpired(self::NOW));
		$this->assertFalse($ban->isActive(self::NOW));
		$this->assertSame('expired', $ban->statusKey(self::NOW));
	}

	/** A revoked ban stops applying even while its expiry is still in the future. */
	public function testRevocationBeatsAFutureExpiry(): void {
		$ban = $this->makeBan([
			'revoked_at' => date('Y-m-d H:i:s', self::NOW - 60),
			'revoked_by' => 9,
			'revoked_by_username' => 'mod_b',
		]);

		$this->assertTrue($ban->isRevoked());
		$this->assertFalse($ban->isActive(self::NOW));
		$this->assertSame('revoked', $ban->statusKey(self::NOW));
		$this->assertSame('mod_b', $ban->revokedByUsername);
	}

	public function testAPermanentBanNeverExpires(): void {
		$ban = $this->makeBan(['expires_at' => null]);

		$this->assertTrue($ban->isPermanent());
		$this->assertFalse($ban->isExpired(self::NOW));
		$this->assertTrue($ban->isActive(self::NOW + 31536000 * 20));
		$this->assertSame(null, $ban->secondsRemaining(self::NOW));
	}

	/** A warning has no expiry either, but it is not a ban that never ends. */
	public function testAWarningIsNotPermanent(): void {
		$ban = $this->makeBan(['is_warning' => 1, 'expires_at' => null, 'checkpoints' => '']);

		$this->assertTrue($ban->isWarning);
		$this->assertFalse($ban->isPermanent());
		$this->assertSame('warning', $ban->statusKey(self::NOW));
	}

	/** A mute is a real ban while it runs; it just does not survive its own expiry. */
	public function testAMuteIsAnOrdinaryBanUntilItLapses(): void {
		$mute = $this->makeBan(['is_mute' => 1]);

		$this->assertTrue($mute->isMute);
		$this->assertTrue($mute->isActive(self::NOW));
		$this->assertTrue($mute->blocks('post'));
		$this->assertSame('mute', $mute->statusKey(self::NOW));
	}

	// ---- the expiry notice --------------------------------------------------

	/**
	 * A lapsed ban is not finished with until the person it stopped has been told so.
	 *
	 * It stops nothing any more, but it is owed one interruption saying they are free again -
	 * which is what the old flat-file system did, and what makes an expiry something somebody is
	 * told about rather than something they discover by trying.
	 */
	public function testALapsedBanStillOwesItsNotice(): void {
		$ban = $this->makeBan(['expires_at' => date('Y-m-d H:i:s', self::NOW - 60)]);

		$this->assertFalse($ban->isActive(self::NOW), 'a lapsed ban was still in force');
		$this->assertTrue($ban->awaitsExpiryNotice(self::NOW), 'the notice was not owed');
	}

	public function testALapsedBanOwesItsNoticeOnlyOnce(): void {
		$ban = $this->makeBan([
			'expires_at' => date('Y-m-d H:i:s', self::NOW - 60),
			'expiry_seen_at' => date('Y-m-d H:i:s', self::NOW - 30),
		]);

		$this->assertTrue($ban->hasSeenExpiryNotice(), 'the telling was not read back off the row');
		$this->assertFalse($ban->awaitsExpiryNotice(self::NOW), 'the notice was owed a second time');
	}

	/** A ban still running has nothing to announce, and neither has a lifted one. */
	public function testABanWithNothingToAnnounceOwesNoNotice(): void {
		$this->assertFalse($this->makeBan()->awaitsExpiryNotice(self::NOW), 'a live ban owed an expiry notice');

		$revoked = $this->makeBan([
			'expires_at' => date('Y-m-d H:i:s', self::NOW - 60),
			'revoked_at' => date('Y-m-d H:i:s', self::NOW - 120),
		]);

		$this->assertFalse($revoked->awaitsExpiryNotice(self::NOW), 'a lifted ban owed an expiry notice');
	}

	/**
	 * Mutes and warnings are left out: a mute is deleted the moment it lapses, and a warning has
	 * no expiry to announce in the first place.
	 */
	public function testMutesAndWarningsOweNoExpiryNotice(): void {
		$mute = $this->makeBan(['is_mute' => 1, 'expires_at' => date('Y-m-d H:i:s', self::NOW - 60)]);
		$warning = $this->makeBan(['is_warning' => 1, 'checkpoints' => '', 'expires_at' => date('Y-m-d H:i:s', self::NOW - 60)]);

		$this->assertFalse($mute->awaitsExpiryNotice(self::NOW), 'a lapsed mute owed an expiry notice');
		$this->assertFalse($warning->awaitsExpiryNotice(self::NOW), 'a warning owed an expiry notice');
	}

	// ---- what it blocks -----------------------------------------------------

	public function testBlocksOnlyTheCheckpointsItNames(): void {
		$ban = $this->makeBan();

		$this->assertTrue($ban->blocks('post'));
		$this->assertTrue($ban->blocks('report'));
		$this->assertFalse($ban->blocks('soudane'));
		$this->assertFalse($ban->blocks('pm'));
	}

	/** A warning carries no checkpoints, so it stops nothing at all. */
	public function testAWarningBlocksNothing(): void {
		$ban = $this->makeBan(['is_warning' => 1, 'checkpoints' => '']);

		$this->assertSame([], $ban->checkpoints);
		$this->assertFalse($ban->blocks('post'));
	}

	public function testBlankCheckpointSegmentsAreDiscarded(): void {
		$ban = $this->makeBan(['checkpoints' => 'post,,report,']);

		$this->assertSame(['post', 'report'], $ban->checkpoints);
	}

	// ---- seen state ---------------------------------------------------------

	public function testSeenStateDistinguishesUnknownFromCookieless(): void {
		$never = $this->makeBan();
		$this->assertFalse($never->hasBeenSeen());
		$this->assertSame(null, $never->seenWithCookies);

		$withCookies = $this->makeBan(['seen_at' => date('Y-m-d H:i:s', self::NOW), 'seen_cookies' => 1]);
		$this->assertTrue($withCookies->hasBeenSeen());
		$this->assertTrue($withCookies->seenWithCookies);

		// 0, not null: they were seen, and their browser handed nothing back.
		$without = $this->makeBan(['seen_at' => date('Y-m-d H:i:s', self::NOW), 'seen_cookies' => 0]);
		$this->assertTrue($without->hasBeenSeen());
		$this->assertFalse($without->seenWithCookies);
	}

	// ---- appealability ------------------------------------------------------

	public function testOnlyLiveBansCanBeAppealed(): void {
		$this->assertTrue($this->makeBan()->isAppealable(self::NOW));
		$this->assertFalse($this->makeBan(['is_warning' => 1])->isAppealable(self::NOW));
		// A mute is swept away when it lapses, so an appeal would outlive what it argues with.
		$this->assertFalse($this->makeBan(['is_mute' => 1])->isAppealable(self::NOW));
		// And a live ban can refuse appeals outright, per the moderator's tick.
		$this->assertFalse($this->makeBan(['rejects_appeals' => 1])->isAppealable(self::NOW));
		$this->assertFalse($this->makeBan(['expires_at' => date('Y-m-d H:i:s', self::NOW - 1)])->isAppealable(self::NOW));
		$this->assertFalse($this->makeBan(['revoked_at' => date('Y-m-d H:i:s', self::NOW - 1)])->isAppealable(self::NOW));
	}

	/** The three reasons are distinct fields; only `reason` is ever shown to the banned party. */
	public function testTheThreeReasonsAreKeptApart(): void {
		$ban = $this->makeBan([
			'reason' => 'Spamming',
			'public_reason' => '<p>(USER WAS BANNED FOR THIS POST)</p>',
			'private_reason' => 'Same guy as last week',
		]);

		$this->assertSame('Spamming', $ban->reason);
		$this->assertSame('<p>(USER WAS BANNED FOR THIS POST)</p>', $ban->publicReason);
		$this->assertSame('Same guy as last week', $ban->privateReason);
	}

	/** A missing column reads as blank rather than tripping the hydration. */
	public function testReasonsDefaultToBlank(): void {
		$ban = $this->makeBan();

		$this->assertSame('', $ban->publicReason);
		$this->assertSame('', $ban->privateReason);
		$this->assertFalse($ban->rejectsAppeals);
	}

	// ---- durations ----------------------------------------------------------

	public function testDurationStringsParseToSeconds(): void {
		$this->assertSame(86400, banDuration::toSeconds('1d'));
		$this->assertSame(3600, banDuration::toSeconds('1h'));
		$this->assertSame(604800, banDuration::toSeconds('1w'));
		$this->assertSame(31536000, banDuration::toSeconds('1y'));
		$this->assertSame(129600, banDuration::toSeconds('1d12h'));
		$this->assertSame(129600, banDuration::toSeconds('1.5d'));
	}

	/** Unparseable input is worth nothing, which the ban form turns into "no duration given". */
	public function testUnparseableDurationsAreZero(): void {
		$this->assertSame(0, banDuration::toSeconds(''));
		$this->assertSame(0, banDuration::toSeconds('forever'));
		$this->assertSame(0, banDuration::toSeconds('0'));
	}

	// ---- pattern matching ---------------------------------------------------

	public function testWildcardDetection(): void {
		$this->assertTrue(ipPatternMatcher::isWildcard('127.0.*'));
		$this->assertFalse(ipPatternMatcher::isWildcard('127.0.0.1'));
	}

	public function testIpv4WildcardMatching(): void {
		$this->assertTrue(ipPatternMatcher::matches('127.0.0.1', '127.0.*'));
		$this->assertTrue(ipPatternMatcher::matches('127.0.5.9', '127.0.*'));
		$this->assertFalse(ipPatternMatcher::matches('127.1.0.1', '127.0.*'));
		$this->assertTrue(ipPatternMatcher::matches('127.0.0.1', '127.0.0.1'));
	}

	/** A '10.*' pattern must not swallow an unrelated v6 address that happens to be passed in. */
	public function testAnIpv4PatternDoesNotMatchIpv6(): void {
		$this->assertFalse(ipPatternMatcher::matches('2001:db8::1', '10.*'));
	}

	public function testIpv6WildcardMatching(): void {
		$this->assertTrue(ipPatternMatcher::matches('2001:db8::1', '2001:db8:*'));
		$this->assertFalse(ipPatternMatcher::matches('2002:db8::1', '2001:db8:*'));
	}

	public function testGarbageInputNeverMatches(): void {
		$this->assertFalse(ipPatternMatcher::matches('not-an-ip', '*'));
		$this->assertFalse(ipPatternMatcher::matches('', '127.0.*'));
	}
}
