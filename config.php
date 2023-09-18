<?php
/*---- Part 1：Basic Program Settings ----*/
// Server Settings
if(!defined('DEBUG')) define("DEBUG", false); // Set to "true" to generate detailed debug settings in the error.log file
define("ROOTPATH", './'); // Main Program Root Directory
define("STORAGE_PATH", 'dat/'); // Storage directory, needs to have 777 permissions (include trailing '/')
define("TIME_ZONE", '0'); // Timezones
define("PIXMICAT_LANGUAGE", 'en_US'); // Language (available languages in /lib/lang/)
define("HTTP_UPLOAD_DIFF", 50); 
ini_set("memory_limit", '128M'); // Maximum allowed memory usage by php 
define("STATIC_URL", 'https://static.heyuri.net/koko/'); // Where static files are stored
define("ACTION_LOG", 'audit.log.txt');

// FileIO settings 
define("FILEIO_BACKEND", 'local'); // FileIO backend specification (local, ftp)
define("FILEIO_INDEXLOG", 'fileioindex.dat'); // FileIO Index Log file
define("FILEIO_PARAMETER", ''); // FileIO Parameters (local storage)
//define("FILEIO_PARAMETER", serialize(array('ftp.example.com', 21, 'demo', 'demo', 'PASV', '/pwd/', 'http://www.example.com/~demo/pwd/', true)));
//define("FILEIO_PARAMETER", serialize(array('00000000000000000000000000000000'))); 
//define("FILEIO_PARAMETER", serialize(array('http://www.example.com/~demo/satellite.cgi', true, '12345678', 'http://www.example.com/~demo/src/', true)));

// Database Settings
define("CONNECTION_STRING", 'mysqli://user:password@localhost/boarddb/imglog/'); // PIO Connection string (MySQLi)

// Archive Database Settings (MySQLi)
define("ARCHIVE_HOST",	'localhost');
define("ARCHIVE_USER",	'root');
define("ARCHIVE_PASS",	'ilovetomo');
define("ARCHIVE_DB",	'archiver3'); // Make the DB first!
define("ARCHIVE_TABLE",	'archiver3');

/*---- Part 2：Board Functions ----*/
define("IMG_DIR", 'src/'); // Image Directory
define("THUMB_DIR", 'src/'); // Thumbnail Directory
define("CDN_DIR", ''); // absolute path to the folder for storing imgs & thumbs (excluding IMG_DIR, e.g. /var/www/cdn/heyuri/)
define("CDN_URL", ''); // img/thumb CDN url (without IMG_DIR directory, e.g. https://h.kncdn.org/b/). Set to blank for locally hosted files
define("REF_URL", ''); // URL prefix, eg: https://jump.heyuri.net
define("PHP_SELF", 'koko.php'); // Name of the main script
define("PHP_SELF2", 'index.html'); // Defines PHP_SELF
define("PHP_EXT", '.html'); // File extension for static pages
define("TITLE", 'Kokonotsuba Board'); // Board Title
define("TITLESUB", ''); // Board Title
define("TITLEIMG", ''); // Board Title
define("HOME", 'index.html'); // What the [Home] button links to
define("TOP_LINKS", @file_get_contents('toplinks.txt')); // Additional links
define("IDSEED", 'setrandom'); // ID generation seed
define("TRIPSALT", ''); // Used for secure tripcodes. Don't change after setting!
define("CAPCODES", array( // tripcode=>color,cap
	'!tripcode' => array('color'=>'#fd0000', 'cap'=>' ## Admin'),
));

// Webhooks for post notifications
// define("DISCORD_WH", '');
// define("IRC_WH", '');

// Moderator settings
// Passwords must be hashed. Obtain a hashed password at https://sys.kolyma.net/passwd.php
define("ADMIN_HASH", array('')); // Administrator password
define("MOD_HASH", array('')); // Moderator password
define("JANITOR_HASH", array('')); ///
// Capcode formats (put '%s' where you want the original name)
define("JCAPCODE_FMT", '%s');
define("MCAPCODE_FMT", '<font color="#770099">%s ## Mod</font>');
define("ACAPCODE_FMT", '<font color="#FF101A">%s ## Admin</font>');
// Footer at the bottom of the page
define("FOOTTEXT", '');

// Functions
// 0 = NO | 1 = YES 
define("THREAD_PAGINATION", 1); // Thread html pagination
define("USE_SEARCH", 1); // Use the search feature
define("USE_UPSERIES", 0); // Allows users to optionally bypass pagination
define("RESIMG", 1); // Allow files in replies
define("AUTO_LINK", 1); // Create urls (autolink)
define("KILL_INCOMPLETE_UPLOAD", 1); // Automatically delete uploaded incomplete additional images
define("ALLOW_NONAME", 1); // Allow posters to submit without names
define("DISP_ID", 0); // 2 enables, 0 disables
define("ID_MODE", 0); // Leave 0, do not change
define("CLEAR_SAGE", 0); // Disable sage if true
define("NOTICE_SAGE", 1); // Visible sage ("SAGE!")
define("USE_QUOTESYSTEM", 1); // Enable >>1234
define("USE_BACKLINK", 1); // Enable backlinks on posts 
define("SHOW_IMGWH", 1); // Display the original length and width dimension of the additional image file
define("USE_CATEGORY", 0); // Enable Categories
define("TRUST_HTTP_X_FORWARDED_FOR", 0); //Whether to use HTTP_X_FORWARDED_FOR to grab the real IP after the Proxy. Note that the file head may be forged, do not open if there is no special need.
$PROXYHEADERlist=array(
	'HTTP_CLIENT_IP',
	'HTTP_X_REAL_IP',
	'HTTP_X_FORWARDED_FOR',
	'HTTP_X_FORWARDED',
	'HTTP_X_CLUSTER_CLIENT_IP',
	'HTTP_FORWARDED_FOR',
	'HTTP_FORWARDED');
