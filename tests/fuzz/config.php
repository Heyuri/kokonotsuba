<?php

/**
 * Fuzz targets for the config editor.
 *
 * Included by tests/fuzz.php with $fuzzer in scope.
 *
 * The interesting surface here is everything that eats untrusted input: saveOverrides() takes a
 * raw POST array straight off the wire (the form is only a suggestion — anything can be posted),
 * and the legacy converter eats whatever a years-old, hand-edited PHP config file happens to hold.
 * Both must coerce, clamp and drop rather than crash, and must never store something the schema
 * doesn't declare or the database column can't hold.
 */

use Koko\Tests\Framework\Fuzzer;
use Koko\Tests\Framework\InMemoryConfigRepository;
use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\configService;
use Kokonotsuba\config\legacyConfigConverter;

use const Kokonotsuba\GLOBAL_BOARD_UID;

$configFields = configSchema::getAllFields();
$configPaths = array_keys($configFields);

/** A value of roughly the right shape for a field — or deliberately of the wrong one. */
$fuzzValueFor = static function (array $meta): mixed {
	// One in four values is hostile: the wrong type entirely for this field.
	if (Fuzzer::int(0, 3) === 0) {
		return Fuzzer::pick([
			Fuzzer::nastyString(20),
			'', '0', '-0', 'null', 'true', 'NaN', 'Infinity',
			'9999999999999999999999',      // beyond PHP_INT_MAX
			'-9999999999999999999999',
			'1e309',                        // beyond float range
			'[]', '{}', '[1,2,', '{"a":',   // JSON, valid and truncated
			'<script>alert(1)</script>',
			"\x00\x01\x02",                 // control bytes
			str_repeat('x', 500),
		]);
	}

	switch ($meta['type']) {
		case configSchema::TYPE_BOOL:
			return Fuzzer::pick(['1', 'on', 'true', 'yes', '0', 'off', 'false', '', 'maybe']);

		case configSchema::TYPE_INT:
			return (string)Fuzzer::int(-1000, 1000);

		case configSchema::TYPE_ARRAY:
			return Fuzzer::pick([
				'[]',
				'{}',
				json_encode([Fuzzer::nastyString(10), Fuzzer::nastyString(10)]),
				json_encode([Fuzzer::nastyString(6) => Fuzzer::nastyString(10)]),
				json_encode(['nested' => ['deep' => 1]]),
				'not json at all',
			]);

		default:
			return Fuzzer::nastyString(30);
	}
};

/** A random POST body: a subset of the real fields, plus junk keys that aren't settings at all. */
$fuzzSubmission = static function () use ($configPaths, $configFields, $fuzzValueFor): array {
	$submitted = [];

	foreach ($configPaths as $dotpath) {
		if (Fuzzer::int(0, 2) === 0) {
			continue; // absent — for a checkbox that means "unchecked"
		}
		$submitted[configSchema::inputKey((string)$dotpath)] = $fuzzValueFor($configFields[$dotpath]);
	}

	// Keys the schema never declared, including ones naming immutable globals.
	$junk = Fuzzer::int(0, 3);
	for ($i = 0; $i < $junk; $i++) {
		$submitted[Fuzzer::pick(['STATIC_URL', 'AuthLevels', 'evil', Fuzzer::nastyString(8)])] = Fuzzer::nastyString(20);
	}

	return [$submitted];
};

/**
 * Save a fuzzed submission and hand back what actually got stored, so the invariants can inspect
 * it. An InvalidArgumentException is a legitimate outcome (an array field whose JSON is broken),
 * not a crash — anything else escaping is a bug.
 */
$saveFuzzed = static function (array $submitted) use ($configFields): array {
	$repository = new InMemoryConfigRepository();
	$service = new configService($repository);

	try {
		$service->saveOverrides(3, $submitted);
	} catch (InvalidArgumentException $e) {
		return ['rejected' => true, 'stored' => []];
	}

	return ['rejected' => false, 'stored' => $service->getOverrides(3)];
};

