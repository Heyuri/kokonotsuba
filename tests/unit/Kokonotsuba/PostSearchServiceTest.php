<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\postSearchService;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for postSearchService's pure input-processing helpers:
 * FULLTEXT sanitization, boolean-query compilation, tripcode extraction and
 * field whitelisting.
 *
 * These methods are private and dependency-free (they never touch the
 * repository), so the service is built with newInstanceWithoutConstructor() and
 * the methods are invoked via reflection — no database or DI container needed.
 */
final class PostSearchServiceTest extends TestCase {

	private postSearchService $service;
	private ReflectionClass $ref;

	protected function setUp(): void {
		$this->ref = new ReflectionClass(postSearchService::class);
		$this->service = $this->ref->newInstanceWithoutConstructor();
	}

	/** Invoke a private/protected method on the service under test. */
	private function call(string $method, ...$args) {
		$m = $this->ref->getMethod($method);
		$m->setAccessible(true);
		return $m->invoke($this->service, ...$args);
	}

	// ---- sanitizeFulltextInput ---------------------------------------------

	public function testSanitizeStripsBooleanOperators(): void {
		$out = $this->call('sanitizeFulltextInput', '+foo -bar >baz <qux (a) ~b *c "d" @e');
		// None of the MySQL boolean operators may survive.
		foreach (['+', '-', '>', '<', '(', ')', '~', '*', '"', '@'] as $op) {
			$this->assertStringNotContains($op, $out);
		}
		$this->assertSame('foo bar baz qux a b c d e', $out);
	}

	public function testSanitizeCollapsesWhitespaceAndTrims(): void {
		$out = $this->call('sanitizeFulltextInput', "  hello\t\n   world  ");
		$this->assertSame('hello world', $out);
	}

	public function testSanitizeKeepsUnicodeLettersAndNumbers(): void {
		$out = $this->call('sanitizeFulltextInput', '日本語 café 123 Привет');
		$this->assertSame('日本語 café 123 Привет', $out);
	}

	public function testSanitizeEmptyInput(): void {
		$this->assertSame('', $this->call('sanitizeFulltextInput', ''));
		$this->assertSame('', $this->call('sanitizeFulltextInput', '   '));
		$this->assertSame('', $this->call('sanitizeFulltextInput', '+++---***'));
	}

	// ---- parseToBooleanFulltext --------------------------------------------

	public function testParsePrefixWildcardByDefault(): void {
		$out = $this->call('parseToBooleanFulltext', 'hello world', false, [], 3);
		$this->assertSame('+hello* +world*', $out);
	}

	public function testParseWholeWordDropsWildcard(): void {
		$out = $this->call('parseToBooleanFulltext', 'hello world', true, [], 3);
		$this->assertSame('+hello +world', $out);
	}

	public function testParseDropsShortWords(): void {
		// Default minimum length is 3, so "a" and "to" are dropped.
		$out = $this->call('parseToBooleanFulltext', 'a to the cat', false, [], 3);
		$this->assertSame('+the* +cat*', $out);
	}

	public function testParseDropsStopwordsCaseInsensitive(): void {
		// Sequential (list) stopwords are lowercased into a lookup internally.
		$out = $this->call('parseToBooleanFulltext', 'The Quick Fox', false, ['the', 'fox'], 3);
		$this->assertSame('+Quick*', $out);
	}

	public function testParseAcceptsStopwordLookupMap(): void {
		// An associative lookup map (word => anything) is used as-is.
		$out = $this->call('parseToBooleanFulltext', 'the quick fox', false, ['the' => true, 'fox' => true], 3);
		$this->assertSame('+quick*', $out);
	}

	public function testParseEmptyYieldsEmptyString(): void {
		$this->assertSame('', $this->call('parseToBooleanFulltext', '', false, [], 3));
		$this->assertSame('', $this->call('parseToBooleanFulltext', '!!! @@@ ---', false, [], 3));
	}

	public function testParseOutputIsBooleanModeSafe(): void {
		// After compilation the string must contain only required (+) tokens made
		// of letters/numbers with an optional trailing wildcard — nothing that
		// would raise a syntax error in MySQL BOOLEAN MODE.
		$out = $this->call('parseToBooleanFulltext', 'don\'t (stop) me~now +please', false, [], 3);
		$this->assertMatchesRegex('/^(\+[\p{L}\p{N}]+\*?(\s\+[\p{L}\p{N}]+\*?)*)?$/u', $out);
	}

	public function testParseOrModeDropsRequiredPrefix(): void {
		// OR mode leaves terms optional, so any of them may match.
		$out = $this->call('parseToBooleanFulltext', 'hello world', false, [], 3, 'or');
		$this->assertSame('hello* world*', $out);
	}

	public function testParseAndModeIsDefault(): void {
		// Omitting the mode (or passing 'and') keeps every term required.
		$out = $this->call('parseToBooleanFulltext', 'hello world', false, [], 3, 'and');
		$this->assertSame('+hello* +world*', $out);
	}

	public function testParseExcludesLeadingMinusInAndMode(): void {
		// A leading '-' marks the word for exclusion; other words stay required.
		$out = $this->call('parseToBooleanFulltext', 'cat -dog', false, [], 3, 'and');
		$this->assertSame('+cat* -dog*', $out);
	}

	public function testParseExcludesLeadingMinusInOrMode(): void {
		// Exclusion applies in OR mode too: the non-excluded term is optional,
		// the '-' term is still forbidden.
		$out = $this->call('parseToBooleanFulltext', 'cat -dog', false, [], 3, 'or');
		$this->assertSame('cat* -dog*', $out);
	}

	public function testParseExcludeHonoursWholeWord(): void {
		// Whole-word matching drops the trailing wildcard for excluded terms as well.
		$out = $this->call('parseToBooleanFulltext', 'cat -dog', true, [], 3, 'and');
		$this->assertSame('+cat -dog', $out);
	}

