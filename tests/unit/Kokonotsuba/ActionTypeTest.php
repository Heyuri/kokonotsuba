<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\action_log\actionType;
use Kokonotsuba\action_log\actionTypeGroup;
use Kokonotsuba\action_log\actionTypeRegistry;

/**
 * The action type enum and the registry modules extend it through.
 *
 * A type key is written into the log row verbatim and read back to filter on, so the key strings
 * are part of the stored format and are pinned here.
 */
final class ActionTypeTest extends TestCase {

	public function testKeysAreStableStrings(): void {
		$this->assertSame('post.delete', actionType::POST_DELETE->value);
		$this->assertSame('post.purge', actionType::POST_PURGE->value);
		$this->assertSame('ban.issue', actionType::BAN_ISSUE->value);
		$this->assertSame('ban.trigger', actionType::BAN_TRIGGER->value);
		$this->assertSame('account.login_failed', actionType::ACCOUNT_LOGIN_FAILED->value);
		$this->assertSame('board.rebuild', actionType::BOARD_REBUILD->value);
		$this->assertSame('other', actionType::OTHER->value);
	}

	/** Every case has to land in a group, or it drops off the filter form entirely. */
	public function testEveryCaseIsGrouped(): void {
		foreach (actionType::cases() as $case) {
			$this->assertTrue($case->group() instanceof actionTypeGroup, $case->value . ' has no group');
			$this->assertTrue($case->label() !== '', $case->value . ' has no label');
		}
	}

	/** Post registrations are the one flood the log would otherwise drown in. */
	public function testPostRegistrationIsOffByDefault(): void {
		$this->assertFalse(actionType::POST_REGISTER->isDefault());
		$this->assertTrue(actionType::POST_DELETE->isDefault());
		$this->assertTrue(actionType::OTHER->isDefault());
	}

	public function testRegistryListsBuiltInsFirst(): void {
		$registry = new actionTypeRegistry();
		$registry->register('tool.tegaki', 'Drawings');

		$keys = array_column($registry->all(), 'key');

		$this->assertSame('post.register', $keys[0]);
		$this->assertSame('tool.tegaki', $keys[count($keys) - 1]);
	}

	public function testRegisteredTypeJoinsItsGroup(): void {
		$registry = new actionTypeRegistry();
		$registry->register('content.tegaki', 'Drawings', 'content');

		$grouped = $registry->grouped();
		$keys = array_column($grouped['content']['entries'], 'key');

		$this->assertTrue(in_array('content.tegaki', $keys, true));
	}

	/** An unknown group would otherwise lose the entry when grouped() buckets by key. */
	public function testUnknownGroupFallsBackToTool(): void {
		$registry = new actionTypeRegistry();
		$registry->register('nonsense.thing', 'Thing', 'nonsense');

		$grouped = $registry->grouped();

		$this->assertTrue(in_array('nonsense.thing', array_column($grouped['tool']['entries'], 'key'), true));
	}

	/** A feature's enum registers every case with its own label, group and default. */
	public function testRegisteredEnumJoinsTheRegistry(): void {
		$registry = new actionTypeRegistry();
		$registry->registerEnum(ActionTypeTestEnum::class);

		$this->assertSame('Drawings', $registry->labelFor('content.tegaki'));
		$this->assertTrue(in_array('content.tegaki', array_column($registry->grouped()['content']['entries'], 'key'), true));
		$this->assertFalse(in_array('tool.tegaki_debug', $registry->defaultKeys(), true));
	}

	public function testRegisteringANonDefinitionClassIsRejected(): void {
		$threw = false;

		try {
			(new actionTypeRegistry())->registerEnum(\stdClass::class);
		} catch (\InvalidArgumentException $e) {
			$threw = true;
		}

		$this->assertTrue($threw);
	}

	/** The key strings feature enums write are part of the stored format, so pin them too. */
	public function testFeatureEnumKeysAreStable(): void {
		$expected = [
			'module/edit/editActionType.php' => ['post.edit'],
			'module/moveThread/moveThreadActionType.php' => ['post.move'],
			'module/adminBan/adminBanActionType.php' => ['ban.edit', 'ban.revoke', 'ban.appeal'],
			'module/fileBan/fileBanActionType.php' => ['file_ban.trigger', 'tool.file_ban'],
			'module/perceptualBan/perceptualBanActionType.php' => ['file_ban.trigger', 'tool.file_ban'],
			'module/banner/bannerActionType.php' => ['content.banner'],
			'module/blotter/blotterActionType.php' => ['content.blotter'],
			'module/ads/adsActionType.php' => ['content.ad'],
			'module/tripcode/capcodeActionType.php' => ['tool.capcode'],
			'module/report/reportActionType.php' => ['tool.report'],
			'module/privateMessage/privateMessageActionType.php' => ['tool.pm'],
			'module/anonIp/anonIpActionType.php' => ['tool.anon_ip'],
		];

		foreach ($expected as $file => $keys) {
			preg_match_all("/case \\w+ = '([^']+)';/", file_get_contents(dirname(__DIR__, 3) . '/' . $file), $m);
			$this->assertSame($keys, $m[1], $file);
		}
	}

	/** A module must not be able to redefine what a built-in type means. */
	public function testRegisteringOverABuiltInIsIgnored(): void {
		$registry = new actionTypeRegistry();
		$registry->register('post.delete', 'Something else', 'content', false);

		$this->assertSame(1, count(array_filter($registry->all(), fn(array $e): bool => $e['key'] === 'post.delete')));
		$this->assertSame('Post deleted', $registry->labelFor('post.delete'));
	}

	public function testInvalidKeysAreRejected(): void {
		$registry = new actionTypeRegistry();

		foreach (['', 'has space', 'Bad/Slash', 'semi;colon'] as $key) {
			$threw = false;

			try {
				$registry->register($key, 'Label');
			} catch (\InvalidArgumentException $e) {
				$threw = true;
			}

			$this->assertTrue($threw, "register('{$key}') should have been rejected");
		}
	}

	/**
	 * Type keys come off a submitted form, so anything unrecognised has to be dropped rather than
	 * bound - an unknown key in the IN list is a filter that quietly matches nothing.
	 */
	public function testFilterKnownDropsUnknownAndDuplicates(): void {
		$registry = new actionTypeRegistry();
		$registry->register('tool.tegaki', 'Drawings');

		$this->assertSame(
			['post.delete', 'tool.tegaki'],
			$registry->filterKnown(['post.delete', 'nonsense', 'POST.DELETE', 'tool.tegaki', ''])
		);
	}

	/** A type whose module has since been switched off still has to render in the table. */
	public function testLabelForFallsBackToTheKey(): void {
		$registry = new actionTypeRegistry();

		$this->assertSame('tool.gone', $registry->labelFor('tool.gone'));
	}
}

enum ActionTypeTestEnum: string implements \Kokonotsuba\action_log\actionTypeDefinition {
	case TEGAKI = 'content.tegaki';
	case TEGAKI_DEBUG = 'tool.tegaki_debug';

	public function label(): string {
		return $this === self::TEGAKI ? 'Drawings' : 'Drawing debug';
	}

	public function group(): actionTypeGroup {
		return $this === self::TEGAKI ? actionTypeGroup::CONTENT : actionTypeGroup::TOOL;
	}

	public function isDefault(): bool {
		return $this === self::TEGAKI;
	}
}