// saveOverrides: whatever is posted, only declared settings are stored, in their declared type,
// within their declared bounds, and always JSON-encodable (it goes into a JSON column).
$fuzzer->target(
	'config\\saveOverrides',
	$saveFuzzed,
	$fuzzSubmission,
	[
		['returns without crashing', fn($r) => is_array($r)],

		['only declared settings are stored', fn($r) => array_reduce(
			array_keys($r['stored']),
			fn(bool $ok, $dotpath) => $ok && configSchema::hasField((string)$dotpath),
			true
		)],

		['stored values match their declared type', function ($r) use ($configFields): bool {
			foreach ($r['stored'] as $dotpath => $value) {
				$type = $configFields[$dotpath]['type'];

				$ok = match ($type) {
					configSchema::TYPE_BOOL  => is_bool($value),
					configSchema::TYPE_INT   => is_int($value),
					configSchema::TYPE_ARRAY => is_array($value),
					default                  => is_string($value),
				};

				if (!$ok) {
					return false;
				}
			}
			return true;
		}],

		['integers never fall below their minimum', function ($r) use ($configFields): bool {
			foreach ($r['stored'] as $dotpath => $value) {
				$meta = $configFields[$dotpath];

				if ($meta['type'] !== configSchema::TYPE_INT || $meta['min'] === null) {
					continue;
				}

				if ($value < $meta['min']) {
					return false;
				}
			}
			return true;
		}],

		// The column is JSON: a value that cannot be encoded would be lost on write.
		['everything stored survives JSON encoding', fn($r) => json_encode($r['stored']) !== false],

		// Storing a value equal to what the scope inherits is pointless and breaks inheritance.
		['nothing equal to the default is stored', function ($r) use ($configFields): bool {
			foreach ($r['stored'] as $dotpath => $value) {
				if (json_encode($value) === json_encode($configFields[$dotpath]['default'])) {
					return false;
				}
			}
			return true;
		}],
	]
);

// inputKey: the form renderer and the save handler both derive this from the dot-path with no
// stored mapping, so it must always be a plain HTML-safe identifier, whatever it is given.
$fuzzer->target(
	'config\\inputKey',
	fn(string $dotpath) => configSchema::inputKey($dotpath),
	fn() => [Fuzzer::pick([
		Fuzzer::nastyString(30),
		'A.B.C',
		'....',
		'',
		'123numeric.start',
		'UPPER_SNAKE',
		'modules.someModule.SOME_KEY',
	])],
	[
		['returns a string', fn($r) => is_string($r)],
		['is deterministic', fn($r, $a) => $r === configSchema::inputKey($a[0])],
		['is empty or a plain identifier', fn($r) => $r === '' || (bool)preg_match('/^[a-z0-9][a-zA-Z0-9]*$/', $r)],
		['contains no dots or brackets', fn($r) => !preg_match('/[.\[\]"\'<>]/', $r)],
	]
);

