<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\board\overboardBoardFilter;

/**
 * The reader's overboard board selection, as stored in the blacklist cookie.
 *
 * The cookie is the reader's own input, so an unreadable one has to fall back to showing
 * everything, and a board listed after the cookie was written has to appear on its own.
 */
final class OverboardBoardFilterTest extends TestCase {

	public function testNoCookieShowsEveryListedBoard(): void {
		$filter = overboardBoardFilter::fromCookie(null, [3, 1, 2]);

		$this->assertSame([1, 2, 3], $filter->allowedBoards());
		$this->assertTrue($filter->isUnfiltered());
	}

	public function testBlacklistedBoardsAreLeftOut(): void {
		$filter = overboardBoardFilter::fromCookie('[2]', [1, 2, 3]);

		$this->assertSame([1, 3], $filter->allowedBoards());
		$this->assertSame([2], $filter->blacklistedBoards());
		$this->assertFalse($filter->isUnfiltered());
	}

	public function testMalformedCookieIsReadAsNoFilter(): void {
		foreach (['', 'not json', '{"a":1', '"2"', '5', '[[2]]', '["x", null]'] as $raw) {
			$filter = overboardBoardFilter::fromCookie($raw, [1, 2]);
			$this->assertSame([1, 2], $filter->allowedBoards(), "cookie: $raw");
		}
	}

	/** The cookie stores a blacklist so a board listed later shows up without a new form submit. */
	public function testANewlyListedBoardIsShownByAnOldCookie(): void {
		$filter = overboardBoardFilter::fromCookie('[2]', [1, 2, 3, 4]);

		$this->assertSame([1, 3, 4], $filter->allowedBoards());
	}

	/** An entry for a board that is no longer listed is harmless and does not count as filtering. */
	public function testAStaleBlacklistEntryDoesNotCountAsAFilter(): void {
		$filter = overboardBoardFilter::fromCookie('[9]', [1, 2]);

		$this->assertSame([1, 2], $filter->allowedBoards());
		$this->assertTrue($filter->isUnfiltered());
	}

	public function testNumericStringsAreAcceptedAndDeduplicated(): void {
		$this->assertSame([1, 2], overboardBoardFilter::parseCookie('["2", 1, "1", 2.0, "abc"]'));
	}

	public function testSelectionRoundTripsThroughTheCookie(): void {
		$listed = [1, 2, 3];
		$written = overboardBoardFilter::fromSelection(['1', '3'], $listed);

		$this->assertSame('[2]', $written->toCookieValue());

		$read = overboardBoardFilter::fromCookie($written->toCookieValue(), $listed);
		$this->assertSame([1, 3], $read->allowedBoards());
	}

	public function testSelectingNothingHidesEverything(): void {
		$filter = overboardBoardFilter::fromSelection([], [1, 2]);

		$this->assertSame([], $filter->allowedBoards());
		$this->assertSame('[1,2]', $filter->toCookieValue());
	}

	public function testSelectingAnUnlistedBoardIsIgnored(): void {
		$filter = overboardBoardFilter::fromSelection([1, 99], [1, 2]);

		$this->assertSame([1], $filter->allowedBoards());
		$this->assertSame([2], $filter->blacklistedBoards());
	}

	/** The key names the boards shown, whatever order they were listed in or hidden from. */
	public function testKeyIsCanonical(): void {
		$a = overboardBoardFilter::fromCookie('[2]', [3, 1, 2]);
		$b = overboardBoardFilter::fromCookie('[2, 9]', [1, 2, 3]);

		$this->assertSame('1,3', $a->key());
		$this->assertSame($a->key(), $b->key());
		$this->assertSame('', overboardBoardFilter::fromSelection([], [1])->key());
	}
}
