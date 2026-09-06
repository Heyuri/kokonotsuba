# Kokonotsuba

## About Kokonotsuba
* https://kokonotsuba.github.io/

## Required stack
Kokonotsuba is designed and tested on the following stack, and isn't guaranteed to work on any other stack.
- OS: Debian 10\~12
- Web server: nginx (but seems to work fine on Apache)
- DB: MariaDB
- PHP: PHP8.3\~PHP8.5

If you are going to suggest pull requests, please make sure the change would work on the above stack first.

## Dependencies

PHP extensions: `mbstring`, `pdo`, `pdo_mysql`, `gd`, `bcmath`

Programs: `ffmpeg` (video thumbnails), `exiftool` (stripping GPS metadata from uploads)

1. Install dependencies. On Debian:

   ```
   sudo apt update
   sudo apt install php-mbstring php-pdo php-mysql php-gd php-bcmath ffmpeg exiftool
   ```

2. Open the `php.ini` your web server uses and add these lines. On Debian it is
   `/etc/php/8.X/fpm/php.ini` for nginx and `/etc/php/8.X/apache2/php.ini` for Apache, with
   `8.X` being your PHP version:

   ```
   extension=mbstring
   extension=pdo_mysql
   extension=gd
   extension=bcmath
   ```

3. Restart PHP so it picks them up. One of these, depending on your setup:

   ```
   sudo systemctl restart php8.3-fpm    # nginx, change 8.3 to your PHP version
   sudo systemctl restart apache2       # Apache
   ```

`install.php` checks all of this for you and tells you what to run for anything missing.

## Installation

Kokonotsuba is installed by cloning it into a web-accessible directory and opening `install.php` in a
browser.

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
├── boards/       board directories go in here, created by the installer and the admin panel
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

Backend files must not be served over HTTP. Follow the steps for your web server.

**Apache** reads the `.htaccess` files that ship in the tree once it is allowed to:

1. Add this to the site's virtual host:

   ```
   <Directory "/var/www/html/kokonotsuba">
       AllowOverride All
   </Directory>
   ```

2. Reload: `sudo systemctl reload apache2`

**nginx** ignores those files and needs the rules in its own config:

1. Open the site's `server` block.

2. Paste the rules below *above* the `location ~ \.php$` block. Regex locations match in the order
   written, and the PHP handler must not see these files first. `install.php` prints the same rules
   with your own path already filled in.

   ```
   # Kokonotsuba: keep the backend out of reach. These go ABOVE the "location ~ \.php$"
   # block, or that block hands the denied .php files to PHP-FPM first.
   location ~ ^/kokonotsuba/(bootstrap|code|configs|global|migrations|module|templates|tests|Utilities)/ {
       deny all;
   }

   location ~ ^/kokonotsuba/(autoload\.php|databaseSettings\.php|databaseSettings\.example\.php|koko\.php|paths\.php|tables\.php|README\.md|LICENSE)(/|$) {
       deny all;
   }

   # Dotfiles at any depth, and every board's boardUID.ini
   location ~ ^/kokonotsuba/((.*/)?\.|.*\.ini$) {
       deny all;
   }
   ```

3. Check and reload: `sudo nginx -t && sudo systemctl reload nginx`

The installer fetches its own `databaseSettings.php` and a few other files over HTTP and refuses to
run if the web server hands any of them out.

### 5. Run the installer

1. Open `install.php` in a browser: `https://example.net/kokonotsuba/install.php`.

2. Read the checks at the top of the page. They cover PHP and its extensions, the external commands,
   every directory the installer needs and what is reachable from the web. Anything red has the
   command that fixes it next to it: run that, reload the page, repeat until nothing is red. The
   Install button stays disabled until then.

3. Fill in the form:

   - **Database** - the host, port, database name, user and password from step 1.
   - **Admin account** - a username and password for the first staff account. It can do anything,
     including creating more boards.
   - **First board** - its identifier (the directory name under `boards/`, such as `b`), title and
     sub-title.
   - **URLs** - filled in from the address you are reading the page on, so leave them alone unless
     the site is served from somewhere else. The one to look at is the home link: it defaults to the
     site root and is where the "Home" link in every board's header goes.

4. Click the install button at the bottom of the installation page.

5. If it reports a failure, read what failed - refer to Common mistakes for likely problems. You can also open an issue on the repository and we'll help you. 

