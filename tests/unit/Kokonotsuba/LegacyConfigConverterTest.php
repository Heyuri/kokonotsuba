<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\InMemoryConfigRepository;
use Koko\Tests\Framework\TestCase;
use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\configService;
use Kokonotsuba\config\legacyConfigConverter;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Converting a pre-database board config file into the overrides the editor stores.
 *
 * The legacy files are flat $config arrays: module settings in one undifferentiated
 * ModuleSettings bag, template names with a '.tpl' suffix, VIDEO_EXT as a pipe-separated string,
 * ad slot sizes as {width, height} pairs - plus keys the schema no longer has at all.
 */
class LegacyConfigConverterTest extends TestCase {

	/** A legacy globalBoardConfig.php, reduced to the parts that matter here. */
	private function legacyGlobal(): array {
		return [
			// Immutable globals were in the same array; they are not editable and must be dropped.
			'STATIC_URL'  => 'https://static.example.net/',
			'WEBSITE_URL' => '/',

			// Plain settings, unchanged in shape.
			'PAGE_DEF'          => 15,
			'MAX_KB'            => 9000,
			'ALWAYS_NOKO'       => false,
			'REPLIES_PER_PAGE'  => 200,

			// Shape changed since.
			'TEMPLATE_FILE'       => 'kokoimg.tpl',
			'REPLY_TEMPLATE_FILE' => 'kokoimg.tpl',
			'VIDEO_EXT'           => 'WEBM|MP4',

			// A setting the schema dropped.
			'SOME_REMOVED_SETTING' => 'whatever',

			'ModuleList' => [
				'catalog'      => true,
				'search'       => true,
				'countryFlags' => true,
			],

			// The old flat bag: no module name anywhere. RENZOKU3 and FLAG_MODE differ from today's
			// defaults (30 and 1), so they are real overrides; ENABLE_YEAH matches its default and
			// so must not be migrated.
			'ModuleSettings' => [
				'RENZOKU3'       => 45,
				'FLAG_MODE'      => 2,
				'SEARCH_TEMPLATE' => 'kokoimg.tpl',
				'ENABLE_YEAH'    => true,
				'A_DEAD_MODULE_SETTING' => 'gone',
			],
		];
	}

	// ---- key mapping --------------------------------------------------------

	public function testLegacyModuleKeysMapToTheirNamespacedDotPath(): void {
		$this->assertSame('modules.antiFlood.RENZOKU3', legacyConfigConverter::moduleDotpathFor('RENZOKU3'));
		$this->assertSame('modules.countryFlags.FLAG_MODE', legacyConfigConverter::moduleDotpathFor('FLAG_MODE'));
		$this->assertSame('modules.soudane.ENABLE_YEAH', legacyConfigConverter::moduleDotpathFor('ENABLE_YEAH'));
	}

	public function testAnUnknownModuleKeyMapsToNothing(): void {
		$this->assertNull(legacyConfigConverter::moduleDotpathFor('A_DEAD_MODULE_SETTING'));
	}

	/** Menu entry toggles are new, so no legacy value can be migrated into one. */
	public function testMenuEntryTogglesAreNotLegacySettings(): void {
		$this->assertNull(legacyConfigConverter::moduleDotpathFor('delete'));
		$this->assertNull(legacyConfigConverter::moduleDotpathFor('BanFile'));

		$this->assertTrue(legacyConfigConverter::isNonLegacyPath('modules.adminDel.PostMenu.delete'));
		$this->assertFalse(legacyConfigConverter::isNonLegacyPath('modules.antiFlood.RENZOKU3'));
	}

	/**
	 * Matching legacy module settings by their bare name only works because no two modules declare
	 * the same one. If that ever stops being true the mapping becomes ambiguous - fail loudly here
	 * rather than silently migrating a value into the wrong module.
	 */
	public function testNoTwoModulesShareASettingName(): void {
		$seen = [];

		foreach (array_keys(configSchema::getAllFields()) as $dotpath) {
			// menu entry toggles are keyed by action name, are shared between modules by design,
			// and were never in the legacy bag - they are excluded from the mapping
			if (!str_starts_with((string)$dotpath, 'modules.')
				|| legacyConfigConverter::isNonLegacyPath((string)$dotpath)) {
				continue;
			}

			$segments = explode('.', (string)$dotpath);
			$bare = end($segments);

			$this->assertFalse(
				isset($seen[$bare]),
				"module setting '{$bare}' is declared by both '" . ($seen[$bare] ?? '') . "' and '{$dotpath}' - the legacy ModuleSettings bag cannot be mapped unambiguously"
			);

			$seen[$bare] = $dotpath;
		}
	}

