<?php
/*
* This is for global settings that should not be overwritten by any board's config, and thus can be accessed without needing to access the board.
*/
$config['PIXMICAT_LANGUAGE'] = 'en_US'; // Language (available languages in /lib/lang/)
$config['ERROR_HANDLER_FILE'] = __DIR__.'/error.log';

$config['STATIC_URL'] = 'https://static.example.net/'; // Where static files are located on the web, can be a full URL (eg. 'https://static.example.com/'). Include trailing '/'
$config['STATIC_PATH'] = '/var/www/static/'; // Where static files are stored in the server, can be an absolute path (eg. '/home/example/web/static/'). Include trailing '/'
$config['WEBSITE_URL'] = "https://".$_SERVER['HTTP_HOST']."/"; //The URL of where the boards are. (e.g "https://boards.example.net/")

$config['USE_CDN'] = false; //Whether to use the "cdn" (AKA storing all board uploaded-file-storages in one central directory on the server)

// Image Thumbnailing
$config['USE_THUMB'] = 1; // Enable Thumbnailing [gd, imagemagick, imagick, magickwand, repng2jpeg]
$config['MAX_W'] = 250; // Max Width
$config['MAX_H'] = 250; // Max Height
$config['MAX_RW'] = 125; // Reply Max Width
$config['MAX_RH'] = 125; // Reply Max Height
$config['THUMB_SETTING'] = array( // Thumbnail Gen. Settings
	'Method' => 'gd', //gd (default), imagemagick, imagick, magickwand, repng2jpeg
	'Format' => 'png',
	'Quality' => 75
);

$config['CDN_DIR'] = '/var/www/website/cdn/'; // absolute path to the folder for boards' dirs for imgs & thumbs (e.g. /var/www/cdn/heyuri/)
$config['CDN_URL'] = 'https://cdn.example.net/'; // img/thumb CDN url (e.g https://cdn.heyuri.net/)

$config['PHP_SELF'] = 'koko.php'; // Name of the main script
$config['PHP_SELF2'] = 'index.html'; // Defines PHP_SELF
$config['PHP_EXT'] = '.html'; // File extension for static pages


$config['FILEIO_BACKEND'] = 'local'; // FileIO backend specification (local, ftp)
$config['FILEIO_INDEXLOG'] = 'fileioindex.dat'; // FileIO Index Log file
$config['FILEIO_PARAMETER'] = ''; // FileIO Parameters (local storage)

$config['IDSEED'] = 'setrandom'; // ID generation seed
$config['TRIPSALT'] = ''; // Used for secure tripcodes. Don't change after setting!

//these are moderator / elevated user roles
$config['roles']['LEV_NONE'] = 0; //not logged in
$config['roles']['LEV_USER'] = 1; //registered user
$config['roles']['LEV_JANITOR'] = 2; //janitor
$config['roles']['LEV_MODERATOR'] = 3; //moderator
$config['roles']['LEV_ADMIN'] = 4; //administrator
$config['roles']['LEV_SYSTEM'] = 5; //system

$config['KILL_INCOMPLETE_UPLOAD'] = 1; // Automatically delete uploaded incomplete additional images
