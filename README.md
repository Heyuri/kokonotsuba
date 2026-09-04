# Kokonotsuba

## About Kokonotsuba
* https://kokonotsuba.github.io/

## Detailed installation instructions
* https://kokonotsuba.github.io/setup.html

## Required stack
Kokonotsuba is designed and tested on the following stack, and isn't guaranteed to work on any other stack.
- OS: Debian 10\~12
- Web server: nginx (but seems to work fine on Apache)
- DB: MariaDB
- PHP: PHP8.1\~PHP8.3

If you are going to suggest pull requests, please make sure the change would work on the above stack first.

## Dependencies
- mbstring
- pdo, pdo_mysql
- gd
- bcmath
- ffmpeg (video thumbnails)
- exiftool (stripping GPS metadata from uploads)

`install.php` checks for all of these and names the package to install for anything that is missing.

## Installation

Kokonotsuba is installed by cloning it into a web-accessible directory and opening `install.php` in a
browser. The installer checks the environment, writes the config, builds the database schema, and
creates the first board and the admin account. It changes nothing until every check passes, and
undoes what it did if a step fails.

### 1. Create the database

The installer connects to a database, it does not create one. In mariadb:

1. `CREATE DATABASE kokonotsuba CHARACTER SET utf8mb4;`

2. `CREATE USER 'koko_user'@'localhost' IDENTIFIED BY 'your_password';`

3. `GRANT ALL PRIVILEGES ON kokonotsuba.* TO 'koko_user'@'localhost';`

4. `FLUSH PRIVILEGES;`

A grant for `'koko_user'@'localhost'` only covers socket connections. If you are going to point the
installer at `127.0.0.1`, grant that host instead.

### 2. Clone it into the web root

`git clone https://github.com/Heyuri/kokonotsuba /var/www/html/kokonotsuba`

Everything lives under that one directory:

```
/var/www/html/kokonotsuba/
├── install.php   open this in a browser, then delete it
├── koko.php      backend entry point (each board's koko.php requires it)
├── static/       css, js and images, served directly
├── global/       site settings, error log, board storage - must not be web-readable
├── boards/       one directory per board, created by the installer and the admin panel
└── ...           the rest of the backend, also not web-readable
```

Boards are served from inside it: a board called `b` ends up at
`https://example.net/kokonotsuba/boards/b/`.

### 3. Permissions

The web server needs to read everything and write to a few directories. Replace `www-data` with the
user your web server runs as:

```
sudo chown -R www-data:www-data /var/www/html/kokonotsuba
sudo chmod -R 750 /var/www/html/kokonotsuba
sudo chmod -R 770 /var/www/html/kokonotsuba/global
sudo chmod 770 /var/www/html/kokonotsuba
```

The last line lets the installer write `databaseSettings.php` and create `boards/`; step 6 takes it
back. You do not have to get this exactly right first go: `install.php` lists every directory it
needs, says what is wrong with it, and prints the command that fixes it.

### 4. Keep the backend out of reach

The backend is inside the web root now, so the web server has to be told not to serve it.

**Apache** reads the `.htaccess` files that ship in the tree, so it is already covered. Make sure
`AllowOverride All` is set for the directory.

**nginx** ignores those files. Paste this into the `server` block, above the `location ~ \.php$`
block (regex locations match in the order written, and the PHP handler must not see these files
first), and reload - `install.php` prints the same rules with your own path already filled in:

```
location ~ ^/kokonotsuba/(bootstrap|code|configs|global|migrations|module|templates|tests|Utilities)/ {
    deny all;
}

location ~ ^/kokonotsuba/(autoload|databaseSettings|databaseSettings\.example|koko|paths|tables)\.php$ {
    deny all;
}

location ~ ^/kokonotsuba/\. {
    deny all;
}
```

The installer fetches its own `databaseSettings.php` and a few other files over HTTP and refuses to
run if the web server hands any of them out.

### 5. Run the installer

Open `https://example.net/kokonotsuba/install.php`. It reports on PHP, its extensions, the external
commands, every directory it needs and what is reachable from the web. Anything red has a command
next to it; fix it and reload the page.

The form asks for the database credentials, the admin account and the first board. The URLs are
filled in from the address you are reading the page on, so they only need changing if the site is
served from somewhere else - the exception is the home link, which defaults to the site root and is
where the "Home" link in every board's header goes. Submitting it:

- writes `databaseSettings.php` (your credentials) and `global/siteSettings.php` (your URLs, and a
  freshly generated tripcode salt and ID seed) - both untracked by git, so updates never touch them
- creates every table by running the migrations, the same ones an update runs
- creates the board directory, its database rows and the admin account in a single transaction