	// ---- extractTripcodeCandidate ------------------------------------------

	public function testExtractTripcodeAfterName(): void {
		$this->assertSame('ViBjFlRv5.', $this->call('extractTripcodeCandidate', 'test◆ViBjFlRv5.'));
	}

	public function testExtractTripcodeBareMarker(): void {
		$this->assertSame('ViBjFlRv5.', $this->call('extractTripcodeCandidate', '◆ViBjFlRv5.'));
	}

	public function testExtractSecureTripcodeMarker(): void {
		$this->assertSame('abcDEF123', $this->call('extractTripcodeCandidate', 'name★abcDEF123'));
	}

	public function testExtractNoMarkerReturnsEmpty(): void {
		$this->assertSame('', $this->call('extractTripcodeCandidate', 'Anonymous'));
		// The legacy '!' posting marker is intentionally NOT treated as a trip marker.
		$this->assertSame('', $this->call('extractTripcodeCandidate', 'user!secret'));
	}

	public function testExtractTripcodeTrimsSurroundingSpace(): void {
		$this->assertSame('ViBjFlRv5.', $this->call('extractTripcodeCandidate', '  test ◆ ViBjFlRv5. '));
	}

	// ---- sanitizeFields -----------------------------------------------------

	public function testSanitizeFieldsDropsUnknownKeys(): void {
		$out = $this->call('sanitizeFields', [
			'com'     => 'hello',
			'name'    => 'bob',
			'evil'    => 'DROP TABLE',
			'boardUID' => 5,
		]);
		$this->assertSame(['com' => 'hello', 'name' => 'bob'], $out);
	}

	public function testSanitizeFieldsDropsEmptyValues(): void {
		$out = $this->call('sanitizeFields', [
			'com'  => '',
			'name' => 'bob',
			'sub'  => '0', // '0' is empty() in PHP and is intentionally dropped
		]);
		$this->assertSame(['name' => 'bob'], $out);
	}

	public function testSanitizeFieldsKeepsAllAllowedKeys(): void {
		$input = [
			'general'   => 'g', 'com' => 'c', 'name' => 'n', 'email' => 'e',
			'sub'       => 's', 'no' => '1', 'file_name' => 'f',
			'tag'       => 't',
		];
		$out = $this->call('sanitizeFields', $input);
		$this->assertSame($input, $out);
	}

	/** The timestamp is range-compared via the date arguments, never passed as a field. */
	public function testSanitizeFieldsDropsRoot(): void {
		$out = $this->call('sanitizeFields', ['name' => 'n', 'root' => '2026-07-01 00:00:00']);
		$this->assertSame(['name' => 'n'], $out);
	}

	// ---- normalizeSqlDateTime ----------------------------------------------

	public function testNormalizeSqlDateTimeAcceptsExactFormat(): void {
		$this->assertSame('2026-07-01 00:00:00', $this->call('normalizeSqlDateTime', '2026-07-01 00:00:00'));
		$this->assertSame('2026-07-01 13:45:09', $this->call('normalizeSqlDateTime', '  2026-07-01 13:45:09  '));
	}

	public function testNormalizeSqlDateTimeRejectsEmptyAndNull(): void {
		$this->assertSame(null, $this->call('normalizeSqlDateTime', null));
		$this->assertSame(null, $this->call('normalizeSqlDateTime', ''));
		$this->assertSame(null, $this->call('normalizeSqlDateTime', '   '));
	}

	public function testNormalizeSqlDateTimeRejectsMalformedValues(): void {
		// date only, impossible day, wrong separator, and injected trailing text
		$this->assertSame(null, $this->call('normalizeSqlDateTime', '2026-07-01'));
		$this->assertSame(null, $this->call('normalizeSqlDateTime', '2026-02-31 00:00:00'));
		$this->assertSame(null, $this->call('normalizeSqlDateTime', '2026/07/01 00:00:00'));
		$this->assertSame(null, $this->call('normalizeSqlDateTime', "2026-07-01 00:00:00' OR 1=1"));
	}

	// ---- buildDateRangeFields ----------------------------------------------

	public function testBuildDateRangeFieldsMapsBothBounds(): void {
		$out = $this->call('buildDateRangeFields', '2026-07-01 00:00:00', '2026-08-01 00:00:00');
		$this->assertSame([
			'root_after'  => '2026-07-01 00:00:00',
			'root_before' => '2026-08-01 00:00:00',
		], $out);
	}

	public function testBuildDateRangeFieldsAllowsOpenEndedRange(): void {
		$this->assertSame(
			['root_after' => '2026-07-01 00:00:00'],
			$this->call('buildDateRangeFields', '2026-07-01 00:00:00', null)
		);
		$this->assertSame(
			['root_before' => '2026-08-01 00:00:00'],
			$this->call('buildDateRangeFields', null, '2026-08-01 00:00:00')
		);
		$this->assertSame([], $this->call('buildDateRangeFields', null, null));
	}

	/** A backwards range would match nothing, so the bounds are swapped instead. */
	public function testBuildDateRangeFieldsSwapsReversedBounds(): void {
		$out = $this->call('buildDateRangeFields', '2026-08-01 00:00:00', '2026-07-01 00:00:00');
		$this->assertSame([
			'root_after'  => '2026-07-01 00:00:00',
			'root_before' => '2026-08-01 00:00:00',
		], $out);
	}

	public function testBuildDateRangeFieldsDropsMalformedBound(): void {
		$out = $this->call('buildDateRangeFields', 'tomorrow', '2026-08-01 00:00:00');
		$this->assertSame(['root_before' => '2026-08-01 00:00:00'], $out);
	}
}
