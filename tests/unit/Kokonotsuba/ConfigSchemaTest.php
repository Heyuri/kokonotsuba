<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\config\configSchema;

/**
 * The schema loaded from the real configs/*.php and module/{name}/config.php.
 *
 * These assert the invariants the config editor relies on rather than the value of any one
 * setting, so adding a setting doesn't break them - but declaring one wrongly does.
 */
class ConfigSchemaTest extends TestCase {

	// ---- input keys ---------------------------------------------------------

	/**
	 * The form input key is derived from the dot-path by both the renderer and the save handler,
	 * with no stored mapping between them - so it has to be deterministic and collision-free.
	 */
	public function testInputKeyIsCamelCaseAndDotFree(): void {
		$this->assertSame('repliesPerPage', configSchema::inputKey('REPLIES_PER_PAGE'));
		$this->assertSame('modulesAntifloodRenzoku3', configSchema::inputKey('modules.antiFlood.RENZOKU3'));
		// Each dot-separated word is lower-cased whole, then title-cased after the first.
		$this->assertSame('modulelistCatalog', configSchema::inputKey('ModuleList.catalog'));
	}

	public function testInputKeyIsDeterministic(): void {
		foreach (array_keys(configSchema::getAllFields()) as $dotpath) {
			$this->assertSame(
				configSchema::inputKey((string)$dotpath),
				configSchema::inputKey((string)$dotpath),
				"inputKey({$dotpath}) is not stable"
			);
		}
	}

	/** Two settings sharing an input key would silently overwrite each other on save. */
	public function testEveryInputKeyIsUnique(): void {
		$seen = [];

		foreach (array_keys(configSchema::getAllFields()) as $dotpath) {
			$key = configSchema::inputKey((string)$dotpath);

			$this->assertFalse(
				isset($seen[$key]),
				"input key '{$key}' is produced by both '" . ($seen[$key] ?? '') . "' and '{$dotpath}'"
			);

			$seen[$key] = $dotpath;
		}
	}

	/** The key is used as an HTML id and inside config[...] - it must be a plain identifier. */
	public function testInputKeysAreSafeHtmlIdentifiers(): void {
		foreach (array_keys(configSchema::getAllFields()) as $dotpath) {
			$key = configSchema::inputKey((string)$dotpath);
			$this->assertTrue(
				(bool)preg_match('/^[a-z][a-zA-Z0-9]*$/', $key),
				"input key '{$key}' (from '{$dotpath}') is not a plain identifier"
			);
		}
	}

	// ---- field metadata -----------------------------------------------------

