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
- PHP: PHP8\~PHP8.3

If you are going to suggest pull requests, please make sure the change would work on the above stack first.

## Dependencies
- mbstring
- pdo
- gd
- bcmath
- ffmpeg
- exiftool

## Basic installation instructions

### 1. Database set-up

In this step, you just need to create the database and give the database user privileges for it.

In mariadb, run these:
1. `CREATE DATABASE kokonotsuba;`

2. `CREATE USER 'koko_user'@'localhost' IDENTIFIED BY 'your_password';`

3. `GRANT ALL PRIVILEGES ON kokonotsuba.* TO 'koko_user'@'localhost';`

4. `FLUSH PRIVILEGES;`

### 2. File set-up
1. Clone the repo into a directory outside of web root `git clone https://github.com/Heyuri/kokonotsuba`

2. Move the `static` directory out of the backend to somewhere web-accessible.

2. Create the directory for the first board, which will be where your boards will be (MUST be in web root to be accessible) E.g if the base directory for your boards is `/var/www/html/`, if your board's uri will be /test/ then: `mkdir /var/www/html/test`

3. Move install.php from the backend directory into the new board's directory (in this case, `test`)

4. Now, create koko.php and make its contents `require` the koko.php located in the backend directory. Lets say the backend is located at `/var/www/kokonotsuba`.
 4a. open it in vim `vim /var/www/html/test/koko.php`
 4b. then paste this into it `<?php require '/var/www/kokonotsuba/koko.php';` then save it.

### 3. Permissions & Ownership
For the backend's global directory:
`chown -R sysuser:webgroup`
`chmod 770 global/`
`chmod -R 770 global/board-storages/`
`chmod -R 770 global/board-configs/`

Once again lets say the first board is called `test`
For the first board:
`chmod -R 770 test`
`chown -R sysuser:webgroup test`

Also ensure that the directory that your boards are in can be written to so board creation/deletion can work. You can do this by:
`chown sysuser:webgroup /var/www/html`
`chmod 770 /var/www/html`

### 4. Configure

#### databaseSettings.php
You'll need to set your database creds and database name here

1. Set database username and database password to the account you created and granted access to earlier.

2. Then set the database name to the name of the database you created - in this case `kokonotsuba`.

#### globalconfig.php
You can configure most things after installing but these will be required for your new board to behave as expected.

1. Set the value of `$config['WEBSITE_URL']` to the base URL of where your koko boards are located in web root

2. Set the value `$config['TRIPSALT']` to a random value, you could either mash your keyboard or generate a large string comprised of random characters. This is used for secure tripcodes so don't change it after setting it

3. Set `$config['STATIC_URL']` to the web-accessible URL of the static directory from earlier. Depending on how you set it up, the URL might look like `https://example.net/static/` or `https://static.example.net/` - it's up to you as long as its in a web-accessible location.

4. Following up from step 3, set `$config['STATIC_PATH']` to the absolute path to that static directory.

### 5. Final

1. From your browser, access install.php at `test/install.php`

2. On install.php all you really need to do is set the admin username and password then click submit. If there's no errors, delete install.php and access `test/koko.php`

3. You should be good to go. If you have any problems, open an Issue on the repository and describe the problem along with any error logs you can provide

#### Note 
 - this installation assumes that your user is in the web user group
 - sysuser is the user you use on your system
 - webgroup is what the group that the web server / user uses, usually its `www-data` or `www`
`chown -R sysuser:webgroup`