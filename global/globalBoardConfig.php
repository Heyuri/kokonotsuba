<?php
require __DIR__.DIRECTORY_SEPARATOR.'globalconfig.php';

// Default image directory name - this should be a relative path to your boards' directory
$config['IMG_DIR'] = 'src/';
// Thumb dir, same as IMG_DIR
$config['THUMB_DIR'] = 'src/';

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

// Allowed filetypes and mimetypes
// The key is the extention and the value is the associated mime-type
$config['ALLOW_UPLOAD_EXT'] = [
	'gif'  => 'image/gif',
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png'  => 'image/png',
	'bmp'  => 'image/bmp',
	'swf'  => 'application/x-shockwave-flash',
	'webm' => 'video/webm',
	'mp4'  => 'video/mp4'
];


// Module List
// These are not all modules that come with kokonotsuba that you can enable, there are some unlisted ones too.
// See: https://github.com/Heyuri/kokonotsuba/wiki/All-modules
$config['ModuleList'] = array(
	/* modes */
	'catalog' => true,
	'search' => true,
	'threadList' => true,
	/* admin */
	'rebuild' => true,
	'adminDel' => true,
	'adminBan' => true,
	'globalMessage' => true,
	'blotter' => true,
	'janitor' => true,
	'moveThread' => true,
	/* thread modes */
	'autoSage' => true,
	'lockThread' => true,
	'sticky' => true,
	/* posting */
	'csrfPrevent' => true,
	'bbCode' => true,
	'wordFilter' => true,
	'countryFlags' => false,
	'animatedGif' => true,
	'antiFlood' => true,
	'fieldTraps' => true,
	'readOnly' => false,
	'viewIp' => true,
	/* misc */
	'soudane' => true,
	'privateMessage' => true,
	'fullBanner' => true,
	'imageMeta' => true,
	'onlineCounter' => true,
	'banner' => true,
	'addInfo' => true,
);

/* Module-specific options */
//mod_anigif
$config['ModuleSettings']['MAX_SIZE_FOR_ANIMATED_GIF'] = 2000; // Max file size for animated gifs (in kilobytes)

//mod_imagemeta
$config['ModuleSettings']['EXIF_DATA_VIEWER'] = false;
$config['ModuleSettings']['IMG_OPS'] = true; //imgops reverse image searcher portal
$config['ModuleSettings']['IQDB'] = false; //iqdb reverse image search protal
$config['ModuleSettings']['SWFCHAN'] = true; //swfchan archive

//mod_countryflags
$config['ModuleSettings']['FLAG_MODE'] = 1; // For the country flags module: 1 = hide flags on posts with "flag" in email field, 2 = show flags on posts with "flag" in email field

//mod_admindel
$config['ModuleSettings']['JANIMUTE_LENGTH'] = 20; // Janitor mute duration (in minutes)
$config['ModuleSettings']['JANIMUTE_REASON'] = 'You have been muted temporarily!'; // Janitor mute reason

//mod_antiflood
$config['ModuleSettings']['RENZOKU3'] = 30; // How many seconds between new threads?

//mod_showip
$config['ModuleSettings']['IPTOGGLE'] = 1; // 1 to have OPs toggle IP display, 2 enables for all posts

//mod_blotter
$config['ModuleSettings']['BLOTTER_FILE'] = __DIR__.DIRECTORY_SEPARATOR.'blotter.txt'; //blotter flat file
$config['ModuleSettings']['BLOTTER_DATE_FORMAT'] = "Y/m/d"; //time date format for blotter entries
$config['ModuleSettings']['BLOTTER_PREVIEW_AMOUNT'] = 5; //Number of previewed blotter entries on the index and thread view

//mod_pm
$config['ModuleSettings']['PM_DIR'] = __DIR__.DIRECTORY_SEPARATOR;
$config['ModuleSettings']['APPEND_TRIP_PM_BUTTON_TO_POST'] = false;

