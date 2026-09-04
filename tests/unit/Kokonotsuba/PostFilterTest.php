<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;

use function Kokonotsuba\libraries\bindPostFilterParameters;

require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/lib_filter.php';

/**
 * The filters behind the manage-posts screen, and the browser one in particular.
 *
 * Staff see the first half of a post's browser token hash and can click it to gather every post
 * made with that browser. The half they see is a label; the filter has to run on the whole of
 * it, as an equality, or it would sooner or later gather somebody else's posts and present them
 * as one person's.
 */
final class PostFilterTest extends TestCase {

	/** @param array<string, mixed> $filters @return array{0: string, 1: array<string, mixed>} */
	private function build(array $filters, bool $prefixColumn = false): array {
		$query = 'SELECT * FROM posts WHERE 1=1';
		$params = [];

		bindPostFilterParameters($params, $query, $filters, $prefixColumn);

		return [$query, $params];
	}

	public function testTheBrowserFilterIsAnEqualityNotAPrefixMatch(): void {
		[$query, $params] = $this->build(['visitor_token_hash' => 'a3f9c1d2b4e60718']);

		$this->assertStringContains('visitor_token_hash = :visitor_token_hash', $query);
		$this->assertStringNotContains('visitor_token_hash LIKE', $query);
		$this->assertSame('a3f9c1d2b4e60718', $params[':visitor_token_hash']);
	}

	/** The value is bound, never spliced into the statement. */
	public function testTheBrowserFilterBindsItsValue(): void {
		[$query, $params] = $this->build(['visitor_token_hash' => "'; DROP TABLE posts; --"]);

		$this->assertStringNotContains('DROP TABLE', $query);
		$this->assertSame("'; DROP TABLE posts; --", $params[':visitor_token_hash']);
	}

	/**
	 * The label staff read is half the stored value, so a filter built from it must not silently
	 * become a prefix search. Bound as-is, it simply matches nothing.
	 */
	public function testTheDisplayedHalfIsNotTreatedAsTheWholeTokenHash(): void {
		[, $params] = $this->build(['visitor_token_hash' => 'a3f9c1d2']);

		$this->assertSame('a3f9c1d2', $params[':visitor_token_hash']);
		$this->assertNotSame('a3f9c1d2%', $params[':visitor_token_hash']);
	}

	public function testAnAbsentBrowserFilterAddsNothing(): void {
		foreach ([[], ['visitor_token_hash' => ''], ['visitor_token_hash' => null]] as $filters) {
			[$query, $params] = $this->build($filters);

			$this->assertStringNotContains('visitor_token_hash', $query, 'an empty filter still built a clause');
			$this->assertFalse(isset($params[':visitor_token_hash']), 'an empty filter still bound a value');
		}
	}

	/** Anything that is not a string is not a token hash and is ignored rather than cast. */
	public function testANonStringBrowserFilterIsIgnored(): void {
		foreach ([['visitor_token_hash' => ['a3f9c1d2b4e60718']], ['visitor_token_hash' => 12345]] as $filters) {
			[$query] = $this->build($filters);

			$this->assertStringNotContains('visitor_token_hash', $query);
		}
	}

	/** Join queries group the post columns behind 'p.'; the filter follows. */
	public function testTheBrowserFilterFollowsTheColumnPrefix(): void {
		[$query] = $this->build(['visitor_token_hash' => 'a3f9c1d2b4e60718'], true);

		$this->assertStringContains('p.visitor_token_hash = :visitor_token_hash', $query);
	}

	/** It narrows alongside the address rather than replacing it. */
	public function testTheBrowserAndAddressFiltersCoexist(): void {
		[$query, $params] = $this->build([
			'visitor_token_hash' => 'a3f9c1d2b4e60718',
			'ip_address' => '192.168.1.5',
		]);

		$this->assertStringContains('visitor_token_hash = :visitor_token_hash', $query);
		$this->assertStringContains('host LIKE :ip_like', $query);
		$this->assertSame('a3f9c1d2b4e60718', $params[':visitor_token_hash']);
	}
}
