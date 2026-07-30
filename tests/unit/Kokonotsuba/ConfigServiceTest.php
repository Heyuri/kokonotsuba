<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use InvalidArgumentException;
use Koko\Tests\Framework\InMemoryConfigRepository;
use Koko\Tests\Framework\TestCase;
use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\configService;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * The config editor's save/resolve logic: how a submitted form becomes stored overrides, and how
 * the schema defaults, the global config and a board's own overrides stack up into one value.
 */
class ConfigServiceTest extends TestCase {

	private InMemoryConfigRepository $repository;
	private configService $service;

	private const BOARD = 3;

	protected function setUp(): void {
		$this->repository = new InMemoryConfigRepository();
		$this->service = new configService($this->repository);
	}

	/** The form posts every field; build that payload, with $changes applied on top. */
	private function submission(array $changes = [], int $boardUid = self::BOARD): array {
		$submitted = [];

		foreach ($this->service->getEffectiveValues($boardUid) as $dotpath => $value) {
			$meta = configSchema::getFieldMeta((string)$dotpath);
			$key = configSchema::inputKey((string)$dotpath);

			if ($meta['type'] === configSchema::TYPE_BOOL) {
				// Unchecked boxes are simply absent from a POST.
				if ($value) {
					$submitted[$key] = '1';
				}
				continue;
			}

			$submitted[$key] = is_array($value)
				? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
				: (string)$value;
		}

		foreach ($changes as $dotpath => $value) {
			$key = configSchema::inputKey((string)$dotpath);

			if ($value === null) {
				unset($submitted[$key]);   // an unchecked checkbox
				continue;
			}

			$submitted[$key] = is_array($value)
				? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
				: (string)$value;
		}

		return $submitted;
	}

	// ---- storing only differences -------------------------------------------

	public function testSavingUnchangedValuesStoresNothing(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission());