6. On success the page lists the clean-up commands from step 6 and links to your new board.

#### Common install errors

- **The page says "Already installed"** - `global/.installed` exists from an earlier run. If that
  run really did finish, delete `install.php` and log in. If you are starting over on purpose,
  remove `global/.installed` file and reload.

- **A directory check is red** - the web server user cannot read or write it. Run the
  `chown`/`chmod` command printed next to it; the installer works out the right user itself, so it
  is safe to paste as-is even if you guessed `www-data` wrong in step 3.

- **Every page is a 500, `install.php` included** - the `.htaccess` sets `Options -Indexes`, which
  Apache refuses to honour unless `AllowOverride` includes `Options`. Use `AllowOverride All` as
  in step 4, not a narrower list, and reload.

- **Everything under `boards/` is a 403, but `install.php` and the backend load** - Apache and
  PHP-FPM run as different users, and the directories the installer created are only readable by
  PHP's. The Apache error log says "Permission denied" rather than "denied by server
  configuration". Compare `ps -eo user,comm | grep -E 'apache2|php-fpm'` with
  `ls -ld boards boards/*`, then either put Apache's user in PHP's group or
  `sudo chmod -R o+rX boards`. Boards created before this was fixed in the installer are 0770.

- **An exposure check says a file is served** - the step 4 rules are missing or in the wrong
  place. On nginx, make sure the `deny` blocks sit *above* `location ~ \.php$` and that you
  reloaded. On Apache, check `AllowOverride All` is set for the directory.

- **"The database ... does not exist"** - step 1 was skipped or the name is misspelt. The
  installer never creates the database; run the `CREATE DATABASE` it prints.

- **"MariaDB refused the username or password"** - usually not a typo but a host mismatch. A grant
  for `'koko_user'@'localhost'` only covers socket connections, so it does not work when the host
  field says `127.0.0.1`, and vice versa. Grant the host you typed into the form, or change the
  host field to match the grant.

- **"... exists but has no privileges"** - the user was created but the `GRANT` was skipped. Run the
  grant it prints and `FLUSH PRIVILEGES`.

- **"Nothing answered at ..."** - MariaDB is not running, or is listening somewhere else. Check
  `sudo systemctl status mariadb`.

- **"PHP has no MariaDB/MySQL driver"** - `php-mysql` is missing. Install it and restart PHP-FPM
  (or Apache) so it is picked up; reloading the page is not enough.

- **"The database already holds N accounts"** - you pointed it at a live install's database.
  Use an empty one, or if this *is* the live install, delete `install.php` and log in with the
  account you already have.

- **"No such directory" on the static path** - the path has to be where `static/` sits on disk on
  this server, `/var/www/html/kokonotsuba/static/` in the layout above, not a URL.

- **A migration failed halfway** - MariaDB commits table changes as it goes, so the tables it made
  are left behind while everything else is rolled back. That is fine: fix the cause and submit
  again, and the migration ledger picks up where it stopped.

### 6. After installing

1. Delete the installer: `rm /var/www/html/kokonotsuba/install.php`

2. Hide the credentials from other users on the machine: `sudo chmod 640 /var/www/html/kokonotsuba/databaseSettings.php`

3. Take back the write access on the top directory that the installer needed:

   ```
   sudo chown root:www-data /var/www/html/kokonotsuba && sudo chmod 750 /var/www/html/kokonotsuba
   ```

4. Open your board. A board called `b` is at `https://example.net/kokonotsuba/boards/b/koko.php`,
   and opening it renders its index page for the first time.

5. Log in through `?mode=admin` on the board with the account you just made, and add more boards
   from there.

#### Note
 - `global/siteSettings.php` is where your site's URLs and salts live from then on; everything else
   is edited from the admin panel. `global/siteSettings.example.php` documents the file
 - never change `TRIPSALT` or `IDSEED` once there are posts: every secure tripcode and poster ID changes
 - `databaseSettings.php` is not tracked by git. `databaseSettings.example.php` is, for setting an
   instance up by hand without the installer
 - if you are moving an existing install into this layout, keep your current `databaseSettings.php`
   and copy your `WEBSITE_URL`, `STATIC_URL`, `STATIC_PATH`, `TRIPSALT` and `IDSEED` out of
   `globalconfig.php` into `global/siteSettings.php` rather than running the installer again

## Updating

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
