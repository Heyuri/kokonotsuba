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
	'ModuleList.catalog'      => boolField('config_label_ModuleList.catalog', true),
	'ModuleList.search'       => boolField('config_label_ModuleList.search', true),
	'ModuleList.threadList'   => boolField('config_label_ModuleList.threadList', true),

	/* admin */
	'ModuleList.rebuild'      => boolField('config_label_ModuleList.rebuild', true),
	'ModuleList.adminDel'     => boolField('config_label_ModuleList.adminDel', true),
	'ModuleList.adminBan'     => boolField('config_label_ModuleList.adminBan', true),
	'ModuleList.fileBan'      => boolField('config_label_ModuleList.fileBan', true),
	'ModuleList.globalMessage'=> boolField('config_label_ModuleList.globalMessage', true),
	'ModuleList.blotter'      => boolField('config_label_ModuleList.blotter', true),
	'ModuleList.janitor'      => boolField('config_label_ModuleList.janitor', true),
	'ModuleList.moveThread'   => boolField('config_label_ModuleList.moveThread', true),
	'ModuleList.rawHtml'      => boolField('config_label_ModuleList.rawHtml', true),
	'ModuleList.deletedPosts' => boolField('config_label_ModuleList.deletedPosts', true),
	'ModuleList.cssHax'       => boolField('config_label_ModuleList.cssHax', true),
	'ModuleList.notes'        => boolField('config_label_ModuleList.notes', true),
	'ModuleList.edit'         => boolField('config_label_ModuleList.edit', true),
	'ModuleList.perceptualBan'=> boolField('config_label_ModuleList.perceptualBan', true),
	'ModuleList.excimerViewer'=> boolField('config_label_ModuleList.excimerViewer', true),
	'ModuleList.anonIp'       => boolField('config_label_ModuleList.anonIp', true),
	'ModuleList.report'       => boolField('config_label_ModuleList.report', true),

	/* thread modes */
	'ModuleList.autoSage'     => boolField('config_label_ModuleList.autoSage', true),
	'ModuleList.lockThread'   => boolField('config_label_ModuleList.lockThread', true),
	'ModuleList.oldThread'    => boolField('config_label_ModuleList.oldThread', true),
	'ModuleList.sticky'       => boolField('config_label_ModuleList.sticky', true),

	/* posting */
	'ModuleList.antiSpam'     => boolField('config_label_ModuleList.antiSpam', true),
	'ModuleList.csrfPrevent'  => boolField('config_label_ModuleList.csrfPrevent', true),
	'ModuleList.bbCode'       => boolField('config_label_ModuleList.bbCode', true),
	'ModuleList.emoji'        => boolField('config_label_ModuleList.emoji', true),
	'ModuleList.wordFilter'   => boolField('config_label_ModuleList.wordFilter', true),
	'ModuleList.countryFlags' => boolField('config_label_ModuleList.countryFlags', false),
	'ModuleList.antiFlood'    => boolField('config_label_ModuleList.antiFlood', true),
	'ModuleList.fieldTraps'   => boolField('config_label_ModuleList.fieldTraps', true),
	'ModuleList.readOnly'     => boolField('config_label_ModuleList.readOnly', false),
	'ModuleList.viewPosts'    => boolField('config_label_ModuleList.viewPosts', true),
	'ModuleList.displayId'    => boolField('config_label_ModuleList.displayId', true),
	'ModuleList.dice'         => boolField('config_label_ModuleList.dice', true),
	'ModuleList.tripcode'     => boolField('config_label_ModuleList.tripcode', true),
	'ModuleList.displayIp'    => boolField('config_label_ModuleList.displayIp', true),
	'ModuleList.animatedGif'  => boolField('config_label_ModuleList.animatedGif', true),
	'ModuleList.tegaki'       => boolField('config_label_ModuleList.tegaki', true),
	'ModuleList.quickReply'   => boolField('config_label_ModuleList.quickReply', true),
	'ModuleList.spoiler'      => boolField('config_label_ModuleList.spoiler', true),
	'ModuleList.threadWatcher'=> boolField('config_label_ModuleList.threadWatcher', true),

	/* misc */
	'ModuleList.soudane'      => boolField('config_label_ModuleList.soudane', true),
	'ModuleList.postApi'      => boolField('config_label_ModuleList.postApi', true),
	'ModuleList.privateMessage'=> boolField('config_label_ModuleList.privateMessage', true),
	'ModuleList.fullBanner'   => boolField('config_label_ModuleList.fullBanner', true),
	'ModuleList.imageMeta'    => boolField('config_label_ModuleList.imageMeta', true),
	'ModuleList.onlineCounter'=> boolField('config_label_ModuleList.onlineCounter', true),
	'ModuleList.ads'          => boolField('config_label_ModuleList.ads', true),
	'ModuleList.banner'       => boolField('config_label_ModuleList.banner', true),
	'ModuleList.addInfo'      => boolField('config_label_ModuleList.addInfo', true),
	'ModuleList.imageServer'  => boolField('config_label_ModuleList.imageServer', true),
	'ModuleList.filter'       => boolField('config_label_ModuleList.filter', true),
	'ModuleList.indexCommentTruncator' => boolField('config_label_ModuleList.indexCommentTruncator', true),
	'ModuleList.emotes'       => boolField('config_label_ModuleList.emotes', true),
	'ModuleList.nameRandomizer'=> boolField('config_label_ModuleList.nameRandomizer', false),
	'ModuleList.youtubeEmbed' => boolField('config_label_ModuleList.youtubeEmbed', true),
	'ModuleList.segregator'   => boolField('config_label_ModuleList.segregator', false),
	'ModuleList.linkCleaner' => boolField('config_label_ModuleList.linkCleaner', true),
];
