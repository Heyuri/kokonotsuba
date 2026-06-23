<?php

/**
 * Shared bootstrap for the test suite and the fuzzer.
 *
 * Loads just enough of the application to exercise its pure, dependency-free
 * units (the Puchiko helper functions, the userRole enum, …) without booting
 * the full request lifecycle, a database, or a web server.
 *
 * Anything that needs the DI container, sessions or HTTP is out of scope here:
 * those are integration concerns, not unit tests.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Surface notices/warnings as exceptions so tests catch sloppy code paths
// (e.g. a function reading an undefined array key on a fuzzed input).
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
	if (!(error_reporting() & $severity)) {
		return false; // respect @-suppression
	}
	throw new \ErrorException($message, 0, $severity, $file, $line);
});

$root = dirname(__DIR__);

// Core application autoloader (maps Kokonotsuba\… and Puchiko\… to /code).
require $root . '/autoload.php';

// Puchiko helpers are namespaced *functions*, not autoloaded classes, so the
// app requires them explicitly. Do the same here.
require $root . '/code/Puchiko/includes.php';

// normalize.php is not part of Puchiko/includes.php; load it for filter tests.
require $root . '/code/Puchiko/normalize.php';

// normalize\mapHomoglyphs() resolves its lookup table relative to
// getBackendGlobalDir(); without a cached map it would fetch Unicode's
// confusables.txt over the network. Define the path helper here (the app's
// paths.php is not loaded in unit context) so it points at a committed fixture
// map — keeping the suite hermetic and offline.
if (!function_exists('getBackendGlobalDir')) {
	function getBackendGlobalDir(): string {
		return __DIR__ . '/fixtures/global/';
	}
}

// Some pure module classes (tripcodeProcessor, messageUtility, …) call
// Kokonotsuba\libraries\generateTripcode. That lib is required manually by the
// app; load it directly here (it has no further dependencies).
require $root . '/code/Kokonotsuba/libraries/lib_tripcode.php';

// Many module classes emit user-facing strings via Kokonotsuba\libraries\_T(),
// which in the full app resolves against the loaded language instance. The unit
// context doesn't boot i18n, so load a stub that echoes the translation key —
// enough to exercise code paths that build messages or throw translated
// exceptions, without dragging in the language layer. (Kept in its own file
// because it declares a namespaced function.)
require __DIR__ . '/framework/i18nStub.php';

/**
 * Absolute path to the backend root, for tests that need to reach app files
 * directly (e.g. requiring a non-autoloaded module class).
 */
define('KOKO_TEST_ROOT', $root);

/**
 * Require a module source file by its path relative to the module/ directory.
 *
 * Module classes live in Kokonotsuba\Modules\{name}\ and are NOT autoloaded —
 * the module engine include_once's them at runtime. Module unit tests do the
 * same via this helper, e.g. requireModuleFile('notes/noteService.php').
 */
function requireModuleFile(string $relativePath): void {
	require_once KOKO_TEST_ROOT . '/module/' . ltrim($relativePath, '/');
}

// Test framework (not autoloaded — these live outside /code).
require __DIR__ . '/framework/AssertionFailedException.php';
require __DIR__ . '/framework/TestCase.php';
require __DIR__ . '/framework/TestRunner.php';
require __DIR__ . '/framework/Fuzzer.php';

return $root;
