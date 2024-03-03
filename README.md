# Kotatsuba

## Required stack
kotatsuba is designed and tested on the following stack.<br>
Web server: nginx/apache/httpd<br>
DB: mariadb<br>
PHP: PHP7.2-PHP8.3<br>

*note: it is not required to use OpenBSD. its just what i am using for testing. debian might be a better choice*
## installation for OpenBSD

install the required packages : ``pkg_add mariadb-server php php-mysqli php-gdb``<br>
php8.2 is what i am going with foir this guide.

initalize and install  the mysql server `mysql_install_db `<br>
start the mysql server `rcctl start mysqld`<br>
set up some security on the data base `mysql_secure_installation`<br>
log into mysql as root `mysql -u root -p`<br>

you will now need to create a database and a user account.
remeber the username and password. you will need that for the configs
```mysql
CREATE DATABASE boarddb;
CREATE USER 'username'@'localhost' IDENTIFIED BY 'password';
GRANT ALL ON boarddb.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

now with the data base set we will set up the httpd server.<br>
edit ``/etc/httpd.conf`` and add the fallowing
```
server "127.0.0.1" {
	listen on * port 80
	root "/htdocs/kotatsuba"
	directory index index.php
	location "*.php" {
		fastcgi socket "/run/php-fpm.sock"
	}

  # Deny access to the dat/ directory to prevent data leaks
   location "/dat/*" {
       block return 403
   }

   # Deny access to the piodata.log.gz file to prevent data leaks
   location "/piodata.log.gz" {
       block return 403
   }
}
```

what we need to do now is add the moduals to php to support mysqli and gd<br>
edit the ``/etc/php-8.2.ini`` and find the extensions section and uncomment the fallowing<br>
```
extension=gd
extention=mysqli
```
while youa re in this file you can also change the max upload. by defualt its capped to 2mb. 
```
upload_max_filesize = 10M
post_max_size = 12M
```

now edit kokonotsuba's ``config .php`` file. make sure to set your mysql credentals you made earlier and update max file size to what you set.

now you can enable and start all of the services<br>
`rcctl enable php82_fpm mysqld httpd`<br>
`rcctl start php82_fpm httlps`<br>


## On centralizing a multi-board instance for ease of life
One thing that futaba-style boards lose to vichan is that often, they are unable to be centralized on a server. This means that having 3-4 boards may mean you have to edit 3-4 different instances of the same software. Making updating a pain. For koko, this doesnt have to be.

To centralize your koko instance, please edit these lines in;

**koko.php**
Remove all lines. Add:

`<?php require_once '/srv/locationofscript/koko.php';?>`

**config.php**
Remove 

`define("ROOTPATH", dirname(__FILE__).DIRECTORY_SEPARATOR);`

Add

`define("ROOTPATH", '/srv/locationofscript/');`

This also has the added benefit of moving the backend files from being viewable by the user. The same can be done with the dat directory by editing it to be in a non-indexable directory, such as /srv/. Example:

`define("STORAGE_PATH", '/srv/boarddata/');`