//mod_ads
$config['ModuleSettings']['SHOW_TOP_AD'] = true; // Whether to show the top full banner ad
$config['ModuleSettings']['SHOW_BOTTOM_AD'] = true; // Whether to show the bottom full banner ad

//mod_wf
$config['ModuleSettings']['FILTERS'] = array( 
  '/\b(rabi-en-rose|rabi~en~rose)\b/i' => '<span class="rabienrose">Rabi~en~Rose</span>',
	'/\b(newfag)\b/i' => 'n00b like me',
	'/\b(newfags)\b/i' => 'n00bs like me',
	'/\b(heyuri★cgi)\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Heyuri%E2%98%85CGI">Heyuri★CGI</a>',
	'/\b(heyuri cgi)\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Heyuri%E2%98%85CGI">Heyuri★CGI</a>',
	'/\b(chat@heyuri)\b/i' => '<a href="https://cgi.heyuri.net/chat/">Chat@Heyuri</a>',
	'/\b(polls@heyuri)\b/i' => '<a href="https://cgi.heyuri.net/vote2/">Polls@Heyuri</a>',
	'/\b(dating@heyuri)\b/i' => '<a href="https://cgi.heyuri.net/dating/">Dating@Heyuri</a>',
	'/\b(uploader@heyuri)\b/i' => '<a href="https://up.heyuri.net/">Uploader@Heyuri</a>',
	'/@party 2/i' => '<a href="https://cgi.heyuri.net/party2/">@Party II</a>',
	'/@party ii/i' => '<a href="https://cgi.heyuri.net/party2/">@Party II</a>',
	'/\b(ayashii world)\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Ayashii_World">Ayashii World</a>',
	'/\b(partybus)\b/i' => '<span class="partybus"><span class="partybusColor1">p</span><span class="partybusColor2">a</span><span class="partybusColor3">r</span><span class="partybusColor4">t</span><span class="partybusColor5">y</span><span class="partybusColor6">b</span><span class="partybusColor7">u</span><span class="partybusColor8">s</span></span>',
	'/\b(boku)\b/i' => '<span class="boku" title="AGE OF DESU IS OVAR, WE BOKU NOW"><span class="bokuGreen">B</span><span class="bokuRed">O</span><span class="bokuGreen">K</span><span class="bokuRed">U</span></span>'
);

//mod_threadlist
$config['ModuleSettings']['THREADLIST_NUMBER'] = 50; // Number of lists displayed on one page
$config['ModuleSettings']['FORCE_SUBJECT'] = true; // Whether to force a new string to have a title
$config['ModuleSettings']['SHOW_IN_MAIN'] = true; // Whether to display on the main page
$config['ModuleSettings']['THREADLIST_NUMBER_IN_MAIN'] = 40; // Display the number of lists on the main page
$config['ModuleSettings']['SHOW_FORM'] = false; // Whether to display the delete form
$config['ModuleSettings']['HIGHLIGHT_COUNT'] = 15; // The number of popular responses, the number of responses exceeding this value will turn red (0 means not used)

//mod_onlinecounter
$config['ModuleSettings']['USER_COUNT_DAT_FILE'] = 'users.dat'; //Name of the file generated by mod_onlinecounter to keep track of how many IPs are viewing the page. Stored in board-storages for that board
$config['ModuleSettings']['USER_COUNT_TIMEOUT'] = 10; //Timeout for counting the amount of users. Counts in minutes

//mod_banner
$config['ModuleSettings']['BANNER_PATH'] = $config['STATIC_PATH'].'image/banner/'; // Set this to the directory of your banner images

//mod_addinfo
$config['ModuleSettings']['ADD_INFO'] = array(
	'<div id="formfuncs"><a class="postformOption" href="javascript:kkjs.form_switch();">Switch form position</a> | <a class="postformOption" href="'.$config['STATIC_URL'].'html/bbcode.html" target="_blank">BBCode reference</a></div>',
	'Read the <a href="//example.net/rules.html">rules</a> before you post.',
	'Read <a href="//example.net/faq.html">our FAQ</a> for any questions.',
	'Modify this by editing $config[\'ModuleSettings\'][\'ADD_INFO\'] in globalconfig.php',
);

