<?php
/*
* This is for global settings that should not be overwritten by any board's config, and thus can be accessed without needing to access the board.
*
* This file returns the config array directly. Load it via getGlobalConfig().
*
* Values specific to one site (URLs, salts) belong in the generated global/siteSettings.php,
* which overrides the defaults below. This file is tracked by git; that one is not.
*/

// The autoloader is registered by the entry point, but this file is also read very early by
// tooling, so make sure the overlay class can be found.
if (!class_exists(Kokonotsuba\config\siteSettings::class, false)) {
	require_once __DIR__.'/../autoload.php';
}

$siteSettings = Kokonotsuba\config\siteSettings::load(__DIR__.'/siteSettings.php');

// Values reused when building derived entries below.
$staticUrl = $siteSettings['STATIC_URL'] ?? 'https://static.example.net/'; // Where static files are located on the web. Include trailing '/'
$staticPath = $siteSettings['STATIC_PATH'] ?? '/var/www/static/'; // Where static files are stored on the server. Include trailing '/'

// Capcode formats (put '%s' where you want the original name). Consumed only by staffCapcodes.
$jCapcodeFmt = '%s';
$dCapcodeFmt = '<span class="capcode capcodeDev">%s ## Developer</span>';
$mCapcodeFmt = '<span class="capcode capcodeMod">%s ## Mod</span>';
$mgCapcodeFmt = '<span class="capcode capcodeManager">%s ## Manager</span>';
$aCapcodeFmt = '<span class="capcode capcodeAdmin">%s ## Admin</span>';
$sCapcodeFmt = '<span class="capcode capcodeSystem">%s ## System</span>';