	// ---- value lookup -------------------------------------------------------

	public function testValuesAreFoundByDotPath(): void {
		$legacy = $this->legacyGlobal();

		$this->assertSame(15, legacyConfigConverter::legacyValueFor($legacy, 'PAGE_DEF'));
		$this->assertSame(true, legacyConfigConverter::legacyValueFor($legacy, 'ModuleList.catalog'));
	}

	public function testModuleValuesAreFoundInTheLegacyFlatBag(): void {
		$legacy = $this->legacyGlobal();

		$this->assertSame(45, legacyConfigConverter::legacyValueFor($legacy, 'modules.antiFlood.RENZOKU3'));
		$this->assertSame(2, legacyConfigConverter::legacyValueFor($legacy, 'modules.countryFlags.FLAG_MODE'));
	}

	public function testAnAbsentValueIsNull(): void {
		$this->assertNull(legacyConfigConverter::legacyValueFor([], 'PAGE_DEF'));
		$this->assertNull(legacyConfigConverter::legacyValueFor([], 'modules.antiFlood.RENZOKU3'));
		$this->assertNull(legacyConfigConverter::legacyValueFor(['ModuleList' => []], 'ModuleList.catalog'));
	}

	// ---- shape conversions --------------------------------------------------

	public function testTemplateNamesLoseTheirTplSuffix(): void {
		$meta = configSchema::getFieldMeta('TEMPLATE_FILE');

		$this->assertSame('kokoimg', legacyConfigConverter::convertValue('TEMPLATE_FILE', $meta, 'kokoimg.tpl'));
		$this->assertSame('kokotxt', legacyConfigConverter::convertValue('TEMPLATE_FILE', $meta, 'kokotxt.tpl'));

		// Already converted, or never suffixed: left alone.
		$this->assertSame('kokoimg', legacyConfigConverter::convertValue('TEMPLATE_FILE', $meta, 'kokoimg'));
	}

	public function testVideoExtensionsBecomeAList(): void {
		$meta = configSchema::getFieldMeta('VIDEO_EXT');

		// Lower-cased: the old files shouted them, the schema default doesn't, and a case-only
		// difference would migrate as a pointless override.
		$this->assertSame(['webm', 'mp4'], legacyConfigConverter::convertValue('VIDEO_EXT', $meta, 'WEBM|MP4'));
		$this->assertSame(['webm'], legacyConfigConverter::convertValue('VIDEO_EXT', $meta, 'WEBM'));
		$this->assertSame([], legacyConfigConverter::convertValue('VIDEO_EXT', $meta, ''));

		// Stray separators and spacing in a hand-edited file.
		$this->assertSame(['webm', 'mp4'], legacyConfigConverter::convertValue('VIDEO_EXT', $meta, ' WEBM || MP4 |'));
	}

	/** The stock legacy value maps exactly onto the schema default, so it migrates to nothing. */
	public function testTheStockVideoExtensionsMigrateToNoOverride(): void {
		$overrides = legacyConfigConverter::extractOverrides(
			['VIDEO_EXT' => 'WEBM|MP4'],
			configSchema::getDefaults()
		);

		$this->assertFalse(isset($overrides['VIDEO_EXT']), "'WEBM|MP4' is the default in new clothes and should not be stored");
	}

	public function testAdSlotDimensionsBecomeWidthByHeightStrings(): void {
		$path = 'modules.ads.ADS_SLOT_DIMENSIONS';
		$meta = configSchema::getFieldMeta($path);

		$converted = legacyConfigConverter::convertValue($path, $meta, [
			'top'    => ['width' => 728, 'height' => 90],
			'mobile' => ['width' => 300, 'height' => 250],
		]);

		$this->assertSame(['top' => '728x90', 'mobile' => '300x250'], $converted);
	}

