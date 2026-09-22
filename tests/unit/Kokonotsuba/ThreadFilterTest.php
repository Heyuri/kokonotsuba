<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;

use function Kokonotsuba\libraries\bindThreadFilterParameters;

require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/lib_filter.php';

/**
 * The thread filters behind the overboard. The list query and the count query bind the same
 * filters, so the pager always agrees with the page.
 */
final class ThreadFilterTest extends TestCase {

	/** @return array{0: string, 1: array<string, mixed>} */
	private function build(array $filters): array {
		$query = 'SELECT COUNT(*) FROM threads t WHERE 1';
		$params = [];

		bindThreadFilterParameters($params, $query, $filters);

		return [$query, $params];
	}

	public function testBoardsAreBoundAsOneInList(): void {
		[$query, $params] = $this->build(['board' => [3, 1, 2]]);

		$this->assertMatchesRegex('/t\.boardUID IN \(:boardUID0, :boardUID1, :boardUID2\)/', $query);
		$this->assertStringNotContains(' OR ', $query);
		$this->assertSame([3, 1, 2], array_values($params));
	}

	public function testNonNumericBoardsAreDropped(): void {
		[$query, $params] = $this->build(['board' => [1, 'x', '2', null]]);

		$this->assertSame([1, 2], array_values($params));
		$this->assertStringNotContains('x', substr($query, strpos($query, 'IN')));
	}

	public function testNoBoardsMeansNoBoardClause(): void {
		[$query, $params] = $this->build(['board' => []]);

		$this->assertStringNotContains('boardUID', $query);
		$this->assertSame([], $params);
	}
}