define("TEXTBOARD_ONLY", 0); // Completely disables all file features
define("USE_PREVIEW", 0); // BROKEN! 
define("FORTUNES", array( // Used for fortune function, selected at random.
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
));

// Module List
$ModuleList = array(
	/* modes */
	'mod_soudane',
	'mod_rss',
	'mod_cat',
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
define("FLAG_MODE", 1); // For the country flags module: 1 = hide flags on posts with "flag" in email field, 2 = show flags on posts with "flag" in email field
//mod_admindel
define("JANIMUTE_LENGTH", 20); // Janitor mute duration (in minutes)
define("JANIMUTE_REASON", 'You have been muted temporarily!'); // Janitor mute reason

// Ban Settings
define("BAN_CHECK", 1); // Comprehensive ban check function
$BANPATTERN = array(); // Deprecated by adminban module
$DNSBLservers = array();  // Deprecated by adminban module
$DNSBLWHlist = array(); // Deprecated by adminban module
$BAD_STRING = array(); // Deprecated by spamdb 
$BAD_FILEMD5 = array(); // Deprecated by spamdb
define("GLOBAL_BANS", STORAGE_PATH.'globalbans.log');


// Image Restrictions
define("MAX_KB", 9000); // Image Upload Capacity in KB. Note: To set it higher. you will have to mess with php.ini due to the maximum being 2mb.
define("STORAGE_LIMIT", 0); // storage limit
define("STORAGE_MAX", 300000); // total storage limit
define("ALLOW_UPLOAD_EXT", 'GIF|JPG|JPEG|PNG|BMP|SWF|WEBM|MP4'); // Allowed filetypes
define("VIDEO_EXT", 'WEBM|MP4'); // What filetypes will be loaded as a video
define("SWF_THUMB", STATIC_URL."swf_thumb.png");

// Continuous Posting Time Limits
define("RENZOKU", 0); // Post limit, intervals in seconds
define("RENZOKU2", 0); // Post limit for images, intervals in seconds

// Image Thumbnailing
define("USE_THUMB", 1); // Enable Thumbnailing [gd, imagemagick, imagick, magickwand, repng2jpeg]
define("MAX_W", 250); // Max Width
define("MAX_H", 250); // Max Height
define("MAX_RW", 125); // Reply Max Width
define("MAX_RH", 125); // Reply Max Height
define("THUMB_SETTING", array( // Thumbnail Gen. Settings
	'Method' => 'gd', //gd (default), imagemagick, imagick, magickwand, repng2jpeg
	'Format' => 'png',
	'Quality' => 75
));

// Appearance
$ADDITION_INFO = @file_get_contents(ROOTPATH.'addinfo.txt'); // Addinfo
$LIMIT_SENSOR = array('ByThreadCountCondition'=>150); // AutoDelete, defaults to 10 pages
define("TEMPLATE_FILE", 'kokoimg.tpl'); // Template File. Set this and the next line to 'kokotxt.tpl' and 'kokotxtreply.tpl' respectively to use Kokonotsuba as a textboard.
define("REPLY_TEMPLATE_FILE", 'kokoimgreply.tpl'); // Reply page template file
define("PAGE_DEF", 15); // How many threads per page
define("ADMIN_PAGE_DEF", 20); // How many threads per page on admin panel
define("RE_DEF", 5); // Shown Replies on Index
define("RE_PAGE_DEF", 1000); // Shown replies on the thread index
define("MAX_RES", 1000); // How many replies before autosaged
define("MAX_AGE_TIME", 0); // How long will thread accept age replies?
define("COMM_MAX", 5000); // How many characters in comment
define("INPUT_MAX", 100); // Maximum non-message characters
define("BR_CHECK", 0); // how many lines to show
define("STATIC_HTML_UNTIL", -1); // Static web pages automatically generated when a new article to the first few pages (all generated:-1 only portal pages: 0)
define("GZIP_COMPRESS_LEVEL", 0); // compression level with gzip 1 - 9
define("DEFAULT_NOTITLE", ''); // Default title if none is inputted
define("DEFAULT_NONAME", 'Anonymous'); // Default Name
define("DEFAULT_NOCOMMENT", 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!'); // Default comment
define("MINIFY_HTML", 0); //Removes unnecessary whitespace in HTML file