	public function testAlreadyConvertedAdSlotDimensionsSurviveASecondRun(): void {
		$path = 'modules.ads.ADS_SLOT_DIMENSIONS';
		$meta = configSchema::getFieldMeta($path);

		$converted = legacyConfigConverter::convertValue($path, $meta, ['top' => '728x90']);

		$this->assertSame(['top' => '728x90'], $converted);
	}

	public function testValuesAreCoercedToTheirSchemaType(): void {
		// Old files were hand-edited PHP: 1/0 for booleans, numeric strings for ints.
		$this->assertSame(true, legacyConfigConverter::convertValue('ALWAYS_NOKO', configSchema::getFieldMeta('ALWAYS_NOKO'), 1));
		$this->assertSame(false, legacyConfigConverter::convertValue('ALWAYS_NOKO', configSchema::getFieldMeta('ALWAYS_NOKO'), 0));
		$this->assertSame(25, legacyConfigConverter::convertValue('PAGE_DEF', configSchema::getFieldMeta('PAGE_DEF'), '25'));
	}

	public function testNegativeIntegersAreClampedUnlessTheFieldOptedOut(): void {
		$this->assertSame(0, legacyConfigConverter::convertValue('PAGE_DEF', configSchema::getFieldMeta('PAGE_DEF'), -5));
		$this->assertSame(-1, legacyConfigConverter::convertValue('STATIC_HTML_UNTIL', configSchema::getFieldMeta('STATIC_HTML_UNTIL'), -1));
	}

	// ---- extracting overrides -----------------------------------------------

	/** A legacy file identical to today's defaults should migrate to nothing at all. */
	public function testAConfigMatchingTheDefaultsProducesNoOverrides(): void {
		$defaults = configSchema::getDefaults();

		// Rebuild a legacy-shaped array out of the current defaults.
		$legacy = ['ModuleList' => [], 'ModuleSettings' => []];
		foreach ($defaults as $dotpath => $value) {
			if (str_starts_with((string)$dotpath, 'modules.')) {
				$segments = explode('.', (string)$dotpath);
				$legacy['ModuleSettings'][end($segments)] = $value;
			} elseif (str_starts_with((string)$dotpath, 'ModuleList.')) {
				$legacy['ModuleList'][substr((string)$dotpath, strlen('ModuleList.'))] = $value;
			} else {
				$legacy[$dotpath] = $value;
			}
		}

		$this->assertSame([], legacyConfigConverter::extractOverrides($legacy, $defaults));
	}

	public function testOnlyDifferencesFromTheBaselineAreExtracted(): void {
		$defaults = configSchema::getDefaults();

		$overrides = legacyConfigConverter::extractOverrides($this->legacyGlobal(), $defaults);

		// PAGE_DEF, REPLIES_PER_PAGE and ENABLE_YEAH match today's defaults - not overrides.
		$this->assertFalse(isset($overrides['PAGE_DEF']), 'a value equal to the default should not be stored');
		$this->assertFalse(isset($overrides['REPLIES_PER_PAGE']));
		$this->assertFalse(isset($overrides['modules.soudane.ENABLE_YEAH']));

		// These genuinely differ from the defaults, and land under their module's namespace.
		$this->assertSame(2, $overrides['modules.countryFlags.FLAG_MODE'] ?? null);
		$this->assertSame(45, $overrides['modules.antiFlood.RENZOKU3'] ?? null);
	}

	public function testDroppedSettingsAreNotMigrated(): void {
		$overrides = legacyConfigConverter::extractOverrides($this->legacyGlobal(), configSchema::getDefaults());

		foreach (array_keys($overrides) as $dotpath) {
			$this->assertTrue(
				configSchema::hasField((string)$dotpath),
				"migrated '{$dotpath}', which is not a schema field"
			);
		}
	}

	public function testImmutableGlobalsAreNotMigrated(): void {
		$overrides = legacyConfigConverter::extractOverrides($this->legacyGlobal(), configSchema::getDefaults());

		$this->assertFalse(isset($overrides['STATIC_URL']), 'STATIC_URL is not board-editable and must not be migrated');
		$this->assertFalse(isset($overrides['WEBSITE_URL']), 'WEBSITE_URL is not board-editable and must not be migrated');
	}

