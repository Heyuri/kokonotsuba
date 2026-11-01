# Repo map

Backend of the Kokonotsuba imageboard engine, cloned into the web root. Boards live in
`boards/{identifier}/` (created at install, not in the repo). Rules: `CLAUDE.md`.

| Folder | What it holds |
|---|---|
| `bootstrap/` | Only `background.php`, the context a background task boots in; the boot itself is `code/Kokonotsuba/kernel/` |
| `code/Kokonotsuba/` | Core application, `Kokonotsuba\` namespace, one directory per concern (`ban/`, `post/`, `routers/`, ...) |
| `code/Puchiko/` | Low-level utility functions (`Puchiko\` namespace) and the background-task classes |
| `configs/` | Editable config schema and defaults, one file per group, built with `_fieldTypes.php` helpers |
| `global/` | `globalconfig.php` (board-immutable settings, tracked) and `siteSettings.php` (per install, untracked) |
| `migrations/` | Core schema and data migrations, run by `Utilities/migrate-cli.php` |
| `module/` | One directory per feature module (adminBan, anonIp, debugBar, soudane, edit, ...), each with its own `CLAUDE.md` where it has rules |
| `static/` | js, css, image, audio, html; deployed separately, referenced via `STATIC_URL` |
| `templates/` | Page templates: `admin/`, `global/`, and the board themes |
| `tests/` | `run.php` unit harness, `fuzz.php`, `integration/`, `stress/`, `browser/`, `js/` |
| `Utilities/` | `*-cli.php` scripts run with `php` outside the web request |

Root files: `koko.php` front controller; `install.php` installer, deleted after use; `autoload.php`;
`paths.php`; `tables.php` (logical to real table names); `databaseSettings.php` (credentials,
untracked, copied from `databaseSettings.example.php`).

## Where do I find X

- Boot and the DI container wiring: `code/Kokonotsuba/kernel/kernel.php` and `kernel/providers/`
- Mode dispatch and route classes: `code/Kokonotsuba/routers/modeHandler.php`, the mode table `routers/routeTable.php`, `code/Kokonotsuba/routers/routes/`
- Config resolution: `code/Kokonotsuba/config/`; schema files in `configs/` and `module/{name}/config.php`
- Repositories and the PDO wrapper: `code/Kokonotsuba/database/`
- Module engine, hook dispatcher, listener traits: `code/Kokonotsuba/module_classes/` (`traits/listeners/`)
- Post storage, text formats, markers: `code/Kokonotsuba/post/`; rendering: `code/Kokonotsuba/renderers/`
- Bans, checkpoints, appeals: `code/Kokonotsuba/ban/`; the ban admin UI: `module/adminBan/`
- Roles and policies: `code/Kokonotsuba/userRole.php`, `code/Kokonotsuba/policy/`; login throttling: `code/Kokonotsuba/log_in/`
- Language strings: `code/Kokonotsuba/lang/en_US.php` (also `es_ES`, `fr_FR`)
- Thread fragment cache: `code/Kokonotsuba/cache/thread_fragment/`; thumbnails: `code/Kokonotsuba/thumb/`
- Installer logic and the nginx rule generator: `code/Kokonotsuba/install/`
- Action log types and references: `code/Kokonotsuba/action_log/`
- Module front-end JS: `static/js/module/`; widget menus: `static/js/postWidget.js`, `static/js/attachmentWidget.js`
