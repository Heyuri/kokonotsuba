# Kokonotsuba

## Required stack
Kokonotsuba is designed and tested on the following stack, and isn't guaranteed to work on any other stack.
OS: debian 10
Web server: nginx
DB: mariadb
PHP: PHP7.2-PHP8.3

If you are going to suggest pull requests, please make sure the change would work on the above stack first.

## On creating new instances
Create the database, modify the configuration file, run install.php, delete install.php, make the first post.

You must change the data directory (default ./dat) to a non-indexable, non-web directory, or sensitive data will be leaked. Alternatively, you can deny access to the directory using web server configurations.

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

## Other questions
Check our wiki (https://github.com/Heyuri/kokonotsuba/wiki), or feel free to create an issue to ask your question.