If anything fails, it says what failed, rolls the database work back, removes what it created and
puts any config file it replaced back the way it was. Fix the problem and submit again.

### 6. After installing

The installer prints these; they are here too:

```
rm /var/www/html/kokonotsuba/install.php
sudo chmod 640 /var/www/html/kokonotsuba/databaseSettings.php
sudo chown root:www-data /var/www/html/kokonotsuba && sudo chmod 750 /var/www/html/kokonotsuba
```

The last line takes back the write access on the top directory that the installer needed. Then open
your board (`https://example.net/kokonotsuba/boards/b/koko.php` for a board called `b`) - the first
request renders its index page. Log in through `?mode=admin` to add more boards.

#### Note
 - `global/siteSettings.php` is where your site's URLs and salts live from then on; everything else
   is edited from the admin panel. `global/siteSettings.example.php` documents the file
 - never change `TRIPSALT` or `IDSEED` once there are posts: every tripcode and poster ID changes
 - `databaseSettings.php` is not tracked by git. `databaseSettings.example.php` is, for setting an
   instance up by hand without the installer
 - if you are moving an existing install into this layout, keep your current `databaseSettings.php`
   and copy your `WEBSITE_URL`, `STATIC_URL`, `STATIC_PATH`, `TRIPSALT` and `IDSEED` out of
   `globalconfig.php` into `global/siteSettings.php` rather than running the installer again

## Updating

Code updates come from `git pull` (or from unpacking a release), then the migrator applies the matching database changes. It doesn't need git itself, so a release tarball updates the same way a clone does. All of the commands below are run from the backend directory.

### First update on an existing install

An install made before the migrator existed has no record of what's already been applied, so it needs baselining once before anything else.

1. Back up your database. Nothing here takes a backup for you, and database changes can't be undone.

2. Three files you edited used to be tracked by git, and the pull will refuse to run over your copies of them. Set them aside, then put git's copies back so the pull can proceed:

   ```
   mkdir -p ../koko-upgrade-backup
   cp databaseSettings.php global/globalconfig.php global/globalBoardConfig.php ../koko-upgrade-backup/
   git checkout -- databaseSettings.php global/globalconfig.php global/globalBoardConfig.php
   ```

   `global/board-configs/board-*.php` and `global/board-storages/` were never tracked, so they stay where they are.

3. Pull the code: `git pull`

4. Put your credentials back, and the old site-wide config where the upgrade can read it (it is untracked now, so neither will conflict again):

   ```
   cp ../koko-upgrade-backup/databaseSettings.php databaseSettings.php
   cp ../koko-upgrade-backup/globalBoardConfig.php global/globalBoardConfig.php
   ```

5. Copy `WEBSITE_URL`, `STATIC_URL`, `STATIC_PATH`, `TRIPSALT` and `IDSEED` (and the `CDN_*` keys if you use them) out of your backed-up `globalconfig.php` into a new `global/siteSettings.php`, following `global/siteSettings.example.php`. The values in `globalconfig.php` are now only defaults.

6. See what would change without changing anything: `php Utilities/migrate-cli.php baseline --dry-run`

7. If it looks right, run it: `php Utilities/migrate-cli.php baseline`

This adds whatever your database is missing, records what it already had, and leaves anything newer for the next step. It won't drop or overwrite anything.

8. Apply whatever is left: `php Utilities/migrate-cli.php up`

This is also where your old config files, flat-file bans and HTML-stored posts are carried into the database. Once `up` reports nothing pending, `global/globalBoardConfig.php` and `global/board-configs/` can go.

9. Copy the new `static/` over the directory `STATIC_PATH` points at, then rebuild every board from the admin panel so the pages pick up the converted posts.

### Every update after that

1. Pull the code: `git pull`

2. See what's pending: `php Utilities/migrate-cli.php status`

3. Apply it: `php Utilities/migrate-cli.php up`

#### Note
 - run `baseline` before `up` on an install that predates the migrator. Running `up` first will record the database as up to date without adding missing columns or indexes, and you'd have to fix it by hand
 - add `--dry-run` to `up` or `baseline` to print the SQL it would run and touch nothing
 - `php Utilities/migrate-cli.php doctor` reports anything that doesn't match the expected schema. It only reads, so it's safe to run whenever
 - `php Utilities/migrate-cli.php version` shows the version, what the database is on, and how many changes are pending
 - new installs don't need any of this, install.php runs the migrator for you
 - an existing install keeps working unchanged: `global/siteSettings.php` is optional, and without it the values in `globalconfig.php` still stand. Moving them into it is worth doing anyway, since `globalconfig.php` is tracked and will conflict on a future pull