	public function testEveryFieldHasNormalizedMetadata(): void {
		$types = [
			configSchema::TYPE_BOOL,
			configSchema::TYPE_INT,
			configSchema::TYPE_STRING,
			configSchema::TYPE_TEXT,
			configSchema::TYPE_ARRAY,
			configSchema::TYPE_TEMPLATE,
		];

		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			foreach (['default', 'type', 'label', 'desc', 'module', 'min'] as $key) {
				$this->assertTrue(array_key_exists($key, $meta), "{$dotpath} has no '{$key}'");
			}

			$this->assertTrue(in_array($meta['type'], $types, true), "{$dotpath} has unknown type '{$meta['type']}'");
		}
	}

	/** A default that doesn't match its declared type would be coerced on the first save. */
	public function testDefaultsMatchTheirDeclaredType(): void {
		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			switch ($meta['type']) {
				case configSchema::TYPE_BOOL:
					$this->assertTrue(is_bool($meta['default']), "{$dotpath} is bool but its default is not");
					break;
				case configSchema::TYPE_INT:
					$this->assertTrue(is_int($meta['default']), "{$dotpath} is int but its default is not");
					break;
				case configSchema::TYPE_ARRAY:
					$this->assertTrue(is_array($meta['default']), "{$dotpath} is array but its default is not");
					break;
				case configSchema::TYPE_STRING:
				case configSchema::TYPE_TEXT:
				case configSchema::TYPE_TEMPLATE:
					$this->assertTrue(is_string($meta['default']), "{$dotpath} is a string type but its default is not");
					break;
			}
		}
	}

	// ---- int minimums -------------------------------------------------------

	/** Int fields floor at zero unless they opt out with their own min. */
	public function testIntFieldsDefaultToAMinimumOfZero(): void {
		$this->assertSame(0, configSchema::getFieldMeta('PAGE_DEF')['min']);
		$this->assertSame(0, configSchema::getFieldMeta('MAX_KB')['min']);
	}

	public function testIntFieldCanOptOutOfTheZeroMinimum(): void {
		// -1 means "all pages" for this one, so it declares its own lower bound.
		$this->assertSame(-1, configSchema::getFieldMeta('STATIC_HTML_UNTIL')['min']);
	}

	public function testMinIsOnlySetForIntFields(): void {
		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			if ($meta['type'] === configSchema::TYPE_INT) {
				$this->assertTrue(
					$meta['min'] === null || is_int($meta['min']),
					"{$dotpath} has a non-int, non-null min"
				);
				continue;
			}

			$this->assertNull($meta['min'], "{$dotpath} is not an int but declares a min");
		}
	}

	/** A default below its own minimum would be clamped away the first time the form is saved. */
	public function testIntDefaultsSatisfyTheirOwnMinimum(): void {
		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			if ($meta['type'] !== configSchema::TYPE_INT || $meta['min'] === null) {
				continue;
			}

			$this->assertTrue(
				$meta['default'] >= $meta['min'],
				"{$dotpath} defaults to {$meta['default']}, below its min of {$meta['min']}"
			);
		}
	}

	// ---- language keys ------------------------------------------------------

	/** Labels and descriptions are language keys named after the dot-path, resolved at render. */
	public function testLabelsAndDescriptionsAreLanguageKeys(): void {
		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			$this->assertSame(
				'config_label_' . $dotpath,
				$meta['label'],
				"{$dotpath} has a label that is not its language key"
			);

			if ($meta['desc'] !== '') {
				$this->assertSame(
					'config_desc_' . $dotpath,
					$meta['desc'],
					"{$dotpath} has a description that is not its language key"
				);
			}
		}
	}

	/** Every language key a field names must actually exist in en_US. */
	public function testEveryLanguageKeyExists(): void {
		$language = [];
		require KOKO_TEST_ROOT . '/code/Kokonotsuba/lang/en_US.php';

		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			$this->assertTrue(
				isset($language[$meta['label']]),
				"missing language entry: {$meta['label']}"
			);

			if ($meta['desc'] !== '') {
				$this->assertTrue(
					isset($language[$meta['desc']]),
					"missing language entry: {$meta['desc']}"
				);
			}
		}
	}

	/**
	 * _T() runs every string through sprintf, so a stray % in a label or description throws
	 * ArgumentCountError when it is translated with no arguments.
	 */
	public function testLanguageTextHasNoSprintfPlaceholders(): void {
		$language = [];
		require KOKO_TEST_ROOT . '/code/Kokonotsuba/lang/en_US.php';

		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			foreach ([$meta['label'], $meta['desc']] as $key) {
				if ($key === '' || !isset($language[$key])) {
					continue;
				}

				$this->assertFalse(
					str_contains($language[$key], '%'),
					"language entry '{$key}' contains a % - _T() would run it through sprintf"
				);
			}
		}
	}

	// ---- groups -------------------------------------------------------------

	public function testEveryFieldBelongsToExactlyOneGroup(): void {
		$counts = [];

		foreach (configSchema::getGroups() as $fields) {
			foreach (array_keys($fields) as $dotpath) {
				$counts[$dotpath] = ($counts[$dotpath] ?? 0) + 1;
			}
		}

		foreach (array_keys(configSchema::getAllFields()) as $dotpath) {
			$this->assertSame(1, $counts[$dotpath] ?? 0, "{$dotpath} is not in exactly one group");
		}
	}

	public function testGroupsCoverEveryField(): void {
		$grouped = 0;
		foreach (configSchema::getGroups() as $fields) {
			$grouped += count($fields);
		}

		$this->assertSame(count(configSchema::getAllFields()), $grouped);
	}

	/** The bbCode module's settings get a section of their own rather than swamping another. */
	public function testBbCodeSettingsAreTheirOwnGroup(): void {
		$groups = configSchema::getGroups();

		$this->assertTrue(isset($groups['BBCode']), 'no BBCode group');

		foreach (array_keys($groups['BBCode']) as $dotpath) {
			$this->assertTrue(
				str_starts_with((string)$dotpath, 'modules.bbCode.'),
				"{$dotpath} is in the BBCode group but is not a bbCode setting"
			);
		}
	}

	// ---- defaults -----------------------------------------------------------

	public function testGetDefaultsCoversEveryField(): void {
		$defaults = configSchema::getDefaults();

		$this->assertSame(count(configSchema::getAllFields()), count($defaults));

		foreach (configSchema::getAllFields() as $dotpath => $meta) {
			$this->assertSame($meta['default'], $defaults[$dotpath], "{$dotpath} default mismatch");
		}
	}

	public function testHasFieldOnlyKnowsDeclaredFields(): void {
		$this->assertTrue(configSchema::hasField('PAGE_DEF'));
		$this->assertFalse(configSchema::hasField('NOT_A_REAL_SETTING'));

		// An immutable global is deliberately not editable.
		$this->assertFalse(configSchema::hasField('STATIC_URL'));
	}
}
