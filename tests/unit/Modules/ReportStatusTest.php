<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\report\reportStatus;

/**
 * Unit tests for the report lifecycle enum.
 *
 * Status arrives from the database as a string, and rows are coloured by the CSS class this
 * enum hands out, so both the coercion and the class names are pinned here.
 */
final class ReportStatusTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('report/reportStatus.php');
	}

	public function testFromValueAcceptsIntsAndDatabaseStrings(): void {
		$this->assertTrue(reportStatus::fromValue(0) === reportStatus::PENDING);
		$this->assertTrue(reportStatus::fromValue(1) === reportStatus::APPROVED);
		$this->assertTrue(reportStatus::fromValue(2) === reportStatus::DISMISSED);

		// PDO hands back strings for TINYINT columns on some drivers.
		$this->assertTrue(reportStatus::fromValue('1') === reportStatus::APPROVED);
		$this->assertTrue(reportStatus::fromValue('2') === reportStatus::DISMISSED);
	}

	/** An unrecognised value must not be treated as actioned — that would hide it from the queue. */
	public function testUnknownValuesFallBackToPending(): void {
		$this->assertTrue(reportStatus::fromValue(99) === reportStatus::PENDING);
		$this->assertTrue(reportStatus::fromValue('') === reportStatus::PENDING);
		$this->assertTrue(reportStatus::fromValue(null) === reportStatus::PENDING);
	}

	public function testOnlyPendingIsPending(): void {
		$this->assertTrue(reportStatus::PENDING->isPending());
		$this->assertFalse(reportStatus::APPROVED->isPending());
		$this->assertFalse(reportStatus::DISMISSED->isPending());
	}

	public function testRowCssClassesAreDistinct(): void {
		$this->assertSame('reportRowPending', reportStatus::PENDING->rowCssClass());
		$this->assertSame('reportRowApproved', reportStatus::APPROVED->rowCssClass());
		$this->assertSame('reportRowDismissed', reportStatus::DISMISSED->rowCssClass());
	}

	/** Labels are user-facing, so they must go through the translation layer. */
	public function testLabelsResolveThroughTranslation(): void {
		$this->assertSame('report_status_pending', reportStatus::PENDING->label());
		$this->assertSame('report_status_approved', reportStatus::APPROVED->label());
		$this->assertSame('report_status_dismissed', reportStatus::DISMISSED->label());
	}
}
