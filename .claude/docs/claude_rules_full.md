# Claude rules, full text

The root `CLAUDE.md` is the short form that loads into every session. The long explanations live
here and in the subfolder `CLAUDE.md` files, which load only when you work in that folder:

- Install layout and the installer: `code/Kokonotsuba/install/CLAUDE.md`
- Request lifecycle, routes, errors, background tasks: `code/Kokonotsuba/routers/CLAUDE.md`
- Configuration cascade, schema files, credentials and table names: `configs/CLAUDE.md`
- Database layer and repositories: `code/Kokonotsuba/database/CLAUDE.md`
- Schema and data migrations: `migrations/CLAUDE.md`
- Modules, hooks, listeners and widget menus: `module/CLAUDE.md`
- Post text storage, formats and markers: `code/Kokonotsuba/post/CLAUDE.md`
- Bans, checkpoints, warnings and mutes: `code/Kokonotsuba/ban/CLAUDE.md`
- Roles, policies and login throttling: `code/Kokonotsuba/policy/CLAUDE.md`
- Action log types and references: `code/Kokonotsuba/action_log/CLAUDE.md`
- Rendering, i18n, overboard and the thread fragment cache: `code/Kokonotsuba/renderers/CLAUDE.md`
- IP anonymization details: `module/anonIp/CLAUDE.md`; debug bar: `module/debugBar/CLAUDE.md`

The sections below had no single home, so they stay here word for word.

## No build tooling — but there IS a test suite

There is **no Composer, no PHPUnit, no package manager, no build step**. Code is plain PHP executed directly by the web server. The only `package.json` is in `.kilo/` (an editor plugin), unrelated to the app. Do not add a build pipeline unless asked.

The repo ships its own zero-dependency harness, run straight from the `php` CLI (see `tests/README.md`):

```
php tests/run.php                    # every *Test.php under tests/unit/
php tests/run.php --filter=Strings   # only Class::method names matching
php tests/run.php --no-color         # disable ANSI colour
php tests/fuzz.php                   # fuzz targets, 1000 iterations each
```

`tests/run.php` scans **only** `tests/unit/`. Anything touching a `*Repository` needs a live MariaDB connection and lives in `tests/integration/`, which the runner does not pick up — those scripts are executed directly and read their connection from `KOKO_TEST_DSN` / `KOKO_TEST_USER` / `KOKO_TEST_PASS`. `tests/integration/repositoryHelpers.php` builds the throwaway schema they share.

To sanity-check PHP syntax on a file: `php -l path/to/file.php`.

### Local dev database access

The local MariaDB has a `claude` account for integration tests and query benchmarking. It is
**localhost-only and scoped to scratch databases** — it can create and drop anything named
`koko_test*` or `koko_bench*`, and can only read the real `kokonotsuba` database. The password is
deliberately a throwaway: it grants nothing that matters and is not the application's own
credential (that stays in `databaseSettings.php`, which is not committed with real values).

```
KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4'
KOKO_TEST_USER='claude'
KOKO_TEST_PASS='claude_local_dev'
```

If the login fails, recreate it as root (`sudo mariadb`):

```sql
CREATE USER IF NOT EXISTS 'claude'@'localhost' IDENTIFIED BY 'claude_local_dev';
ALTER USER 'claude'@'localhost' IDENTIFIED BY 'claude_local_dev';
GRANT ALL PRIVILEGES ON `koko\_test%`.* TO 'claude'@'localhost';
GRANT ALL PRIVILEGES ON `koko\_bench%`.* TO 'claude'@'localhost';
GRANT SELECT ON `kokonotsuba`.* TO 'claude'@'localhost';
FLUSH PRIVILEGES;
```

Benchmark and integration work goes in a `koko_bench*` / `koko_test*` database — never against
`kokonotsuba` itself.

## Autoloading & namespaces

`autoload.php` is a custom PSR-4-style autoloader: a fully-qualified class name maps directly to a file under `code/`, with `\` → `/`. Two roots live there:

- `Kokonotsuba\…` → `code/Kokonotsuba/…` — the core application.
- `Puchiko\…` → `code/Puchiko/…` — low-level utility **functions** (strings, arrays, files, json, html, request, normalize, misc) plus the background-task classes in `background.php`. The functions are namespaced *functions*, not autoloaded classes, so `code/Puchiko/includes.php` `require`s them all explicitly via `bootstrap/libraryIncludes.php`. The legacy `lib_*.php` files in `code/Kokonotsuba/libraries/` are likewise required manually there, not autoloaded.

Module classes use the namespace `Kokonotsuba\Modules\{moduleName}\` and are **not** autoloaded — the module engine `include_once`s their files explicitly (see below), and a module's own helper files are `require_once`d from the top of `moduleMain.php` / `moduleAdmin.php`.

`code/Kokonotsuba/kokoLibrary.php` (the former `PMCLibrary`) is the static access point for the two request-scoped singletons that predate the DI container: the `SimpleLogger` instances and the `LanguageLoader`. Everything else goes through the container.

## IP anonymization

Every column that holds an address is registered in `module/anonIp/anonIpTargets.php`, so **adding a table that stores an IP means adding an `anonIpTarget` and nothing else** (`tests/integration/anonIp.php` fails otherwise). Anonymization hashes rows in place, so **any lookup keyed on an address must accept both forms**: `ipAnonymizer::storedForms($ip)`. Stored format, salt rules, the two exceptions and scheduling: `module/anonIp/CLAUDE.md`.

## Debug bar & profiling

`module/debugBar` (off by default) draws request timings into the footer of live pages. Adding `kokoProfile=1` downloads a speedscope profile carrying every statement the request ran, which is the quickest way to see where a page's queries come from. Details: `module/debugBar/CLAUDE.md`.

## Static assets

`static/` (js, css, image, audio, html) is deployed **outside** the backend to a web-accessible location; the app references it via `STATIC_URL`. Front-end JS in `static/js/` is vanilla (no bundler), one file per module under `static/js/module/`. Uploaded files live per-board under each board's directory, or in a central CDN dir when `USE_CDN` is enabled.

## CLI utilities

`Utilities/*-cli.php` are standalone scripts run with `php`, outside the web request flow. `migrateCountryFlags-cli` (geoip backfill) is **not** run by `up`; the import CLIs are hand-driven forms of upgrades `up` already performs, kept for dry runs, previews and non-default sources.
