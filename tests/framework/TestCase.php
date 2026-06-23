<?php

namespace Koko\Tests\Framework;

/**
 * Base class for unit tests.
 *
 * Extend this and add public methods whose names start with `test`. The runner
 * discovers each one, instantiates the class fresh per method, calls setUp()
 * before and tearDown() after, and reports the outcome.
 *
 * Assertions throw AssertionFailedException on failure (reported as FAIL); any
 * other Throwable is reported as ERROR. A test that runs every assertion
 * without throwing passes.
 *
 * There is deliberately no Composer/PHPUnit dependency here — the project ships
 * no package manager, so this is a small self-contained harness in plain PHP.
 */
abstract class TestCase {

	/** Number of assertions executed in the current test method. */
	private int $assertionCount = 0;

	/** Run before each test method. Override to set up fixtures. */
	protected function setUp(): void {}

	/** Run after each test method (even if it failed). Override to clean up. */
	protected function tearDown(): void {}

	public function getAssertionCount(): int {
		return $this->assertionCount;
	}

	public function resetAssertionCount(): void {
		$this->assertionCount = 0;
	}

	// ---- Assertions ---------------------------------------------------------

	/** Pass if $condition is strictly true. */
	protected function assertTrue($condition, string $message = ''): void {
		$this->assertionCount++;
		if ($condition !== true) {
			$this->fail($message ?: 'Failed asserting that ' . $this->export($condition) . ' is true.');
		}
	}

	/** Pass if $condition is strictly false. */
	protected function assertFalse($condition, string $message = ''): void {
		$this->assertionCount++;
		if ($condition !== false) {
			$this->fail($message ?: 'Failed asserting that ' . $this->export($condition) . ' is false.');
		}
	}

	/** Pass if $expected === $actual (type and value). */
	protected function assertSame($expected, $actual, string $message = ''): void {
		$this->assertionCount++;
		if ($expected !== $actual) {
			$this->fail($message ?: sprintf(
				"Failed asserting that two values are identical.\n      expected: %s\n      actual:   %s",
				$this->export($expected),
				$this->export($actual)
			));
		}
	}

	/** Pass if $expected == $actual (loose). Prefer assertSame where possible. */
	protected function assertEquals($expected, $actual, string $message = ''): void {
		$this->assertionCount++;
		if ($expected != $actual) {
			$this->fail($message ?: sprintf(
				"Failed asserting that two values are equal.\n      expected: %s\n      actual:   %s",
				$this->export($expected),
				$this->export($actual)
			));
		}
	}

	protected function assertNotSame($expected, $actual, string $message = ''): void {
		$this->assertionCount++;
		if ($expected === $actual) {
			$this->fail($message ?: 'Failed asserting that two values are not identical: ' . $this->export($actual));
		}
	}

	protected function assertNull($value, string $message = ''): void {
		$this->assertionCount++;
		if ($value !== null) {
			$this->fail($message ?: 'Failed asserting that ' . $this->export($value) . ' is null.');
		}
	}

	protected function assertNotNull($value, string $message = ''): void {
		$this->assertionCount++;
		if ($value === null) {
			$this->fail($message ?: 'Failed asserting that value is not null.');
		}
	}

	/** Pass if $haystack contains $needle as a substring. */
	protected function assertStringContains(string $needle, string $haystack, string $message = ''): void {
		$this->assertionCount++;
		if (!str_contains($haystack, $needle)) {
			$this->fail($message ?: sprintf(
				'Failed asserting that %s contains %s.',
				$this->export($haystack),
				$this->export($needle)
			));
		}
	}

	protected function assertStringNotContains(string $needle, string $haystack, string $message = ''): void {
		$this->assertionCount++;
		if (str_contains($haystack, $needle)) {
			$this->fail($message ?: sprintf(
				'Failed asserting that %s does not contain %s.',
				$this->export($haystack),
				$this->export($needle)
			));
		}
	}

	protected function assertMatchesRegex(string $pattern, string $subject, string $message = ''): void {
		$this->assertionCount++;
		if (!preg_match($pattern, $subject)) {
			$this->fail($message ?: sprintf(
				'Failed asserting that %s matches %s.',
				$this->export($subject),
				$pattern
			));
		}
	}

	protected function assertCount(int $expected, $countable, string $message = ''): void {
		$this->assertionCount++;
		$actual = is_countable($countable) ? count($countable) : -1;
		if ($actual !== $expected) {
			$this->fail($message ?: "Failed asserting count is $expected; got $actual.");
		}
	}

	/** Pass if $needle is an element of array $haystack (strict). */
	protected function assertContains($needle, array $haystack, string $message = ''): void {
		$this->assertionCount++;
		if (!in_array($needle, $haystack, true)) {
			$this->fail($message ?: 'Failed asserting that array contains ' . $this->export($needle) . '.');
		}
	}

	protected function assertGreaterThan($bound, $actual, string $message = ''): void {
		$this->assertionCount++;
		if (!($actual > $bound)) {
			$this->fail($message ?: "Failed asserting that {$this->export($actual)} is greater than {$this->export($bound)}.");
		}
	}

	protected function assertLessThan($bound, $actual, string $message = ''): void {
		$this->assertionCount++;
		if (!($actual < $bound)) {
			$this->fail($message ?: "Failed asserting that {$this->export($actual)} is less than {$this->export($bound)}.");
		}
	}

	protected function assertIsString($value, string $message = ''): void {
		$this->assertionCount++;
		if (!is_string($value)) {
			$this->fail($message ?: 'Failed asserting that value is a string; got ' . gettype($value) . '.');
		}
	}

	protected function assertIsArray($value, string $message = ''): void {
		$this->assertionCount++;
		if (!is_array($value)) {
			$this->fail($message ?: 'Failed asserting that value is an array; got ' . gettype($value) . '.');
		}
	}

	/**
	 * Assert that $callback throws. Optionally require a specific class and/or
	 * that the message contains a given substring. Returns the caught Throwable.
	 */
	protected function assertThrows(callable $callback, ?string $expectedClass = null, string $messageContains = ''): \Throwable {
		$this->assertionCount++;
		try {
			$callback();
		} catch (\Throwable $e) {
			if ($expectedClass !== null && !($e instanceof $expectedClass)) {
				$this->fail("Expected exception of type $expectedClass; got " . get_class($e) . '.');
			}
			if ($messageContains !== '' && !str_contains($e->getMessage(), $messageContains)) {
				$this->fail("Expected exception message to contain '$messageContains'; got '" . $e->getMessage() . "'.");
			}
			return $e;
		}
		$this->fail('Failed asserting that a Throwable was thrown.');
	}

	/** Explicitly mark the bookkeeping of an assertion that passed via custom logic. */
	protected function pass(): void {
		$this->assertionCount++;
	}

	/** Unconditionally fail the current test. */
	protected function fail(string $message): void {
		throw new AssertionFailedException($message);
	}

	// ---- Helpers ------------------------------------------------------------

	/** Render a value compactly for failure messages. */
	private function export($value): string {
		if (is_string($value)) {
			$shown = mb_strlen($value) > 120 ? mb_substr($value, 0, 117) . '...' : $value;
			return "'" . str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], $shown) . "'";
		}
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		if (is_null($value)) {
			return 'null';
		}
		if (is_array($value)) {
			return 'array(' . count($value) . ')';
		}
		if (is_object($value)) {
			return get_class($value);
		}
		return (string)$value;
	}
}