	public function testConvertedValuesAreStoredInTheirNewShape(): void {
		$legacy = $this->legacyGlobal();
		$legacy['TEMPLATE_FILE'] = 'kokotxt.tpl';   // differs from the default, so it is stored
		$legacy['VIDEO_EXT'] = 'WEBM';

		$overrides = legacyConfigConverter::extractOverrides($legacy, configSchema::getDefaults());

		$this->assertSame('kokotxt', $overrides['TEMPLATE_FILE'] ?? null);
		$this->assertSame(['webm'], $overrides['VIDEO_EXT'] ?? null);
	}

	public function testEveryExtractedValueMatchesItsSchemaType(): void {
		$overrides = legacyConfigConverter::extractOverrides($this->legacyGlobal(), configSchema::getDefaults());

		foreach ($overrides as $dotpath => $value) {
			$meta = configSchema::getFieldMeta((string)$dotpath);

			switch ($meta['type']) {
				case configSchema::TYPE_BOOL:
					$this->assertTrue(is_bool($value), "{$dotpath} should be a bool");
					break;
				case configSchema::TYPE_INT:
					$this->assertTrue(is_int($value), "{$dotpath} should be an int");
					break;
				case configSchema::TYPE_ARRAY:
					$this->assertTrue(is_array($value), "{$dotpath} should be an array");
					break;
				default:
					$this->assertTrue(is_string($value), "{$dotpath} should be a string");
			}
		}
	}

	// ---- the migration as a whole -------------------------------------------

	/**
	 * The shape the CLI script performs: the old site-wide file becomes the global config, and each
	 * board's file becomes only what that board changed from the site-wide file. A board that just
	 * inherited a value must end up storing nothing for it, so it keeps following the global config.
	 */
	public function testBoardKeepsInheritingWhatItNeverOverrode(): void {
		$defaults = configSchema::getDefaults();

		$legacyGlobal = $this->legacyGlobal();
		$legacyGlobal['PAGE_DEF'] = 20;             // the old site-wide value

		$globalOverrides = legacyConfigConverter::extractOverrides($legacyGlobal, $defaults);
		$this->assertSame(20, $globalOverrides['PAGE_DEF'] ?? null);

		// The board's file required the global one, so it holds the same 20 without having set it.
		$legacyBoard = $legacyGlobal;
		$legacyBoard['MAX_KB'] = 4096;              // the one thing this board actually changed

		$legacyGlobalValues = array_merge($defaults, $globalOverrides);
		$boardOverrides = legacyConfigConverter::extractOverrides($legacyBoard, $legacyGlobalValues);

		$this->assertSame(['MAX_KB' => 4096], $boardOverrides);

		// End to end: load those rows and the board still follows a later global change.
		$repository = new InMemoryConfigRepository();
		$repository->saveOverridesForBoardUid(GLOBAL_BOARD_UID, $globalOverrides);
		$repository->saveOverridesForBoardUid(3, $boardOverrides);

		$service = new configService($repository);

		$this->assertSame(20, $service->getEffectiveConfig(3)['PAGE_DEF'], 'the board should inherit the migrated global value');
		$this->assertSame(4096, $service->getEffectiveConfig(3)['MAX_KB'], 'the board should keep the value it overrode');
	}

	public function testMigratingTwiceIsIdempotent(): void {
		$defaults = configSchema::getDefaults();

		$first = legacyConfigConverter::extractOverrides($this->legacyGlobal(), $defaults);
		$second = legacyConfigConverter::extractOverrides($this->legacyGlobal(), $defaults);

		$this->assertSame($first, $second);
	}

	/** Migrated rows must survive the JSON column round trip unchanged. */
	public function testMigratedOverridesRoundTripThroughStorage(): void {
		$overrides = legacyConfigConverter::extractOverrides($this->legacyGlobal(), configSchema::getDefaults());

		$repository = new InMemoryConfigRepository();
		$repository->saveOverridesForBoardUid(GLOBAL_BOARD_UID, $overrides);

		$this->assertSame($overrides, $repository->getOverridesByBoardUid(GLOBAL_BOARD_UID));
	}

	public function testAnEmptyLegacyConfigMigratesToNothing(): void {
		$this->assertSame([], legacyConfigConverter::extractOverrides([], configSchema::getDefaults()));
	}
}
