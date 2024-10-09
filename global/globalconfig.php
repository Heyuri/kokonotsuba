<?php
/*
* Treat this file as a instance-wide config. Overwrite values in here in the board-config file for your board, if you want board specific settings.
*/


// Database creds
$config['DATABASE_USERNAME'] = 'user';
$config['DATABASE_PASSWORD'] ='password';

$config['DATABASE_DRIVER'] = 'mysqli';
$config['DATABASE_HOST'] = '127.0.0.1';
$config['DATABASE_PORT'] = 3306; // Default MySQL port

$config['ROOTPATH'] = './'; //Set this to the directory of the backend-files

$config['ALLOW_UPLOAD_EXT'] = 'GIF|JPG|JPEG|PNG|BMP|SWF|WEBM|MP4'; // Allowed filetypes

// Appearance
$config['ADDITION_INFO'] = @file_get_contents($config['ROOTPATH'].'addinfo.txt'); // Addinfo
$config['LIMIT_SENSOR'] = array('ByThreadCountCondition'=>150); // AutoDelete, defaults to 10 pages


// Appearance
$config['TEMPLATE_FILE'] = $config['ROOTPATH'].'templates/kokoimg.tpl'; // Template File. Set this and the next line to 'kokotxt.tpl' and 'kokotxtreply.tpl' respectively to use Kokonotsuba as a textboard.
$config['REPLY_TEMPLATE_FILE'] = $config['ROOTPATH'].'templates/kokoimg.tpl'; // Reply page template file
$config['MAX_AGE_TIME'] = 0; // How long will thread accept age replies? (hours)

$config['USE_CATEGORY'] = 0; // Enable Categories


// Ban Settings
$config['BAN_CHECK'] = 1; // Comprehensive ban check function
$config['GLOBAL_BANS'] = $config['STORAGE_PATH'].'globalbans.log';

// Webhooks for post notifications
$config['DISCORD_WH'] = '';
$config['IRC_WH'] = '';


$config['TIME_ZONE'] = '0'; // Timezones, 0 is UTC. Example: '-4' for New York, or '9' for Japan
$config['PIXMICAT_LANGUAGE'] = 'en_US'; // Language (available languages in /lib/lang/)
$config['HTTP_UPLOAD_DIFF'] = 50; 
$config['STATIC_URL'] = './static/'; // Where static files are stored. Can be a url. Include trailing '/'
$config['ACTION_LOG'] = 'audit.log.txt';

$config['FILEIO_BACKEND'] = 'local'; // FileIO backend specification (local, ftp)
$config['FILEIO_INDEXLOG'] = 'fileioindex.dat'; // FileIO Index Log file
$config['FILEIO_PARAMETER'] = ''; // FileIO Parameters (local storage)

$config['IDSEED'] = 'setrandom'; // ID generation seed
$config['TRIPSALT'] = ''; // Used for secure tripcodes. Don't change after setting!
$config['CAPCODES'] = array( // tripcode=>color,cap // for secure tripcode hashes, put ★ instead of ◆
	'◆tripcode' => array('color'=>'#fd0000', 'cap'=>' ## Admin'),
);

$config['REF_URL'] = ''; // URL prefix, eg: https://jump.heyuri.net

$config['VIDEO_EXT'] = 'WEBM|MP4'; // What filetypes will be loaded as a video


$config['ALLOW_NONAME'] = 1; // Allow posters to submit without names
$config['DISP_ID'] = 0; // 2 enables, 0 disables
$config['ID_MODE'] = 0; // Leave 0, do not change
$config['CLEAR_SAGE'] = 0; // Disable sage if true
$config['NOTICE_SAGE'] = 1; // Visible sage ("SAGE!")
$config['USE_QUOTESYSTEM'] = 1; // Enable >>1234
$config['SHOW_IMGWH'] = 1; // Display the original length and width dimension of the additional image file

$config['PAGE_DEF'] = 15; // How many threads per page
$config['ADMIN_PAGE_DEF'] = 20; // How many replies per page on admin panel
$config['RE_DEF'] = 5; // Shown Replies on Index
$config['RE_PAGE_DEF'] = 1000; // Shown replies on the thread index
$config['MAX_RES'] = 1000; // How many replies before autosaged

$config['TEXTBOARD_ONLY'] = 0; // Completely disables all file features
$config['RESIMG'] = 1; // Allow files in replies

// Continuous Posting Time Limits
$config['RENZOKU'] = 0; // Post limit, intervals in seconds
$config['RENZOKU2'] = 0; // Post limit for images, intervals in seconds

