<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\userRole;
use Kokonotsuba\Modules\report\reportPolicy;

/**
 * Unit tests for the report queue's permission policy.
 *
 * The shipped defaults deliberately split viewing/approving from dismissing/clearing, so most
 * of these pin down what a janitor can and cannot do.
 */
final class ReportPolicyTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('report/reportPolicy.php');
	}

	/** The AuthLevels entries this module ships with in global/globalconfig.php. */
	private function shippedAuthLevels(): array {
		return [
			'CAN_VIEW_REPORTS' => userRole::LEV_JANITOR,
			'CAN_APPROVE_REPORT' => userRole::LEV_JANITOR,
			'CAN_DISMISS_REPORT' => userRole::LEV_MODERATOR,
			'CAN_CLEAR_POST_REPORTS' => userRole::LEV_MODERATOR,
			'CAN_DELETE_POST' => userRole::LEV_JANITOR,
			'CAN_VIEW_IP_ADDRESSES' => userRole::LEV_MODERATOR,
		];
	}

	public function testJanitorCanViewAndApproveButNotDismissOrClear(): void {
		$policy = new reportPolicy($this->shippedAuthLevels(), userRole::LEV_JANITOR);

		$this->assertTrue($policy->canView());
		$this->assertTrue($policy->canApprove());
		$this->assertFalse($policy->canDismiss());
		$this->assertFalse($policy->canClearPostReports());
	}

	public function testModeratorCanDoEverything(): void {
		$policy = new reportPolicy($this->shippedAuthLevels(), userRole::LEV_MODERATOR);

		$this->assertTrue($policy->canView());
		$this->assertTrue($policy->canApprove());
		$this->assertTrue($policy->canDismiss());
		$this->assertTrue($policy->canClearPostReports());
	}

	public function testPlainUserIsLockedOut(): void {
		$policy = new reportPolicy($this->shippedAuthLevels(), userRole::LEV_USER);

		$this->assertFalse($policy->canView());
		$this->assertFalse($policy->canApprove());
		$this->assertFalse($policy->canDismiss());
		$this->assertFalse($policy->canClearPostReports());
	}

	/** Approving deletes the reported post, so deletion rights are required on top. */
	public function testApprovingAlsoRequiresPostDeletionRights(): void {
		$authLevels = $this->shippedAuthLevels();
		$authLevels['CAN_DELETE_POST'] = userRole::LEV_ADMIN;

		$this->assertFalse((new reportPolicy($authLevels, userRole::LEV_JANITOR))->canApprove());
		$this->assertFalse((new reportPolicy($authLevels, userRole::LEV_MODERATOR))->canApprove());
		$this->assertTrue((new reportPolicy($authLevels, userRole::LEV_ADMIN))->canApprove());
	}

	public function testRolesAreConfigurable(): void {
		$authLevels = $this->shippedAuthLevels();
		$authLevels['CAN_DISMISS_REPORT'] = userRole::LEV_ADMIN;

		$this->assertFalse((new reportPolicy($authLevels, userRole::LEV_MODERATOR))->canDismiss());
		$this->assertTrue((new reportPolicy($authLevels, userRole::LEV_ADMIN))->canDismiss());
	}

	public function testMissingAuthLevelsFallBackToShippedDefaults(): void {
		$policy = new reportPolicy([], userRole::LEV_JANITOR);

		$this->assertTrue($policy->canView());
		$this->assertTrue($policy->canApprove());
		$this->assertFalse($policy->canDismiss());
	}

	public function testIpVisibilityFollowsCanViewIpAddresses(): void {
		$authLevels = $this->shippedAuthLevels();

		$this->assertFalse((new reportPolicy($authLevels, userRole::LEV_JANITOR))->canViewIpAddresses());
		$this->assertTrue((new reportPolicy($authLevels, userRole::LEV_MODERATOR))->canViewIpAddresses());
	}
}
