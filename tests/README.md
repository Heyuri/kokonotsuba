# Tests

A small, **zero-dependency** test harness for Kokonotsuba. The project ships no
Composer, PHPUnit, or build step, so this suite is plain PHP run directly with
the `php` CLI — nothing to install.

It covers the pure, side-effect-free units of the codebase:

- The `Puchiko` helper functions (strings, arrays, filter normalisation) and the
  `userRole` enum.
- The **module logic layer** — the libs, value-object DTOs, policies,
  processors, renderers and services that hold the modules' actual business
  logic (tripcode generation, capcode rendering, permission policies, perceptual
  hashing, vote tallying, private-message parsing, …).

### What is *not* covered, and why

Two layers of the module system are integration concerns, not unit-testable in
isolation, so they are deliberately excluded:

- **`*Repository` classes** extend `baseRepository` and need a live PDO/MariaDB
  connection. They are exercised against a real database, not here.
- **`moduleMain.php` / `moduleAdmin.php`** are constructed with the full
  `moduleContext` (board, template engine, DI container, request) and mostly
  register hooks and render templates. Testing them means booting the request
  lifecycle — an integration test.

The DB-wiring factory functions (`getXService()` in the various `*Lib.php`
files) are likewise just `new`-up glue around a DB connection and are skipped.

Where a class under test depends on a repository or service, the test injects a
small **stub** (an anonymous subclass with an empty constructor and canned
return values) so the unit's own logic is tested without the DB. See
`tests/unit/Modules/NotePolicyTest.php` and `SoudaneServiceTest.php`.

## Running the unit tests

```sh
php tests/run.php                    # run everything under tests/unit/
php tests/run.php --filter=Strings   # only Class::method names containing "Strings"
php tests/run.php --no-color         # disable ANSI colour (for CI logs)
php tests/run.php tests/unit/Puchiko # scan a specific directory
```

Exit code is `0` when all tests pass, `1` otherwise — drop it straight into CI.

Output is a progress line of `.` (pass) / `F` (fail) / `E` (error), followed by
details for every non-pass and a summary line.

## Running the fuzzer

The fuzzer throws large volumes of hostile, randomly-generated input (multibyte
text, emoji, control/zero-width characters, HTML, malformed URLs, empty strings)
at the same functions and checks that broad **invariants** always hold — output
stays valid UTF-8, escaping never leaks a live `<script`, normalisation is
idempotent, and so on. It catches crashes and edge cases that hand-written
examples miss.

```sh
php tests/fuzz.php                     # 1000 iterations/target, random seed
php tests/fuzz.php --iterations=20000  # hammer harder
php tests/fuzz.php --seed=12345        # reproduce an exact run
php tests/fuzz.php --target=autoLink   # only fuzz matching targets
```

Every run prints its seed up front. When a failing input is found it reports the
target, the violated invariant (or the exception), and the exact input
`var_export`'d so you can paste it into a regression test. Exit code is `0` when
no failing input is found, `1` otherwise.

The test bootstrap promotes PHP warnings/notices to exceptions, so a fuzzed input
that makes a function read an undefined key or mis-handle an encoding is caught
as a crash rather than silently passing.

## Layout

```
tests/
  bootstrap.php          Loads autoloader + Puchiko + lib_tripcode; warnings→exceptions
  run.php                Unit-test CLI entry point
  fuzz.php               Fuzzer CLI entry point + target definitions
  framework/
    TestCase.php         Base class: assertions, setUp/tearDown
    TestRunner.php       Discovery + execution + coloured reporting
    Fuzzer.php           Property-based fuzzer + input generators
    i18nStub.php         Test stub for the _T() translation helper
    AssertionFailedException.php
  unit/
    Puchiko/             Tests for the helper functions
    Kokonotsuba/         Tests for core classes (e.g. userRole)
    Modules/             Tests for the module logic layer
  fixtures/
    global/              Committed homoglyph map so normalisation tests stay offline
```

### Testing a module class

Module classes live in `Kokonotsuba\Modules\{name}\` and are **not** autoloaded,
so require the file under test (and any class it references) with the
`requireModuleFile()` helper, relative to `module/`:

```php
protected function setUp(): void {
    requireModuleFile('notes/noteService.php'); // dependency referenced by the policy
    requireModuleFile('notes/notePolicy.php');  // class under test
}
```

To isolate a class from its DB-backed collaborators, inject an anonymous
subclass that skips the real constructor:

```php
$stub = new class extends noteService {
    public function __construct() {}                 // bypass the repo dependency
    public function noteOwnedByAccount(int $a, int $n): bool { return true; }
};
```

## Writing a unit test

Create `tests/unit/.../SomethingTest.php`. Any file ending in `Test.php` is
auto-discovered; any `public function test*()` becomes a test.

```php
<?php

namespace Koko\Tests\Unit\Puchiko;

use Koko\Tests\Framework\TestCase;

use function Puchiko\strings\formatFileSize;

final class ExampleTest extends TestCase {
    public function testKilobytes(): void {
        $this->assertSame('1 KB', formatFileSize(1024));
    }
}
```

Available assertions: `assertTrue/False`, `assertSame/NotSame`, `assertEquals`,
`assertNull/NotNull`, `assertStringContains/NotContains`, `assertMatchesRegex`,
`assertCount`, `assertContains`, `assertGreaterThan/LessThan`,
`assertIsString/IsArray`, `assertThrows`, plus `fail()` / `pass()`.

## Adding a fuzz target

In `tests/fuzz.php`, register the function under test with a generator and a
list of `[description, fn($result, $args): bool]` invariants:

```php
$fuzzer->target(
    'strings\\formatFileSize',
    'Puchiko\\strings\\formatFileSize',
    fn() => [Fuzzer::int(0, PHP_INT_MAX >> 1)],
    [
        ['returns a string',   fn($r) => is_string($r)],
        ['has a B/KB/MB unit', fn($r) => (bool)preg_match('/ (B|KB|MB)$/', $r)],
    ]
);
```

`Fuzzer` provides input generators: `int()`, `bool()`, `pick()`,
`nastyString()`, `url()`, `assoc()`. All draw from the seeded `mt_rand()`, so a
run is fully reproducible from its seed.
