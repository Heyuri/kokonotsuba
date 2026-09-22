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
  connection. They are exercised against a real database, not here — see
  `integration/repositoryHelpers.php`.
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

## Running the JavaScript tests

`static/js/quoteLookup.js` holds the rules a text quote is matched by and the client that asks
the post API about the ones a page cannot answer. It is plain CommonJS with no DOM in it, so
node runs it directly - no package manager, nothing to install:

```sh
node --test tests/js/                              # unit tests
node tests/js/quoteLookup.fuzz.js                  # 2000 iterations/target, random seed
node tests/js/quoteLookup.fuzz.js --iterations=50000 --seed=12345
node tests/js/quoteLookup.fuzz.js --target=resolver
```

The fuzzer checks the parsers against their contracts, the matcher against a plain re-statement
of the same rules, and the API client against a server that is slow, missing, broken and lying
at once - the cache, the queue and the back-off must stay inside their bounds and every lookup
must settle.

The same rules live in PHP as `Kokonotsuba\quote_link\textQuoteMatcher`, and
`tests/integration/textQuotes.php` runs both against the same threads, so a change to one side
that the other did not get fails there.

## Running the browser check

What only a DOM can answer - a comment read back as its lines, an attachment read by its full
name rather than the truncated one, which quote needs the API, what a hover puts on the page -
is checked in a real browser against real board markup, with the API stubbed:

```sh
firefox --headless --screenshot /tmp/quoteHover.png --window-size=1100,600 \
    tests/browser/quoteHover.html
```

The page shows PASS or FAIL per case with a count at the top, so the screenshot is the report;
opening the file by hand works the same way, with the details in the console.

## Layout

```
tests/
  bootstrap.php          Loads autoloader + Puchiko + lib_tripcode; warnings→exceptions
  run.php                Unit-test CLI entry point
  fuzz.php               Fuzzer CLI entry point + the helper-function targets
  fuzz/                  Further targets, one domain per file (config, debug bar,
                         rendering, page rebuilding); every *.php here is included
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
  integration/           Needs a live MariaDB; NOT picked up by run.php
    migrations.php       Migration runner: baseline, reconcile, detect, rollback
    install.php          The installer against a scratch app root: files, rows,
                         refusal over a live database, rollback and retry
    roleLevelMigration.php
    deletionSemantics.php
    loginAttempts.php    Staff login brute-force ledger: counting, clearing, warning
    bans.php             Ban enforcement: scope, checkpoints, wildcards, visitor
                         tokens, seen state, appeals, listing
    textQuotes.php       Text quote lookups: the queries behind them, the page
                         script and the in-memory rules, on the same threads
    repositoryHelpers.php  baseRepository's shared query helpers, and the repository
                           methods built on them
  js/                    JavaScript tests; run with node, NOT picked up by run.php
    quoteLookup.test.js  Quote matching rules and the post API client
    quoteLookup.fuzz.js  Fuzzer for the same, plus random API traffic
  browser/               Opened in a browser, not run by anything
    quoteHover.html      The DOM half of the hover previews, with the API stubbed
  stress/                Concurrency tests, run directly; NOT picked up by run.php
    quoteLookups.php         Text quote lookups under load, against a running install
    threadFragmentCache.php  Forked workers against one fragment cache directory
    fragmentTraffic.php      HTTP traffic against a running scratch install
  fixtures/
    global/              Committed homoglyph map so normalisation tests stay offline
```

## Running the stress tests

What only shows when requests overlap. `threadFragmentCache.php` needs nothing but
`pcntl`: it forks readers, repliers, editors and config saves onto one cache
directory and checks that no read is torn, no temp file is left, and that nothing
stale is still served once the traffic stops.

```sh
php tests/stress/threadFragmentCache.php --workers=16 --seconds=30
php tests/stress/threadFragmentCache.php --threads=4 --render-ms=20   # more contention
```

`fragmentTraffic.php` drives a running install over HTTP: reads of many different
pages over a cold cache, then the same with votes, replies, new threads and
deletions mixed in on several boards at once,
then votes timed to land while their thread's page is being drawn. After each
phase every page is fetched as the cache has it and again with the cache emptied;
the two must match. It also fails on any unhandled error page and on a new thread
that does not point at its own OP, which is how it caught posting races that have
nothing to do with the cache. It posts and deletes, so it refuses any database not named
`koko_test*` / `koko_bench*`, and it wants a server that answers in parallel
(`PHP_CLI_SERVER_WORKERS=16 php -S ...`).

```sh
KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_bench_fuzz;charset=utf8mb4' \
KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
php tests/stress/fragmentTraffic.php --base=http://127.0.0.1:8097 \
    --storages=/path/to/scratch/app/global/board-storages
```

`quoteLookups.php` loads the text quote endpoint the way a board full of readers would. Every
lookup is answered once on its own and recorded, then all of them are replayed at once - a
lookup is a pure read, so no answer may change - and the worst case is aimed at on purpose:
needles nothing holds, on the longest threads, where the search runs its whole window for
nothing. It times ordinary page loads alone and again while the lookups run flat out, which is
what "does this strain the site" means in practice, and it throws hostile parameters at the
endpoint (overlong, wildcard, binary, repeated). It only reads, but it still refuses any
database not named `koko_test*` / `koko_bench*`, and it wants a server that answers in parallel.

```sh
KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_bench;charset=utf8mb4' \
KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
php tests/stress/quoteLookups.php --base=http://127.0.0.1:8099 \
    --threads=40 --seconds=20 --concurrency=24
```

The install it points at needs a session store its own user can write (`php -S -d
session.save_path=...`), or PHP quietly keeps no session at all and the phase that measures what
carrying one costs measures nothing.

A lookup is one statement, and `--max-statements` is what keeps it that way. The count it
measures is the whole request, so it also covers the board being booted and the post being
fetched and drawn: a miss costs four statements, three of which are the request itself.

It fails on a 5xx, on an answer that changed under load, on a lookup that returns a post holding
neither the text nor a file of that name, and when `--max-miss-ms`, `--max-statements` or
`--max-slowdown` is exceeded.

## Running the integration tests

`tests/run.php` scans `tests/unit/` only. Anything needing a live database lives
in `tests/integration/` and is executed directly, reading its connection from the
environment. Point these at a throwaway database — they drop and recreate tables.

```sh
KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
php tests/integration/migrations.php
php tests/integration/bans.php
php tests/integration/anonIp.php
```

`anonIp.php` also sweeps `information_schema` for columns that look like an
address and fails when one is not registered in `anonIpTargets`, so a new table
storing an IP is caught the moment its migration lands.

Exit code is `0` on success, `1` on failure, `2` when no database is reachable.

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
