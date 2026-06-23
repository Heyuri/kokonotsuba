<?php

namespace Koko\Tests\Unit\Puchiko;

use Koko\Tests\Framework\TestCase;

use function Puchiko\array\array_equals;
use function Puchiko\array\find_row_by_key_value;

/**
 * Unit tests for the Puchiko\array helpers.
 */
final class ArrayHelpersTest extends TestCase {

	public function testArrayEqualsIgnoresOrder(): void {
		$this->assertTrue(array_equals([1, 2, 3], [3, 2, 1]));
		$this->assertTrue(array_equals([], []));
		$this->assertTrue(array_equals(['a', 'b'], ['b', 'a']));
	}

	public function testArrayEqualsDetectsDifference(): void {
		$this->assertFalse(array_equals([1, 2], [1, 2, 3]));
		$this->assertFalse(array_equals([1, 2, 3], [1, 2, 4]));
	}

	public function testFindRowByKeyValueReturnsMatch(): void {
		$rows = [
			['id' => 1, 'name' => 'alpha'],
			['id' => 2, 'name' => 'beta'],
		];
		$this->assertSame(['id' => 2, 'name' => 'beta'], find_row_by_key_value($rows, 'id', 2));
	}

	public function testFindRowByKeyValueReturnsNullWhenAbsent(): void {
		$rows = [['id' => 1], ['id' => 2]];
		$this->assertNull(find_row_by_key_value($rows, 'id', 99));
		$this->assertNull(find_row_by_key_value($rows, 'missing', 1));
	}

	public function testFindRowByKeyValueIsStrict(): void {
		// String "2" must not match integer 2.
		$rows = [['id' => 2]];
		$this->assertNull(find_row_by_key_value($rows, 'id', '2'));
	}

	public function testFindRowByKeyValueSkipsNonArrayRows(): void {
		$rows = ['not-an-array', 42, ['id' => 7]];
		$this->assertSame(['id' => 7], find_row_by_key_value($rows, 'id', 7));
	}
}
