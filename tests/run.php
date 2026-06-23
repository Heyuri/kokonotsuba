<?php

/**
 * Unit test runner.
 *
 * Usage:
 *   php tests/run.php                  Run every *Test.php under tests/unit/
 *   php tests/run.php --filter=Strings Only run tests whose Class::method
 *                                      contains "Strings"
 *   php tests/run.php --no-color       Disable ANSI colour
 *   php tests/run.php path/to/dir      Scan a specific directory instead
 *
 * Exit code is 0 when all tests pass, 1 otherwise — suitable for CI.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

require __DIR__ . '/bootstrap.php';

use Koko\Tests\Framework\TestRunner;

$filter = '';
$colour = null;
$dirs = [];

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--filter=')) {
		$filter = substr($arg, strlen('--filter='));
	} elseif ($arg === '--no-color' || $arg === '--no-colour') {
		$colour = false;
	} elseif ($arg === '--color' || $arg === '--colour') {
		$colour = true;
	} elseif (!str_starts_with($arg, '--')) {
		$dirs[] = $arg;
	} else {
		fwrite(STDERR, "Unknown option: $arg\n");
		exit(2);
	}
}

if (!$dirs) {
	$dirs = [__DIR__ . '/unit'];
}

$runner = new TestRunner($dirs, $filter, $colour);
exit($runner->run());
