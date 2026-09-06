<?php

/*
 * Template for global/siteSettings.php: the handful of global values that belong to your site
 * rather than to the software.
 *
 * install.php writes that file for you. Copy this one over it by hand only if you are setting an
 * instance up without the installer:
 *
 *     cp global/siteSettings.example.php global/siteSettings.php
 *
 * It is not tracked by git, so updating the code never conflicts with it. Anything set here
 * overrides globalconfig.php; anything left out or left empty keeps globalconfig's default. Only
 * the keys below are read (see Kokonotsuba\config\siteSettings).
 */

return [
	// Base URL every board URL is built on: {WEBSITE_URL}{board identifier}/
	'WEBSITE_URL' => 'https://example.net/kokonotsuba/boards/',

	// Where the "Home" link in each board's header points. This is the site-wide default; a board
	// can still point its own header elsewhere from the board configuration editor. A full URL, an
	// absolute path, or a file next to the board ('index.html') all work.
	'HOME' => 'https://example.net/',

	// Where static/ is served from, and where it is on disk. Both end in a slash.
	'STATIC_URL' => 'https://example.net/kokonotsuba/static/',
	'STATIC_PATH' => '/var/www/html/kokonotsuba/static/',

	/*
	 * Secure tripcode salt and poster ID seed. Generate with:
	 *   php -r 'echo bin2hex(random_bytes(32));'
	 * Never change them once posts exist: every tripcode and ID on the site would change.
	 */
	'TRIPSALT' => '',
	'IDSEED' => '',

	// Store every board's uploads in one central directory instead of per board.
	'USE_CDN' => false,
	'CDN_DIR' => '/var/www/website/cdn/',
	'CDN_URL' => 'https://cdn.example.net/',

	'PIXMICAT_LANGUAGE' => 'en_US',
];