		$this->assertSame([], $this->service->getOverrides(self::BOARD));
		$this->assertFalse($this->repository->hasRow(self::BOARD), 'an empty override set should leave no row');
	}

	public function testOnlyChangedValuesAreStored(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => 42]));

		$this->assertSame(['PAGE_DEF' => 42], $this->service->getOverrides(self::BOARD));
	}

	public function testSavingAValueBackToItsDefaultDropsTheOverride(): void {
		$default = configSchema::getFieldMeta('PAGE_DEF')['default'];

		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => 42]));
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => $default]));

		$this->assertSame([], $this->service->getOverrides(self::BOARD));
	}

	// ---- the global -> board cascade ----------------------------------------

	/**
	 * The point of the global config: a board that never touched a setting keeps following it.
	 * This is why a board's save diffs against the global config, not against the schema defaults -
	 * otherwise merely opening a board's form and saving would freeze the value it was shown.
	 */
	public function testBoardInheritsTheGlobalConfig(): void {
		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 20], GLOBAL_BOARD_UID));

		$this->assertSame(20, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF']);
		$this->assertSame([], $this->service->getOverrides(self::BOARD));
	}

	public function testSavingABoardWithAnInheritedValueDoesNotFreezeIt(): void {
		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 20], GLOBAL_BOARD_UID));

		// The board's form is prefilled with the inherited 20; saving it unchanged must store nothing.
		$this->service->saveOverrides(self::BOARD, $this->submission());
		$this->assertSame([], $this->service->getOverrides(self::BOARD));

		// So a later global change still reaches the board.
		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 30], GLOBAL_BOARD_UID));
		$this->assertSame(30, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF']);
	}

	public function testABoardThatDivergesKeepsItsOwnValue(): void {
		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 20], GLOBAL_BOARD_UID));
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => 5]));

		$this->assertSame(['PAGE_DEF' => 5], $this->service->getOverrides(self::BOARD));

		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 40], GLOBAL_BOARD_UID));

		$this->assertSame(5, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF']);
		$this->assertSame(40, $this->service->getEffectiveConfig(GLOBAL_BOARD_UID)['PAGE_DEF']);
	}

	public function testGlobalConfigItselfDiffsAgainstTheSchemaDefaults(): void {
		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 20], GLOBAL_BOARD_UID));

		$this->assertSame(['PAGE_DEF' => 20], $this->service->getOverrides(GLOBAL_BOARD_UID));
	}

	public function testInheritedValuesAreTheGlobalForABoardAndTheDefaultsForTheGlobal(): void {
		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 20], GLOBAL_BOARD_UID));

		$default = configSchema::getFieldMeta('PAGE_DEF')['default'];

		$this->assertSame(20, $this->service->getInheritedValues(self::BOARD)['PAGE_DEF']);
		$this->assertSame($default, $this->service->getInheritedValues(GLOBAL_BOARD_UID)['PAGE_DEF']);
	}

	// ---- resetting ----------------------------------------------------------

	public function testResettingABoardReturnsItToTheGlobalValue(): void {
		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 20], GLOBAL_BOARD_UID));
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => 5]));

		$this->service->resetOverrides(self::BOARD);

		$this->assertSame([], $this->service->getOverrides(self::BOARD));
		$this->assertSame(20, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF'], 'reset should fall back to the global, not the schema default');
	}

	public function testResettingTheGlobalReturnsEveryBoardToTheDefaults(): void {
		$default = configSchema::getFieldMeta('PAGE_DEF')['default'];

		$this->service->saveOverrides(GLOBAL_BOARD_UID, $this->submission(['PAGE_DEF' => 20], GLOBAL_BOARD_UID));
		$this->service->resetOverrides(GLOBAL_BOARD_UID);

		$this->assertSame($default, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF']);
		$this->assertSame([], $this->repository->rows);
	}

	// ---- coercion -----------------------------------------------------------

	public function testCheckboxAbsenceMeansFalse(): void {
		// AUTO_LINK defaults to true; omitting it from the POST is how a browser says "unchecked".
		$this->service->saveOverrides(self::BOARD, $this->submission(['AUTO_LINK' => null]));

		$this->assertSame(false, $this->service->getOverrides(self::BOARD)['AUTO_LINK']);
		$this->assertSame(false, $this->service->getEffectiveConfig(self::BOARD)['AUTO_LINK']);
	}

	public function testIntegersAreStoredAsIntegers(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => '42']));

		$stored = $this->service->getOverrides(self::BOARD)['PAGE_DEF'];

		$this->assertTrue(is_int($stored), 'a submitted number must be stored as an int, not a string');
		$this->assertSame(42, $stored);
	}

	/** The input's min attribute is a client-side hint; a hand-crafted POST must still be clamped. */
	public function testNegativeIntegersAreClampedToTheFieldsMinimum(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => '-5']));

		$this->assertSame(0, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF']);
	}

	public function testAFieldThatOptedOutOfTheZeroMinimumKeepsItsNegativeValue(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['STATIC_HTML_UNTIL' => '-1']));

		$this->assertSame(-1, $this->service->getEffectiveConfig(self::BOARD)['STATIC_HTML_UNTIL']);
	}

	public function testArrayFieldsAreDecodedFromJson(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['VIDEO_EXT' => ['WEBM']]));

		$this->assertSame(['WEBM'], $this->service->getOverrides(self::BOARD)['VIDEO_EXT']);
	}

	public function testAnEmptyArrayFieldIsStoredAsAnEmptyArray(): void {
		$submitted = $this->submission();
		$submitted[configSchema::inputKey('VIDEO_EXT')] = '';

		$this->service->saveOverrides(self::BOARD, $submitted);

		$this->assertSame([], $this->service->getEffectiveConfig(self::BOARD)['VIDEO_EXT']);
	}

	public function testInvalidJsonInAnArrayFieldIsRejected(): void {
		$submitted = $this->submission();
		$submitted[configSchema::inputKey('VIDEO_EXT')] = '{not json';

		$this->assertThrows(
			fn() => $this->service->saveOverrides(self::BOARD, $submitted),
			InvalidArgumentException::class
		);
	}

	/** A rejected save must not have written half of the form. */
	public function testARejectedSaveStoresNothing(): void {
		$submitted = $this->submission(['PAGE_DEF' => 42]);
		$submitted[configSchema::inputKey('VIDEO_EXT')] = '{not json';

		try {
			$this->service->saveOverrides(self::BOARD, $submitted);
		} catch (InvalidArgumentException) {
			// expected
		}

		$this->assertFalse($this->repository->hasRow(self::BOARD));
	}

	// ---- hostile input ------------------------------------------------------

	public function testUnknownSubmittedKeysAreIgnored(): void {
		$submitted = $this->submission();
		$submitted['thisIsNotASetting'] = 'evil';
		$submitted['STATIC_URL'] = 'https://attacker.example/';

		$this->service->saveOverrides(self::BOARD, $submitted);

		$this->assertSame([], $this->service->getOverrides(self::BOARD));
		$this->assertSame('https://static.example.net/', $this->service->getEffectiveConfig(self::BOARD)['STATIC_URL']);
	}

	/** Overrides are filtered against the schema on read, so a setting removed later goes quiet. */
	public function testStoredOverridesForRemovedSettingsAreIgnored(): void {
		$this->repository->saveOverridesForBoardUid(self::BOARD, [
			'PAGE_DEF' => 42,
			'A_SETTING_THAT_NO_LONGER_EXISTS' => 'x',
		]);

		$this->assertSame(['PAGE_DEF' => 42], $this->service->getOverrides(self::BOARD));
	}

	public function testAnEmptySubmissionLeavesNonBoolValuesAlone(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => 42]));

		// A POST with no fields at all: non-bools are absent, so they must not be forced to default.
		$this->service->saveOverrides(self::BOARD, []);

		$this->assertSame(42, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF']);
	}

	// ---- resolution ---------------------------------------------------------

	public function testEffectiveConfigContainsTheImmutableGlobals(): void {
		$config = $this->service->getEffectiveConfig(self::BOARD);

		// Not editable, but still part of the array every board reads.
		$this->assertTrue(isset($config['STATIC_URL']));
		$this->assertTrue(isset($config['WEBSITE_URL']));
		$this->assertTrue(isset($config['AuthLevels']));
	}

	public function testEffectiveConfigNestsDotPaths(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['ModuleList.catalog' => null]));

		$config = $this->service->getEffectiveConfig(self::BOARD);

		$this->assertSame(false, $config['ModuleList']['catalog'], 'a dot-path must resolve into a nested array');
	}

	public function testEffectiveValuesMatchTheStoredOverrides(): void {
		$this->service->saveOverrides(self::BOARD, $this->submission(['PAGE_DEF' => 7]));

		$this->assertSame(7, $this->service->getEffectiveValues(self::BOARD)['PAGE_DEF']);
	}

	// ---- single-setting edits (the CLI editor's surface) --------------------

	public function testSetOverrideStoresOneValueCoerced(): void {
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '42');

		$stored = $this->service->getOverrides(self::BOARD)['PAGE_DEF'];
		$this->assertTrue(is_int($stored), 'a set value must be coerced to its type');
		$this->assertSame(42, $stored);
	}

	public function testSetOverrideLeavesOtherOverridesUntouched(): void {
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '42');
		$this->service->setOverride(self::BOARD, 'MAX_KB', '4096');

		$this->assertSame(['PAGE_DEF' => 42, 'MAX_KB' => 4096], $this->service->getOverrides(self::BOARD));
	}

	public function testSetOverrideToTheInheritedValueClearsTheOverride(): void {
		$default = configSchema::getFieldMeta('PAGE_DEF')['default'];

		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '42');
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', (string)$default);

		$this->assertSame([], $this->service->getOverrides(self::BOARD));
	}

	public function testSetOverrideDiffsAgainstTheGlobalForABoard(): void {
		$this->service->setOverride(GLOBAL_BOARD_UID, 'PAGE_DEF', '20');

		// Setting the board to the value it already inherits from the global stores no override.
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '20');
		$this->assertSame([], $this->service->getOverrides(self::BOARD));

		// A genuinely different value is stored.
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '5');
		$this->assertSame(['PAGE_DEF' => 5], $this->service->getOverrides(self::BOARD));
	}

	public function testSetOverrideClampsIntegersToTheMinimum(): void {
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '-5');

		$this->assertSame(0, $this->service->getEffectiveConfig(self::BOARD)['PAGE_DEF']);
	}

	public function testSetOverrideRejectsAnUnknownSetting(): void {
		$this->assertThrows(
			fn() => $this->service->setOverride(self::BOARD, 'NOT_A_SETTING', 'x'),
			\InvalidArgumentException::class
		);
	}

	public function testSetOverrideRejectsInvalidArrayJson(): void {
		$this->assertThrows(
			fn() => $this->service->setOverride(self::BOARD, 'VIDEO_EXT', '{not json'),
			\InvalidArgumentException::class
		);
	}

	public function testSetOverrideAcceptsJsonForArrayFields(): void {
		$this->service->setOverride(self::BOARD, 'VIDEO_EXT', '["webm"]');

		$this->assertSame(['webm'], $this->service->getOverrides(self::BOARD)['VIDEO_EXT']);
	}

	public function testUnsetOverrideClearsJustThatSetting(): void {
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '42');
		$this->service->setOverride(self::BOARD, 'MAX_KB', '4096');

		$this->service->unsetOverride(self::BOARD, 'PAGE_DEF');

		$this->assertSame(['MAX_KB' => 4096], $this->service->getOverrides(self::BOARD));
	}

	public function testUnsetOverrideIsANoOpWhenNotOverridden(): void {
		$this->service->setOverride(self::BOARD, 'MAX_KB', '4096');

		$this->service->unsetOverride(self::BOARD, 'PAGE_DEF');

		$this->assertSame(['MAX_KB' => 4096], $this->service->getOverrides(self::BOARD));
	}

	public function testEmptyingAScopeBySettingBackToInheritedLeavesNoRow(): void {
		$this->service->setOverride(self::BOARD, 'PAGE_DEF', '42');
		$this->service->unsetOverride(self::BOARD, 'PAGE_DEF');

		$this->assertFalse($this->repository->hasRow(self::BOARD), 'an emptied scope should leave no row');
	}
}
