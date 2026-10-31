<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\html\getFrontAdminNavEntries;

require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/html/miscPartials.php';

/** Who may edit front page news, pages, and the site settings that need both. */
final class FrontPagePolicyTest extends TestCase {
	private function policy(array $authLevels, userRole $role): frontPagePolicy {
		return new frontPagePolicy($authLevels, $role, 1);
	}

	public function testNewsAndPagesFollowTheirOwnLevels(): void {
		$levels = ['CAN_EDIT_NEWS' => userRole::LEV_MODERATOR, 'CAN_EDIT_PAGES' => userRole::LEV_ADMIN];

		$this->assertTrue($this->policy($levels, userRole::LEV_MODERATOR)->canEditNews());
		$this->assertFalse($this->policy($levels, userRole::LEV_MODERATOR)->canEditPages());
		$this->assertFalse($this->policy($levels, userRole::LEV_JANITOR)->canEditNews());
		$this->assertTrue($this->policy($levels, userRole::LEV_ADMIN)->canEditPages());
	}

	public function testBothDefaultToAdmin(): void {
		$this->assertFalse($this->policy([], userRole::LEV_MANAGER)->canEditNews());
		$this->assertFalse($this->policy([], userRole::LEV_MANAGER)->canEditPages());
		$this->assertTrue($this->policy([], userRole::LEV_ADMIN)->canEditNews());
		$this->assertTrue($this->policy([], userRole::LEV_ADMIN)->canEditPages());
	}

	public function testSiteSettingsNeedBoth(): void {
		$levels = ['CAN_EDIT_NEWS' => userRole::LEV_MODERATOR, 'CAN_EDIT_PAGES' => userRole::LEV_ADMIN];

		$this->assertFalse($this->policy($levels, userRole::LEV_MODERATOR)->canEditSite());
		$this->assertTrue($this->policy($levels, userRole::LEV_ADMIN)->canEditSite());
	}

	public function testAnonymousVisitorsEditNothing(): void {
		$policy = $this->policy([], userRole::LEV_NONE);

		$this->assertFalse($policy->canEditNews());
		$this->assertFalse($policy->canEditPages());
	}

	/** Board and front page staff navs share this list, so each entry follows its own right. */
	public function testStaffNavEntriesFollowEachRight(): void {
		$levels = ['CAN_EDIT_NEWS' => userRole::LEV_MODERATOR, 'CAN_EDIT_PAGES' => userRole::LEV_ADMIN];
		$links = new frontPageLinks('https://example.net/');

		$moderator = getFrontAdminNavEntries($this->policy($levels, userRole::LEV_MODERATOR), $links);
		$this->assertSame(['frontNews'], array_column($moderator, 'key'));
		$this->assertSame('frontPage', $moderator[0]['group']);
		$this->assertSame('https://example.net/?mode=admin#frontAdminNews', $moderator[0]['url']);

		$admin = getFrontAdminNavEntries($this->policy($levels, userRole::LEV_ADMIN), $links);
		$this->assertSame(['frontNews', 'frontPages', 'frontLogo'], array_column($admin, 'key'));

		$this->assertSame([], getFrontAdminNavEntries($this->policy($levels, userRole::LEV_JANITOR), $links));
	}
}
