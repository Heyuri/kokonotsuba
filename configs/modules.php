<?php
/**
 * Module toggles (config path: ModuleList.*).
 *
 * Each entry enables or disables a module for the board. Defaults preserve the historical
 * ModuleList defaults. See:
 * https://github.com/Heyuri/kokonotsuba/wiki/All-modules
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\boolField;

return [
	'_group' => 'Modules',

	/* modes */
	'ModuleList.catalog'      => boolField('Catalog', true),
	'ModuleList.search'       => boolField('Search', true),
	'ModuleList.threadList'   => boolField('Thread list', true),

	/* admin */
	'ModuleList.rebuild'      => boolField('Rebuild', true),
	'ModuleList.adminDel'     => boolField('Admin delete', true),
	'ModuleList.adminBan'     => boolField('Admin ban', true),
	'ModuleList.fileBan'      => boolField('File ban', true),
	'ModuleList.globalMessage'=> boolField('Global message', true),
	'ModuleList.blotter'      => boolField('Blotter', true),
	'ModuleList.janitor'      => boolField('Janitor', true),
	'ModuleList.moveThread'   => boolField('Move thread', true),
	'ModuleList.rawHtml'      => boolField('Raw HTML', true),
	'ModuleList.deletedPosts' => boolField('Deleted posts', true),
	'ModuleList.cssHax'       => boolField('CSS hax', true),
	'ModuleList.notes'        => boolField('Notes', true),
	'ModuleList.edit'         => boolField('Edit posts', true),
	'ModuleList.perceptualBan'=> boolField('Perceptual ban', true),
	'ModuleList.excimerViewer'=> boolField('Excimer profile viewer', true),
	'ModuleList.anonIp'       => boolField('Anonymize IPs', true),

	/* thread modes */
	'ModuleList.autoSage'     => boolField('Auto-sage', true),
	'ModuleList.lockThread'   => boolField('Lock thread', true),
	'ModuleList.oldThread'    => boolField('Old thread', true),
	'ModuleList.sticky'       => boolField('Sticky', true),

	/* posting */
	'ModuleList.antiSpam'     => boolField('Anti-spam', true),
	'ModuleList.csrfPrevent'  => boolField('CSRF prevention', true),
	'ModuleList.bbCode'       => boolField('BBCode', true),
	'ModuleList.emoji'        => boolField('Emoji', true),
	'ModuleList.wordFilter'   => boolField('Word filter', true),
	'ModuleList.countryFlags' => boolField('Country flags', false),
	'ModuleList.antiFlood'    => boolField('Anti-flood', true),
	'ModuleList.fieldTraps'   => boolField('Field traps', true),
	'ModuleList.readOnly'     => boolField('Read-only', false),
	'ModuleList.viewPosts'    => boolField('View posts', true),
	'ModuleList.displayId'    => boolField('Display ID', true),
	'ModuleList.dice'         => boolField('Dice', true),
	'ModuleList.tripcode'     => boolField('Tripcode', true),
	'ModuleList.displayIp'    => boolField('Display IP', true),
	'ModuleList.animatedGif'  => boolField('Animated GIF', true),
	'ModuleList.tegaki'       => boolField('Tegaki (oekaki)', true),
	'ModuleList.quickReply'   => boolField('Quick reply', true),
	'ModuleList.spoiler'      => boolField('Spoiler', true),
	'ModuleList.threadWatcher'=> boolField('Thread watcher', true),

	/* misc */
	'ModuleList.soudane'      => boolField('Soudane (voting)', true),
	'ModuleList.postApi'      => boolField('Post API', true),
	'ModuleList.privateMessage'=> boolField('Private messages', true),
	'ModuleList.fullBanner'   => boolField('Full banner', true),
	'ModuleList.imageMeta'    => boolField('Image metadata', true),
	'ModuleList.onlineCounter'=> boolField('Online counter', true),
	'ModuleList.ads'          => boolField('Ads', true),
	'ModuleList.banner'       => boolField('Banner', true),
	'ModuleList.addInfo'      => boolField('Additional info', true),
	'ModuleList.imageServer'  => boolField('Image server', true),
	'ModuleList.filter'       => boolField('Filter', true),
	'ModuleList.indexCommentTruncator' => boolField('Index comment truncator', true),
	'ModuleList.emotes'       => boolField('Emotes', true),
	'ModuleList.nameRandomizer'=> boolField('Name randomizer', false),
	'ModuleList.youtubeEmbed' => boolField('YouTube embed', true),
	'ModuleList.segregator'   => boolField('Segregator', false),
];