// The legacy converter: a decade-old hand-edited config file can hold anything at all. It must
// still yield only declared settings, correctly typed, and never explode on a value of the wrong
// shape (a string where an array was expected, and so on).
$fuzzer->target(
	'config\\legacyConfigConverter::extractOverrides',
	fn(array $legacy) => legacyConfigConverter::extractOverrides($legacy, configSchema::getDefaults()),
	function () use ($configPaths, $configFields): array {
		$legacy = [];

		foreach ($configPaths as $dotpath) {
			if (Fuzzer::int(0, 2) === 0) {
				continue; // the old file simply didn't set this one
			}

			// Old files stored raw PHP values, of every type and frequently the "wrong" one.
			$value = Fuzzer::pick([
				Fuzzer::nastyString(20),
				Fuzzer::int(-100, 100),
				Fuzzer::bool(),
				null,
				[],
				['a' => 1, 'b' => 2],
				[['width' => 728, 'height' => 90]],
				'kokoimg.tpl',
				'WEBM|MP4',
				1, 0, '1', '0', '',
			]);

			if (str_starts_with((string)$dotpath, 'modules.')) {
				$segments = explode('.', (string)$dotpath);
				$legacy['ModuleSettings'][end($segments)] = $value;
				continue;
			}

			// Reproduce the legacy nesting for dotted core keys (ModuleList.catalog, …).
			$segments = explode('.', (string)$dotpath);
			if (count($segments) === 1) {
				$legacy[$dotpath] = $value;
			} else {
				$legacy[$segments[0]][$segments[1]] = $value;
			}
		}

		// Keys the schema no longer knows, and immutable globals that were in the same array.
		$legacy['STATIC_URL'] = 'https://static.example.net/';
		$legacy[Fuzzer::nastyString(8)] = Fuzzer::nastyString(8);

		return [$legacy];
	},
	[
		['returns an array', fn($r) => is_array($r)],

		['only declared settings are migrated', fn($r) => array_reduce(
			array_keys($r),
			fn(bool $ok, $dotpath) => $ok && configSchema::hasField((string)$dotpath),
			true
		)],

		['immutable globals are never migrated', fn($r) => !isset($r['STATIC_URL']) && !isset($r['AuthLevels'])],

		['migrated values match their declared type', function ($r) use ($configFields): bool {
			foreach ($r as $dotpath => $value) {
				$ok = match ($configFields[$dotpath]['type']) {
					configSchema::TYPE_BOOL  => is_bool($value),
					configSchema::TYPE_INT   => is_int($value),
					configSchema::TYPE_ARRAY => is_array($value),
					default                  => is_string($value),
				};

				if (!$ok) {
					return false;
				}
			}
			return true;
		}],

		['migrated integers respect their minimum', function ($r) use ($configFields): bool {
			foreach ($r as $dotpath => $value) {
				$meta = $configFields[$dotpath];

				if ($meta['type'] !== configSchema::TYPE_INT || $meta['min'] === null) {
					continue;
				}

				if ($value < $meta['min']) {
					return false;
				}
			}
			return true;
		}],

		['migrated overrides survive JSON encoding', fn($r) => json_encode($r) !== false],

		// A migration that is not idempotent would drift every time it is re-run.
		['is idempotent', fn($r, $a) => $r === legacyConfigConverter::extractOverrides($a[0], configSchema::getDefaults())],
	]
);

// The migrated rows must be loadable by the very service that will serve them.
$fuzzer->target(
	'config\\migrateThenResolve',
	function (array $legacy) {
		$defaults = configSchema::getDefaults();
		$globalOverrides = legacyConfigConverter::extractOverrides($legacy, $defaults);

		$repository = new InMemoryConfigRepository();
		if ($globalOverrides !== []) {
			$repository->saveOverridesForBoardUid(GLOBAL_BOARD_UID, $globalOverrides);
		}

		$service = new configService($repository);

		return [
			'migrated' => $globalOverrides,
			'resolved' => $service->getEffectiveValues(GLOBAL_BOARD_UID),
		];
	},
	function () use ($configPaths): array {
		$legacy = [];
		foreach ($configPaths as $dotpath) {
			if (Fuzzer::int(0, 3) !== 0) {
				continue;
			}
			$value = Fuzzer::pick([Fuzzer::nastyString(15), Fuzzer::int(-50, 50), Fuzzer::bool(), [], null]);

			if (str_starts_with((string)$dotpath, 'modules.')) {
				$segments = explode('.', (string)$dotpath);
				$legacy['ModuleSettings'][end($segments)] = $value;
			} else {
				$segments = explode('.', (string)$dotpath);
				if (count($segments) === 1) {
					$legacy[$dotpath] = $value;
				} else {
					$legacy[$segments[0]][$segments[1]] = $value;
				}
			}
		}
		return [$legacy];
	},
	[
		['every migrated value is what the editor then reads back', function ($r): bool {
			foreach ($r['migrated'] as $dotpath => $value) {
				if (json_encode($r['resolved'][$dotpath] ?? null) !== json_encode($value)) {
					return false;
				}
			}
			return true;
		}],
		['every schema field resolves to a value', fn($r) => count($r['resolved']) === count(configSchema::getAllFields())],
	]
);