//mod_globalmsg
$config['ModuleSettings']['GLOBAL_TXT'] = __DIR__.'/globalmsg.txt';

//mod_adminban
$config['DEFAULT_BAN_MESSAGE'] = '<p class="warning">(USER WAS BANNED FOR THIS POST) <img class="banIcon icon" alt="banhammer" src="'.$config['STATIC_URL'].'image'.DIRECTORY_SEPARATOR.'hammer.gif"></p>';

//mod_soudane
$config['ModuleSettings']['ENABLE_YEAH'] = true;
$config['ModuleSettings']['ENABLE_NOPE'] = false;
$config['ModuleSettings']['ENABLE_SCORE'] = false;
$config['ModuleSettings']['SHOW_SCORE_ONLY'] = false;

//mod_search
$config['ModuleSettings']['SEARCH_POSTS_PER_PAGE'] = 50;

//mod_readonly
$config['ModuleSettings']['ALLOW_REPLY'] = false; // allow replies to threads but disallow creating threads when board is read-only
$config['ModuleSettings']['MINIMUM_ROLE'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;

//mod_pushpost
$config['ModuleSettings']['PUSHPOST_CHARACTER_LIMIT'] = 250;

$config['BAD_STRING'] = array(); // Deprecated by spamdb
$config['BAD_FILEMD5'] = array(); // Deprecated by spamdb
$config['BANPATTERN'] = array(); // Deprecated by adminban module
$config['DNSBLservers'] = array();  // Deprecated by adminban module
$config['DNSBLWHlist'] = array(); // Deprecated by adminban module


$config['SWF_THUMB'] = $config['STATIC_URL']."image/swf_thumb.png";
$config['ROLL'] = true; //roll feature. True = enabled, False = disabled

/*---- Part 2：Board Functions ----*/
$config['HOME'] = 'index.html'; // What the [Home] button links to
$config['TOP_LINKS'] = @file_get_contents(__DIR__.'/toplinks.txt'); // Navigation links at top left

$config['COMM_MAX'] = 5000; // How many characters in comment
$config['INPUT_MAX'] = 100; // Maximum non-message characters
$config['BR_CHECK'] = 0; // How many lines to show
$config['STATIC_HTML_UNTIL'] = -1; // Static web pages automatically generated when a new article to the first few pages (all generated:-1 only portal pages: 0)
$config['GZIP_COMPRESS_LEVEL'] = 0; // Compression level with gzip 1 - 9

$config['DEFAULT_NOTITLE'] = ''; // Default title if none is inputted
$config['DEFAULT_NONAME'] = 'Anonymous'; // Default Name
$config['DEFAULT_NOCOMMENT'] = 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!'; // Default comment
$config['MINIFY_HTML'] = 0; //Removes unnecessary whitespace in HTML file

// Image Restrictions
$config['MAX_KB'] = 9000; // Image Upload Capacity in KB. Note: To set it higher. you will have to mess with php.ini due to the maximum being 2mb.
$config['STORAGE_LIMIT'] = 0; // Storage limit
$config['STORAGE_MAX'] = 300000; // Total storage limit

$config['AUTO_LINK'] = 1; // Create urls (autolink)

// Footer at the bottom of the page
$config['FOOTTEXT'] = '';


$config['CAPCODES'] = array( // tripcode=>color,cap // for secure tripcode hashes, put ★ instead of ◆
	'tripcode' => array('color'=>'#fd0000', 'cap'=>' ## Admin'),
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
$config['RENDER_REPLY_NUMBER'] = true; // Show the sequential reply number for each post within a thread (does not change if posts are deleted)

$config['PAGE_DEF'] = 15; // How many threads per page
$config['ADMIN_PAGE_DEF'] = 100; // How many replies per page on admin panel
$config['RE_DEF'] = 5; // Shown Replies on Index
$config['RE_PAGE_DEF'] = 1000; // Shown replies on the thread index
$config['MAX_RES'] = 1000; // How many replies before autosaged

$config['TEXTBOARD_ONLY'] = 0; // Completely disables all file features
$config['RESIMG'] = 1; // Allow files in replies

// Continuous Posting Time Limits
$config['RENZOKU'] = 0; // Post limit, intervals in seconds
$config['RENZOKU2'] = 0; // Post limit for images, intervals in seconds


$config['TRUST_HTTP_X_FORWARDED_FOR'] = 0; //Whether to use HTTP_X_FORWARDED_FOR to grab the real IP after the Proxy. Note that the file head may be forged, do not open if there is no special need.

// Appearance
$config['MAX_THREAD_AMOUNT'] = 150; // Auto deletes the last thread from a board that exceed this limit, defaults to 10 pages

// Appearance
$config['TEMPLATE_FILE'] = 'kokoimg.tpl'; // Template File. Set this and the next line to 'kokotxt.tpl' and 'kokotxtreply.tpl' respectively to use Kokonotsuba as a textboard.
$config['REPLY_TEMPLATE_FILE'] = 'kokoimg.tpl'; // Reply page template file
$config['MAX_AGE_TIME'] = 0; // How long will thread accept age replies? (hours)

$config['USE_CATEGORY'] = 0; // Enable Categories

$config['PREVENT_DUPLICATE_FILE_UPLOADS'] = false; // Disallow the same file was being posted twice 

// Ban Settings
$config['BAN_CHECK'] = 1; // Comprehensive ban check function
$config['GLOBAL_BANS'] = 'globalbans.log'; //global bans file name. The file is stored in `global/`

// Webhooks for post notifications
$config['DISCORD_WH'] = '';
$config['IRC_WH'] = '';


$config['TIME_ZONE'] = '0'; // Timezones, 0 is UTC. Example: '-4' for New York, or '9' for Japan
$config['PIXMICAT_LANGUAGE'] = 'en_US'; // Language (available languages in /lib/lang/)
$config['HTTP_UPLOAD_DIFF'] = 50; 


// Overboard title and sub-title
$config['OVERBOARD_TITLE'] = "Overboard";
$config['OVERBOARD_SUBTITLE'] = "Posts from all koko boards";
// HTML that will appear above the filter box
$config['OVERBOARD_SUB_HEADER_HTML'] = '';
// How many threads per page on the overboard
$config['OVERBOARD_THREADS_PER_PAGE'] = 20;
// A link to the overboard on the admin bar (next to [Admin] on the top right). Displayed as [Overboard]
$config['ADMINBAR_OVERBOARD_BUTTON'] = true;

$config['ACTIONLOG_MAX_PER_PAGE'] = 50; // the amount of actionlog entries per page

// Role levels
$config['AuthLevels']['CAN_VIEW_IP_ADDRESSES'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;
$config['AuthLevels']['CAN_BAN'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;
$config['AuthLevels']['CAN_DELETE_POST'] = \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR;
$config['AuthLevels']['CAN_LOCK'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;
$config['AuthLevels']['CAN_STICKY'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;
$config['AuthLevels']['CAN_AUTO_SAGE'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;
$config['AuthLevels']['CAN_MANAGE_REBUILD'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;
$config['AuthLevels']['CAN_EDIT_GLOBAL_MESSAGE'] = \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN;
$config['AuthLevels']['CAN_EDIT_BLOTTER'] = \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN;
$config['AuthLevels']['CAN_MANAGE_POSTS'] = \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR;
$config['AuthLevels']['CAN_VIEW_ACTION_LOG'] = \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR;

// The duration (in seconds) of inactivity allowed before automatically logging out a staff user
// This value must not exceed the value of session.gc_maxlifetime in your php.ini
$config['STAFF_LOGIN_TIMEOUT'] = 86400;

// name of system role/user
$config['SYSTEMCHAN_NAME'] = "System-chan";
