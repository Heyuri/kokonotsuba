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
$config['MAX_RW'] = 200; // Reply Max Width
$config['MAX_RH'] = 200; // Reply Max Height
$config['THUMB_SETTING'] = array( // Thumbnail Gen. Settings
	'Method' => 'gd', //gd (default), imagemagick, imagick, magickwand, repng2jpeg
	'Format' => 'png',
	'Quality' => 75
);

$config['CDN_DIR'] = '/var/www/website/cdn/'; // absolute path to the folder for boards' dirs for imgs & thumbs (e.g. /var/www/cdn/heyuri/)
$config['CDN_URL'] = 'https://cdn.example.net/'; // img/thumb CDN url (e.g https://cdn.heyuri.net/)

$config['LIVE_INDEX_FILE'] = 'koko.php'; // Name of the main script
$config['STATIC_INDEX_FILE'] = 'index.html'; // Defines LIVE_INDEX_FILE
$config['PHP_EXT'] = '.html'; // File extension for static pages


$config['FILEIO_BACKEND'] = 'local'; // FileIO backend specification (local, ftp)
$config['FILEIO_INDEXLOG'] = 'fileioindex.dat'; // FileIO Index Log file
$config['FILEIO_PARAMETER'] = ''; // FileIO Parameters (local storage)

$config['IDSEED'] = 'setrandom'; // ID generation seed
$config['TRIPSALT'] = ''; // Used for secure tripcodes. Don't change after setting!

// Capcode formats (put '%s' where you want the original name)
$config['JCAPCODE_FMT'] = '%s';
$config['DCAPCODE_FMT'] = '<span class="capcode capcodeDev">%s ## Developer</span>';
$config['MCAPCODE_FMT'] = '<span class="capcode capcodeMod">%s ## Mod</span>';
$config['ACAPCODE_FMT'] = '<span class="capcode capcodeAdmin">%s ## Admin</span>';
$config['SCAPCODE_FMT'] = '<span class="capcode capcodeSystem">%s ## System</span>';


// mod capcode map
// The key (e.g 'Admin') is that needs to go next to a "## " in the name field to trigger it
// capcodeHtml is the html format for the name when drawing
// requiredRole is what role the poster needs to be able to trigger it
$config['staffCapcodes'] = [
    'System' => [
        'capcodeHtml' => $config['SCAPCODE_FMT'],
        'requiredRole' => \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN,
    ],

    'Admin' => [
        'capcodeHtml' => $config['ACAPCODE_FMT'],
        'requiredRole' => \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN,
    ],

    'Mod' => [
        'capcodeHtml' => $config['MCAPCODE_FMT'],
        'requiredRole' => \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR,
    ],

    'Developer' => [
        'capcodeHtml' => $config['DCAPCODE_FMT'],
        'requiredRole' => \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR,
    ],

    'Janitor' => [
        'capcodeHtml' => $config['JCAPCODE_FMT'],
        'requiredRole' => \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR,
    ],
];


$config['KILL_INCOMPLETE_UPLOAD'] = 1; // Automatically delete uploaded incomplete additional images

