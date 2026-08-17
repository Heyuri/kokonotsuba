<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\configService;
use Kokonotsuba\renderers\widgetMenuPolicy;

/**
 * The toggles behind the post and attachment menus.
 *
 * The policy is what every menu entry passes through, so the cases that matter are the defaults:
 * an entry nobody configured has to keep showing, or enabling a module would stop being enough to
 * see its options.
 */
class WidgetMenuPolicyTest extends TestCase {

	private function entry(string $action): array {
		return ['href' => '#', 'action' => $action, 'label' => $action, 'subMenu' => '', 'params' => []];
	}

	private function policy(array $post = [], array $attachment = []): widgetMenuPolicy {
		return new widgetMenuPolicy([
			widgetMenuPolicy::MENU_POST => $post,
			widgetMenuPolicy::MENU_ATTACHMENT => $attachment,
		]);
	}

	// ---- defaults -----------------------------------------------------------

	/** A module's new entry works before anyone declares a toggle for it. */
	public function testUndeclaredActionsAreEnabled(): void {
		$policy = $this->policy();

		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'somethingNew'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'somethingNew'));
	}

	/** An entry with no action can't be named by a toggle, so it is left alone. */
	public function testEntriesWithoutAnActionAreKept(): void {
		$policy = $this->policy(['' => false]);

		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_POST, ''));
		$this->assertCount(1, $policy->filter(widgetMenuPolicy::MENU_POST, [$this->entry('')]));
	}

	// ---- toggling -----------------------------------------------------------

	public function testDisabledActionsAreDropped(): void {
		$policy = $this->policy(['delete' => false, 'ban' => true]);

		$kept = $policy->filter(widgetMenuPolicy::MENU_POST, [
			$this->entry('delete'),
			$this->entry('ban'),
			$this->entry('warn'),
		]);

		$this->assertSame(['ban', 'warn'], array_column($kept, 'action'));
	}

	/** The two menus are separate namespaces: same action name, different toggles. */
	public function testMenusDoNotShareToggles(): void {
		$policy = $this->policy(['hide' => false], ['hide' => true]);

		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'hide'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'hide'));
	}

	/** Config values arrive from a form, so a stored 0 or '' has to read as off. */
	public function testFalsyValuesDisable(): void {
		$policy = $this->policy(['delete' => 0, 'mute' => '', 'ban' => '0', 'warn' => 1]);

		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'delete'));
		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'mute'));
		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'ban'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'warn'));
	}

	public function testFilteringRenumbersTheEntries(): void {
		$kept = $this->policy(['delete' => false])
			->filter(widgetMenuPolicy::MENU_POST, [$this->entry('delete'), $this->entry('ban')]);

		$this->assertSame([0], array_keys($kept));
	}

	// ---- reading the config -------------------------------------------------

	/** A module declares the entries it adds in its own namespace. */
	public function testTogglesAreReadFromModuleNamespaces(): void {
		$policy = widgetMenuPolicy::fromConfig(['modules' => [
			'adminDel' => ['PostMenu' => ['delete' => false], 'AttachmentMenu' => ['deleteFile' => true]],
			'adminBan' => ['PostMenu' => ['ban' => true]],
		]]);

		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'delete'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'ban'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'deleteFile'));
	}

	/** fileBan and perceptualBan both add 'BanFile' - one of them saying no is enough. */
	public function testAModuleTurningAnActionOffWinsOverOneLeavingItOn(): void {
		$policy = widgetMenuPolicy::fromConfig(['modules' => [
			'fileBan' => ['AttachmentMenu' => ['BanFile' => true]],
			'perceptualBan' => ['AttachmentMenu' => ['BanFile' => false]],
		]]);

		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'BanFile'));
	}

	/** An entry with no module of its own is toggled by the same path at the top level. */
	public function testTopLevelTogglesApplyToo(): void {
		$policy = widgetMenuPolicy::fromConfig([
			'PostMenu' => ['somethingCore' => false],
			'modules' => ['adminDel' => ['PostMenu' => ['delete' => true]]],
		]);

		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'somethingCore'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'delete'));
	}

	public function testAConfigWithNoTogglesShowsEverything(): void {
		$policy = widgetMenuPolicy::fromConfig([]);

		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'delete'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'deleteFile'));
	}

	// ---- shipped schema -----------------------------------------------------

	/** Every declared toggle is a bool defaulting to on, so a fresh board shows the full menus. */
	public function testShippedTogglesDefaultToEnabled(): void {
		$declared = 0;

		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			if (!preg_match('/(^|\.)(' . widgetMenuPolicy::MENU_POST . '|' . widgetMenuPolicy::MENU_ATTACHMENT . ')\./', (string)$dotpath)) {
				continue;
			}

			$declared++;
			$this->assertSame(configSchema::TYPE_BOOL, $meta['type'], "{$dotpath} should be a checkbox");
			$this->assertTrue($meta['default'], "{$dotpath} should ship enabled");
		}

		$this->assertTrue($declared > 0, 'no menu toggles are declared in the schema');
	}

	/** The declared toggles have to reach the policy through the config the board resolves. */
	public function testShippedTogglesResolveIntoThePolicy(): void {
		$policy = widgetMenuPolicy::fromConfig(configService::resolveDefaults());

		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_POST, 'delete'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'purgeDeletedFile'));

		// and a board that turns one off loses exactly that entry
		$config = configService::resolveDefaults();
		$config['modules']['deletedPosts']['AttachmentMenu']['purgeDeletedFile'] = false;
		$policy = widgetMenuPolicy::fromConfig($config);

		$this->assertFalse($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'purgeDeletedFile'));
		$this->assertTrue($policy->isEnabled(widgetMenuPolicy::MENU_ATTACHMENT, 'restoreDeletedFile'));
	}
}
