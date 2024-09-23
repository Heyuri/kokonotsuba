<?php
/*
* This file is for board-specific configurations
*/

$config['STORAGE_PATH'] = 'dat/'; // Storage directory, needs to have 777 permissions. Include trailing '/'
require $config['STORAGE_PATH'].'instance-config.php';

//Board database info
$config['DATABASE_DBNAME'] =  'database';
$config['DATABASE_TABLENAME'] = 'sometable';

$config['TITLE'] = 'Kokonotsuba Board'; // Board Title
$config['TITLESUB'] = ''; // Board Title
$config['TITLEIMG'] = ''; // Board Title Image (url)

$config['ACCOUNT_FLATFILE'] = $config['STORAGE_PATH'].'accounts.txt'; //flatfile used for  storing account data

$config['IMG_DIR'] = 'src/'; // Image Directory
$config['THUMB_DIR'] = 'src/'; // Thumbnail Directory
$config['CDN_DIR'] = ''; // absolute path to the folder for storing imgs & thumbs (excluding IMG_DIR, e.g. /var/www/cdn/heyuri/)
$config['CDN_URL'] = ''; // img/thumb CDN url (without IMG_DIR directory, e.g. https://h.kncdn.org/b/). Set to blank for locally hosted files

// Module List
$config['ModuleList'] = array(
	/* modes */
	'mod_soudane',
	'mod_rss',
	'mod_cat',
	'mod_search',
	'mod_stat',
	/* admin */
	'mod_admindel',
	'mod_adminban',
	/* thread modes */
	'mod_autosage',
	'mod_stop',
	'mod_sticky',
	/* posting */
	'mod_csrf_prevent',
	'mod_bbcode',
	'mod_wf',
	'mod_anigif',
);

// Module-specific options
//mod_countryflags
$config['ModuleSettings']['FLAG_MODE'] = 1; // For the country flags module: 1 = hide flags on posts with "flag" in email field, 2 = show flags on posts with "flag" in email field
//mod_admindel
$config['ModuleSettings']['JANIMUTE_LENGTH'] = 20; // Janitor mute duration (in minutes)
$config['ModuleSettings']['JANIMUTE_REASON'] = 'You have been muted temporarily!'; // Janitor mute reason
//mod_antiflood
$config['ModuleSettings']['RENZOKU3'] = 30; // How many seconds between new threads?
//mod_showip
$config['ModuleSettings']['IPTOGGLE'] = 1; // 1 to have OPs toggle IP display, 2 enables for all posts

$config['ALLOW_UPLOAD_EXT'] = 'GIF|JPG|JPEG|PNG|BMP|SWF|WEBM|MP4'; // Allowed filetypes

// Appearance
$config['ADDITION_INFO'] = @file_get_contents($config['ROOTPATH'].'addinfo.txt'); // Addinfo
$config['LIMIT_SENSOR'] = array('ByThreadCountCondition'=>150); // AutoDelete, defaults to 10 pages


// Appearance
$config['TEMPLATE_FILE'] = 'templates/kokoimg.tpl'; // Template File. Set this and the next line to 'kokotxt.tpl' and 'kokotxtreply.tpl' respectively to use Kokonotsuba as a textboard.
$config['REPLY_TEMPLATE_FILE'] = 'templates/kokoimg.tpl'; // Reply page template file
$config['MAX_AGE_TIME'] = 0; // How long will thread accept age replies? (hours)


$config['USE_CATEGORY'] = 0; // Enable Categories


// Ban Settings
$config['BAN_CHECK'] = 1; // Comprehensive ban check function
$config['GLOBAL_BANS'] = $config['STORAGE_PATH'].'globalbans.log';

// Webhooks for post notifications
$config['DISCORD_WH'] = '';
$config['IRC_WH'] = '';