$config = [
	'PIXMICAT_LANGUAGE' => 'en_US', // Language (available languages in /lib/lang/)
	'ERROR_HANDLER_FILE' => __DIR__.'/error.log',

	'STATIC_URL' => $staticUrl,
	'STATIC_PATH' => $staticPath,
	'WEBSITE_URL' => '/',

	// Where the header's "Home" link points. Site-wide default: boards can override it from the
	// board configuration editor, which takes this as its default (see configs/appearance.php).
	'HOME' => 'index.html',

	'USE_CDN' => false, // Whether to use the "cdn" (storing all board upload storages in one central directory)

	// Image Thumbnailing
	'USE_THUMB' => 1, // Enable Thumbnailing
	'MAX_W' => 250,  // Max Width
	'MAX_H' => 250,  // Max Height
	'MAX_RW' => 200, // Reply Max Width
	'MAX_RH' => 200, // Reply Max Height
	'THUMB_SETTING' => [ // Thumbnail Gen. Settings
		// Preferred backend: gd (default) or ffmpeg. Whichever is set, the other one covers
		// the files it cannot read, such as AVIF and images too large to decode in memory.
		'Method' => 'gd',
		'Format' => 'png', // jpg, png, gif or webp
		'Quality' => 75, // 1-100, lossy formats only (jpg, webp)
		'TransparentBackgroundColor' => '#F0E0D6',
	],

	'CDN_DIR' => '/var/www/website/cdn/', // absolute path to the folder for boards' img/thumb dirs
	'CDN_URL' => 'https://cdn.example.net/', // img/thumb CDN url

	'LIVE_INDEX_FILE' => 'koko.php', // Name of the main script
	'STATIC_INDEX_FILE' => 'index.html', // Defines LIVE_INDEX_FILE
	'PHP_EXT' => '.html', // File extension for static pages

	'IDSEED' => 'setrandom', // ID generation seed
	'TRIPSALT' => '', // Used for secure tripcodes. Don't change after setting!

	// Role levels
	'AuthLevels' => [
		'CAN_VIEW_IP_ADDRESSES' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_MOVE_THREAD' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_BAN' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_DELETE_POST' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_DELETE_ALL' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_DELETE_RESTORE_RECORDS' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_LOCK' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_STICKY' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_AUTO_SAGE' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_MANAGE_REBUILD' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_EDIT_GLOBAL_MESSAGE' => Kokonotsuba\userRole::LEV_MANAGER,
		'CAN_EDIT_BLOTTER' => Kokonotsuba\userRole::LEV_MANAGER,
		'CAN_MANAGE_POSTS' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_VIEW_ACTION_LOG' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_RAW_HTML' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_MANAGE_CAPCODES' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_ONLY_VIEW_POSTS_FROM_USER' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_LEAVE_NOTE' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_DELETE_NOTE' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_VIEW_HOST_NOTE' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_LEAVE_HOST_NOTE' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_DELETE_HOST_NOTE' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_EDIT_POST' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_VIEW_POST_REVISIONS' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_RESTORE_POST_REVISIONS' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_BAN_FILES' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_MANAGE_PMS' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_MANAGE_ADS' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_MANAGE_ANTI_SPAM_SYSTEM' => Kokonotsuba\userRole::LEV_MANAGER,
		'CAN_MANAGE_BANNERS' => Kokonotsuba\userRole::LEV_MANAGER,
		'CAN_ANONYMIZE_IPS' => Kokonotsuba\userRole::LEV_ADMIN,
		'CAN_VIEW_VOTES' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_VIEW_REPORTS' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_APPROVE_REPORT' => Kokonotsuba\userRole::LEV_JANITOR,
		'CAN_DISMISS_REPORT' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_CLEAR_POST_REPORTS' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_VIEW_BAN_APPEALS' => Kokonotsuba\userRole::LEV_MODERATOR,
		'CAN_ACTION_BAN_APPEAL' => Kokonotsuba\userRole::LEV_MODERATOR,
	],

	// mod capcode map
	// The key (e.g 'Admin') is what needs to go next to a "## " in the name field to trigger it.
	// capcodeHtml is the html format for the name when drawing; requiredRole is the role needed to trigger it.
	'staffCapcodes' => [
		'System' => [
			'capcodeHtml' => $sCapcodeFmt,
			'requiredRole' => Kokonotsuba\userRole::LEV_ADMIN,
		],
		'Admin' => [
			'capcodeHtml' => $aCapcodeFmt,
			'requiredRole' => Kokonotsuba\userRole::LEV_ADMIN,
		],
		'Manager' => [
			'capcodeHtml' => $mgCapcodeFmt,
			'requiredRole' => Kokonotsuba\userRole::LEV_MANAGER,
		],
		'Mod' => [
			'capcodeHtml' => $mCapcodeFmt,
			'requiredRole' => Kokonotsuba\userRole::LEV_MODERATOR,
		],
		'Developer' => [
			'capcodeHtml' => $dCapcodeFmt,
			'requiredRole' => Kokonotsuba\userRole::LEV_MODERATOR,
		],
		'Janitor' => [
			'capcodeHtml' => $jCapcodeFmt,
			'requiredRole' => Kokonotsuba\userRole::LEV_JANITOR,
		],
	],

	// Default values for JS user settings checkboxes.
	// Sent to the frontend as JSON and used when the user has not yet set a preference in localStorage.
	'JS_DEFAULT_SETTINGS' => [
		'neomenu' => false,
		'persistnav' => false,
		'persistpager' => false,
		'centerthreads' => false,
		'tripkeys' => false,
		'markopqu' => true,
		'imgexpand' => true,
		'imghover' => false,
		'update' => true,
		'useqr' => true,
		'alwaysqr' => false,
		'quoteinline' => false,
		'quotetooltip' => true,
		'galmode' => false,
		'addbacklinks' => false,
		'threadWatcherNotifs' => true,
		'threadWatcherQuotePush' => true,
		'threadWatcherNewThreads' => true,
		'threadWatcherSound' => true,
		'threadWatcherAutoWatch' => true,
		'threadWatcherAutoWatchOwnThreads' => true,
		'enablesoudane' => true,
		'reportNotifs' => true,
		'staffNav' => true,
		'staffAlerts' => true,
	],

	// Board styles
	'styles' => [
		// kokoimg styles
		'kokoimg' => [
			'Sakomoto' => 'sakomoto.css',
			'Heyuri Classic' => 'heyuriclassic.css',
			'Futaba' => 'futaba.css',
			'Burichan' => 'burichan.css',
			'Fuuka' => 'fuuka.css',
			'Gurochan' => 'gurochan.css',
			'Photon' => 'photon.css',
			'Tomorrow' => 'tomorrow.css',
			'Ayashii' => 'ayashii.css',
			'Mercury' => 'mercury.css',
		],
		// kokotxt styles
		'kokotxt' => [
			'Pseud0ch' => 'pseud0ch.css',
			'Pseud0ch (serif)' => 'pseud0ch2.css',
			'Pseud0ch (sans-serif)' => 'pseud0ch3.css',
			'Gochannel' => 'gochannel.css',
			'Tomorrow' => 'tomorrow.css',
			'Ayashii' => 'ayashii.css',
			'Blue Moon' => 'bluemoon.css',
			'Futaba' => 'futaba.css',
			'Headline' => 'headline.css',
			'Mercury' => 'mercury.css',
			'Toothpaste' => 'toothpaste.css',
			'VIPPER' => 'vipper.css',
		],
	],

	'KILL_INCOMPLETE_UPLOAD' => 1, // Automatically delete uploaded incomplete additional images

	// Excimer Profiling
	// Requires the Excimer PHP extension (https://www.mediawiki.org/wiki/Excimer)
	// When enabled, speedscope-compatible JSON profiles are saved to global/excimer/{category}/
	// Categories: posting (registRoute), rebuild (mode=rebuild), deleting (adminDel)
	'EXCIMER_PROFILING' => false,

	/*---------------------------------------------------------------------------
	 * Board base defaults (structural / computed).
	 *
	 * NOT surfaced in the per-board configuration editor: they are either structural (storage
	 * layout), derived from other global values (STATIC_URL / STATIC_PATH / paths), or non-scalar
	 * enum values. They form the immutable base beneath the editable configs/ schema and
	 * per-board DB overrides. Board-editable settings live in configs/*.php instead.
	 *--------------------------------------------------------------------------*/

	// Storage layout (relative paths under each board's upload directory)
	'IMG_DIR' => 'src/',
	'THUMB_DIR' => 'src/',

	// Proxy headers inspected when resolving the real client IP
	'PROXYHEADERlist' => [
		'HTTP_CLIENT_IP',
		'HTTP_X_REAL_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
	],

	// Legacy flat-file bans, kept only so Utilities/ban-import-cli.php knows where to look.
	// Bans themselves live in the database; see code/Kokonotsuba/ban/.
	'GLOBAL_BANS' => 'globalbans.log',

	// Name of the token cookie
	'VISITOR_TOKEN_COOKIE' => 'koko',

	// How long that cookie lives, in days.
	'VISITOR_TOKEN_DAYS' => 730,

	// Key the token cookie is signed with, so a hand-edited one is thrown away and reissued
	// rather than believed. Blank falls back to TRIPSALT + IDSEED, which every install has.
	// Changing it invalidates every token cookie; the bans tied to them are untouched.
	'VISITOR_TOKEN_SECRET' => '',

	// Placeholder thumbnails (derived from STATIC_URL)
	'SWF_THUMB' => $staticUrl.'image/swf_thumb.png',
	'AUDIO_THUMB' => $staticUrl.'image/audio.png',
	'ARCHIVE_THUMB' => $staticUrl.'image/archive.png',

	// Navigation links at top left (read from global/toplinks.txt)
	'TOP_LINKS' => @file_get_contents(__DIR__.'/toplinks.txt'),

	// overboard sub header conf, its in here so we can attach functions to it for seeing last post times on other scripts
	'OVERBOARD_SUB_HEADER_HTML' => '',
];

// Site-specific values from global/siteSettings.php win over the defaults above.
return array_replace($config, $siteSettings);
