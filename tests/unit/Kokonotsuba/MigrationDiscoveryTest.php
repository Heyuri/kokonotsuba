<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\migrations\discoveredMigration;

/** Filename parsing and ordering — the part of the runner that needs no database. */
final class MigrationDiscoveryTest extends TestCase {

	public function testParsesVersionAndName(): void {
		$migration = discoveredMigration::fromPath('core', '/x/migrations/20260815_000001_baseline.php');

		$this->assertNotNull($migration);
		$this->assertSame('20260815_000001', $migration->version);
		$this->assertSame('baseline', $migration->name);
		$this->assertSame('core', $migration->namespace);
	}

	public function testKeepsUnderscoresInTheName(): void {
		$migration = discoveredMigration::fromPath('core', '/x/20260820_141500_add_post_flags.php');

		$this->assertSame('add_post_flags', $migration->name);
	}

	public function testRejectsFilesWithoutATimestamp(): void {
		$this->assertNull(discoveredMigration::fromPath('core', '/x/README.php'));
		$this->assertNull(discoveredMigration::fromPath('core', '/x/baseline.php'));
		$this->assertNull(discoveredMigration::fromPath('core', '/x/2026_01_baseline.php'));
	}

	public function testIdIncludesTheNamespace(): void {
		$migration = discoveredMigration::fromPath('module:soudane', '/x/20260101_000000_votes.php');

		$this->assertSame('module:soudane/20260101_000000_votes', $migration->id());
	}

	public function testVersionsSortChronologicallyAsStrings(): void {
		// The runner orders by ksort on the version string; zero-padding must make that correct.
		$versions = ['20260901_000000', '20260815_000001', '20261001_120000', '20260815_000002'];
		sort($versions, SORT_STRING);

		$this->assertSame(
			['20260815_000001', '20260815_000002', '20260901_000000', '20261001_120000'],
			$versions
		);
	}
}
