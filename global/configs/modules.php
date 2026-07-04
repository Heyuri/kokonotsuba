<?php
/**
 * Module toggles (config path: ModuleList.*).
 *
 * Each entry enables or disables a module for the board. Defaults preserve the historical
 * ModuleList defaults. See:
 * https://github.com/Heyuri/kokonotsuba/wiki/All-modules
 */

$m = static fn(string $label, bool $default, string $desc = ''): array => [
	'default' => $default,
	'type'    => 'bool',
	'label'   => $label,
	'desc'    => $desc,
];

return [
	'_group' => 'Modules',

	/* modes */
	'ModuleList.catalog'      => $m('Catalog', true),
	'ModuleList.search'       => $m('Search', true),
	'ModuleList.threadList'   => $m('Thread list', true),

	/* admin */
	'ModuleList.rebuild'      => $m('Rebuild', true),
	'ModuleList.adminDel'     => $m('Admin delete', true),
	'ModuleList.adminBan'     => $m('Admin ban', true),
	'ModuleList.fileBan'      => $m('File ban', true),
	'ModuleList.globalMessage'=> $m('Global message', true),
	'ModuleList.blotter'      => $m('Blotter', true),
	'ModuleList.janitor'      => $m('Janitor', true),
	'ModuleList.moveThread'   => $m('Move thread', true),
	'ModuleList.rawHtml'      => $m('Raw HTML', true),
	'ModuleList.deletedPosts' => $m('Deleted posts', true),
	'ModuleList.cssHax'       => $m('CSS hax', true),
	'ModuleList.notes'        => $m('Notes', true),
	'ModuleList.edit'         => $m('Edit posts', true),
	'ModuleList.perceptualBan'=> $m('Perceptual ban', true),
	'ModuleList.excimerViewer'=> $m('Excimer profile viewer', true),
	'ModuleList.anonIp'       => $m('Anonymize IPs', true),

	/* thread modes */
	'ModuleList.autoSage'     => $m('Auto-sage', true),
	'ModuleList.lockThread'   => $m('Lock thread', true),
	'ModuleList.oldThread'    => $m('Old thread', true),
	'ModuleList.sticky'       => $m('Sticky', true),

	/* posting */
	'ModuleList.antiSpam'     => $m('Anti-spam', true),
	'ModuleList.csrfPrevent'  => $m('CSRF prevention', true),
	'ModuleList.bbCode'       => $m('BBCode', true),
	'ModuleList.emoji'        => $m('Emoji', true),
	'ModuleList.wordFilter'   => $m('Word filter', true),
	'ModuleList.countryFlags' => $m('Country flags', false),
	'ModuleList.antiFlood'    => $m('Anti-flood', true),
	'ModuleList.fieldTraps'   => $m('Field traps', true),
	'ModuleList.readOnly'     => $m('Read-only', false),
	'ModuleList.viewPosts'    => $m('View posts', true),
	'ModuleList.displayId'    => $m('Display ID', true),
	'ModuleList.dice'         => $m('Dice', true),
	'ModuleList.tripcode'     => $m('Tripcode', true),
	'ModuleList.displayIp'    => $m('Display IP', true),
	'ModuleList.animatedGif'  => $m('Animated GIF', true),
	'ModuleList.tegaki'       => $m('Tegaki (oekaki)', true),
	'ModuleList.quickReply'   => $m('Quick reply', true),
	'ModuleList.spoiler'      => $m('Spoiler', true),
	'ModuleList.threadWatcher'=> $m('Thread watcher', true),

	/* misc */
	'ModuleList.soudane'      => $m('Soudane (voting)', true),
	'ModuleList.postApi'      => $m('Post API', true),
	'ModuleList.privateMessage'=> $m('Private messages', true),
	'ModuleList.fullBanner'   => $m('Full banner', true),
	'ModuleList.imageMeta'    => $m('Image metadata', true),
	'ModuleList.onlineCounter'=> $m('Online counter', true),
	'ModuleList.ads'          => $m('Ads', true),
	'ModuleList.banner'       => $m('Banner', true),
	'ModuleList.addInfo'      => $m('Additional info', true),
	'ModuleList.imageServer'  => $m('Image server', true),
	'ModuleList.filter'       => $m('Filter', true),
	'ModuleList.indexCommentTruncator' => $m('Index comment truncator', true),
	'ModuleList.emotes'       => $m('Emotes', true),
	'ModuleList.nameRandomizer'=> $m('Name randomizer', false),
	'ModuleList.youtubeEmbed' => $m('YouTube embed', true),
	'ModuleList.segregator'   => $m('Segregator', false),
];
