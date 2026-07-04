<?php
/**
 * Page layout, templates, pagination and index/thread list display.
 * Defaults preserve the historical board defaults.
 */

return [
	'_group' => 'Appearance & pagination',

	'HOME'               => ['default' => 'index.html', 'type' => 'string', 'label' => 'Home link target', 'desc' => 'What the [Home] button links to.'],
	'FOOTTEXT'           => ['default' => '', 'type' => 'text', 'label' => 'Footer text', 'desc' => 'HTML shown at the bottom of the page.'],
	'REF_URL'            => ['default' => '', 'type' => 'string', 'label' => 'Referrer URL prefix', 'desc' => 'URL prefix for outbound links (e.g. https://jump.example.net).'],

	'TEMPLATE_FILE'       => ['default' => 'kokoimg', 'type' => 'string', 'label' => 'Index template', 'desc' => 'Template directory for the index page.'],
	'REPLY_TEMPLATE_FILE' => ['default' => 'kokoimg', 'type' => 'string', 'label' => 'Reply template', 'desc' => 'Template directory for the thread/reply page.'],

	'TOP_THREAD_PAGER'   => ['default' => false, 'type' => 'bool', 'label' => 'Top thread pager', 'desc' => 'Render a thread pager at the top of the thread.'],
	'RENDER_REPLY_NUMBER'=> ['default' => true,  'type' => 'bool', 'label' => 'Show reply numbers', 'desc' => 'Show the sequential reply number for each post within a thread.'],
	'REPLIES_PER_PAGE'   => ['default' => 200, 'type' => 'int', 'label' => 'Replies per thread page', 'desc' => 'Replies shown (excluding OP) per thread page.'],

	'PAGE_DEF'           => ['default' => 15, 'type' => 'int', 'label' => 'Threads per page', 'desc' => 'How many threads per index page.'],
	'ADMIN_PAGE_DEF'     => ['default' => 100, 'type' => 'int', 'label' => 'Admin replies per page', 'desc' => 'How many replies per page in the admin panel.'],
	'RE_DEF'             => ['default' => 5, 'type' => 'int', 'label' => 'Replies shown on index', 'desc' => 'Replies shown per thread on the index.'],
	'RE_PAGE_DEF'        => ['default' => 1000, 'type' => 'int', 'label' => 'Replies shown on thread', 'desc' => 'Replies shown on the thread page.'],
	'MAX_RES'            => ['default' => 1000, 'type' => 'int', 'label' => 'Replies before auto-sage', 'desc' => 'How many replies before a thread is auto-saged.'],
	'MAX_THREAD_AMOUNT'  => ['default' => 150, 'type' => 'int', 'label' => 'Max threads per board', 'desc' => 'Threads beyond this are pruned oldest-first.'],
	'MAX_AGE_TIME'       => ['default' => 0, 'type' => 'int', 'label' => 'Age reply window (h)', 'desc' => 'How long a thread accepts age replies, in hours (0 = always).'],

	'STATIC_HTML_UNTIL'  => ['default' => 10, 'type' => 'int', 'label' => 'Static HTML pages', 'desc' => 'How many index pages are statically generated (-1 = all, 0 = portal only).'],
	'GZIP_COMPRESS_LEVEL'=> ['default' => 0, 'type' => 'int', 'label' => 'Gzip level', 'desc' => 'Gzip compression level (1-9, 0 = off).'],
	'MINIFY_HTML'        => ['default' => 0, 'type' => 'int', 'label' => 'Minify HTML', 'desc' => 'Remove unnecessary whitespace from generated HTML (1 = on).'],
	'AUTO_LINK'          => ['default' => 1, 'type' => 'int', 'label' => 'Auto-link URLs', 'desc' => 'Turn URLs in comments into links (1 = on).'],

	// thread list module
	'ModuleSettings.THREADLIST_NUMBER'         => ['default' => 50, 'type' => 'int', 'label' => 'Thread list per page', 'desc' => 'Number of entries shown per thread-list page.'],
	'ModuleSettings.FORCE_SUBJECT'             => ['default' => true, 'type' => 'bool', 'label' => 'Force subject', 'desc' => 'Require a subject for new threads.'],
	'ModuleSettings.SHOW_IN_MAIN'              => ['default' => true, 'type' => 'bool', 'label' => 'Show thread list on main', 'desc' => 'Display the thread list on the main page.'],
	'ModuleSettings.THREADLIST_NUMBER_IN_MAIN' => ['default' => 40, 'type' => 'int', 'label' => 'Thread list on main count', 'desc' => 'Number of entries shown on the main page.'],
	'ModuleSettings.SHOW_FORM'                 => ['default' => false, 'type' => 'bool', 'label' => 'Show thread-list delete form', 'desc' => 'Display the delete form on the thread list.'],
	'ModuleSettings.HIGHLIGHT_COUNT'           => ['default' => 15, 'type' => 'int', 'label' => 'Popular reply highlight', 'desc' => 'Reply count above which the count turns red (0 = off).'],

	// index comment truncator
	'ModuleSettings.CHARACTER_PREVIEW_LIMIT'   => ['default' => 2500, 'type' => 'int', 'label' => 'Preview character limit', 'desc' => 'Max characters shown in an index comment preview.'],
	'ModuleSettings.LINE_PREVIEW_LIMIT'        => ['default' => 10, 'type' => 'int', 'label' => 'Preview line limit', 'desc' => 'Max lines shown in an index comment preview.'],

	// search module
	'ModuleSettings.SEARCH_POSTS_PER_PAGE'     => ['default' => 50, 'type' => 'int', 'label' => 'Search results per page', 'desc' => 'Number of search results shown per page.'],
	'ModuleSettings.SEARCH_TEMPLATE'           => ['default' => 'kokoimg', 'type' => 'string', 'label' => 'Search template', 'desc' => 'Template used to render search results.'],
	'ModuleSettings.DISPLAY_THREADED_FORMAT'   => ['default' => false, 'type' => 'bool', 'label' => 'Threaded search format', 'desc' => 'Display search results in a threaded format.'],

	// additional info lines
	'ModuleSettings.ADD_INFO' => [
		'default' => [
			'Read the <a href="//example.net/rules.html">rules</a> before you post.',
			'Read <a href="//example.net/faq.html">our FAQ</a> for any questions.',
			'Modify this by editing the additional-info setting.',
		],
		'type'  => 'array',
		'label' => 'Additional info lines',
		'desc'  => 'JSON array of HTML lines shown by the additional-info module.',
	],
];