/*---- Part 2：Board Functions ----*/
$config['PHP_SELF'] = 'koko.php'; // Name of the main script
$config['PHP_SELF2'] = 'index.html'; // Defines PHP_SELF
$config['PHP_EXT'] = '.html'; // File extension for static pages
$config['HOME'] = 'index.html'; // What the [Home] button links to
$config['TOP_LINKS'] = @file_get_contents('toplinks.txt'); // Additional links

$config['COMM_MAX'] = 5000; // How many characters in comment
$config['INPUT_MAX'] = 100; // Maximum non-message characters
$config['BR_CHECK'] = 0; // how many lines to show
$config['STATIC_HTML_UNTIL'] = -1; // Static web pages automatically generated when a new article to the first few pages (all generated:-1 only portal pages: 0)
$config['GZIP_COMPRESS_LEVEL'] = 0; // compression level with gzip 1 - 9

$config['DEFAULT_NOTITLE'] = ''; // Default title if none is inputted
$config['DEFAULT_NONAME'] = 'Anonymous'; // Default Name
$config['DEFAULT_NOCOMMENT'] = 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!'; // Default comment
$config['MINIFY_HTML'] = 0; //Removes unnecessary whitespace in HTML file

// Image Restrictions
$config['MAX_KB'] = 9000; // Image Upload Capacity in KB. Note: To set it higher. you will have to mess with php.ini due to the maximum being 2mb.
$config['STORAGE_LIMIT'] = 0; // storage limit
$config['STORAGE_MAX'] = 300000; // total storage limit

$config['AUTO_LINK'] = 1; // Create urls (autolink)

// 0 = NO | 1 = YES 
$config['THREAD_PAGINATION'] = 1; // Thread html pagination
$config['USE_UPSERIES'] = 0; // Allows users to optionally bypass pagination
$config['KILL_INCOMPLETE_UPLOAD'] = 1; // Automatically delete uploaded incomplete additional images

// Capcode formats (put '%s' where you want the original name)
$config['JCAPCODE_FMT'] = '%s';
$config['MCAPCODE_FMT'] = '<font color="#770099">%s ## Mod</font>';
$config['ACAPCODE_FMT'] = '<font color="#FF101A">%s ## Admin</font>';

// Footer at the bottom of the page
$config['FOOTTEXT'] = '';

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

$config['TRUST_HTTP_X_FORWARDED_FOR'] = 0; //Whether to use HTTP_X_FORWARDED_FOR to grab the real IP after the Proxy. Note that the file head may be forged, do not open if there is no special need.

$config['PROXYHEADERlist'] = array(
	'HTTP_CLIENT_IP',
	'HTTP_X_REAL_IP',
	'HTTP_X_FORWARDED_FOR',
	'HTTP_X_FORWARDED',
	'HTTP_X_CLUSTER_CLIENT_IP',
	'HTTP_FORWARDED_FOR',
	'HTTP_FORWARDED');
$config['FORTUNES'] = array( // Used for fortune function, selected at random.
	'Your true waifu will reveal herself',
	'Only time will tell',
	'Dark times are to come',
	'Your harem is only just begining',
	'You have cancer',
	'You have aids',
	'Tomo will strangle you in your sleep',
	'You just lost the game',
	'We don\'t know what happens next',
	'mah pen0z is bigger than uurz',
	'LOLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOL',
	'Bad luck',
	'Average luck',
	'Good luck',
	'Godly luck',
	'Very bad luck',
	'ｷﾀ━━━━━━(ﾟ∀ﾟ)━━━━━━ !!!!',
	'（　´_ゝ`）ﾌｰﾝ'
);

// Module List
// These are not all modules that come with kokonotsuba that you can enable, there are some unlisted ones too.
// See: https://github.com/Heyuri/kokonotsuba/wiki/All-modules
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


$config['ACCOUNT_FLATFILE'] = $config['STORAGE_PATH'].'accounts.txt';

$config['BAD_STRING'] = array();
$config['BAD_FILEMD5'] = array();
$config['BANPATTERN'] = array(); // Deprecated by adminban module
$config['DNSBLservers'] = array();  // Deprecated by adminban module
$config['DNSBLWHlist'] = array(); // Deprecated by adminban module


$config['SWF_THUMB'] = $config['STATIC_URL']."image/swf_thumb.png";

$config['ROLL'] = array();

//these are moderator / elevated user roles
$config['roles']['LEV_NONE'] = 0; //not logged-in
$config['roles']['LEV_USER'] = 1; //registered user
$config['roles']['LEV_JANITOR'] = 2; //janitor
$config['roles']['LEV_MODERATOR'] = 3; //moderator
$config['roles']['LEV_ADMIN'] = 4; //administrator
