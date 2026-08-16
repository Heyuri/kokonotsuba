<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use InvalidArgumentException;
use Koko\Tests\Framework\TestCase;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\sqlRunner;

/** {LOGICAL_TABLE} expansion and dry-run behaviour. */
final class SqlRunnerTest extends TestCase {

	/** @var list<string> */
	private array $logged = [];

	/** Nothing here executes, so a connection that would fatal if touched is the point. */
	private function connectionStub(): databaseConnection {
		return new class extends databaseConnection {
			public function __construct() {}
		};
	}

	private function runner(bool $dryRun = true): sqlRunner {
		$this->logged = [];

		return new sqlRunner(
			$this->connectionStub(),
			['POST_TABLE' => 'posts', 'BOARD_TABLE' => 'boards'],
			$dryRun,
			function (string $statement): void {
				$this->logged[] = $statement;
			}
		);
	}

	public function testExpandsKnownPlaceholders(): void {
		$this->assertSame(
			'SELECT * FROM posts JOIN boards',
			$this->runner()->expand('SELECT * FROM {POST_TABLE} JOIN {BOARD_TABLE}')
		);
	}

	public function testLeavesNonPlaceholderBracesAlone(): void {
		// Lowercase does not match the placeholder pattern.
		$this->assertSame('{lower}', $this->runner()->expand('{lower}'));
	}

	public function testUnknownPlaceholderIsFatal(): void {
		$error = $this->assertThrows(
			fn () => $this->runner()->expand('SELECT * FROM {NOPE_TABLE}'),
			InvalidArgumentException::class
		);

		$this->assertStringContains('tables.php', $error->getMessage());
	}

	public function testDryRunPlansStatementsWithoutExecuting(): void {
		$runner = $this->runner(true);
		$runner->run('DELETE FROM {POST_TABLE}');

		// A null connection would fatal if it were touched; reaching here proves it was not.
		$this->assertSame(['DELETE FROM posts'], $runner->getPlan());
		$this->assertSame(['DELETE FROM posts'], $this->logged);
	}

	public function testTableRejectsUnknownKeys(): void {
		$this->assertThrows(
			fn () => $this->runner()->table('MISSING_TABLE'),
			InvalidArgumentException::class
		);
	}
}
