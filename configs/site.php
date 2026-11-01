<?php
/**
 * Site identity and front page config defaults. Every field is site-wide.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{arrayField, boolField, intField, stringField, textField};

return [
	'_group' => 'Site',

	'SITE.NAME'             => stringField('config_label_SITE.NAME', 'Kokonotsuba', 'config_desc_SITE.NAME', global: true),
	'SITE.DESCRIPTION'      => textField('config_label_SITE.DESCRIPTION', '', 'config_desc_SITE.DESCRIPTION', global: true),
	'SITE.SHOW_TITLE'       => boolField('config_label_SITE.SHOW_TITLE', true, 'config_desc_SITE.SHOW_TITLE', global: true),
	'SITE.LOGO'             => stringField('config_label_SITE.LOGO', '', 'config_desc_SITE.LOGO', global: true),
	'SITE.FAVICON'          => stringField('config_label_SITE.FAVICON', '', 'config_desc_SITE.FAVICON', global: true),
	'SITE.KEYWORDS'         => stringField('config_label_SITE.KEYWORDS', '', 'config_desc_SITE.KEYWORDS', global: true),
	'SITE.THEME_COLOR'      => stringField('config_label_SITE.THEME_COLOR', '', 'config_desc_SITE.THEME_COLOR', global: true),
	'SITE.RANDOM_SUBTITLES' => boolField('config_label_SITE.RANDOM_SUBTITLES', true, 'config_desc_SITE.RANDOM_SUBTITLES', global: true),
	'SITE.SUBTITLES'        => arrayField('config_label_SITE.SUBTITLES', [], 'config_desc_SITE.SUBTITLES', global: true),
	'SITE.SHOW_POST_STATS'  => boolField('config_label_SITE.SHOW_POST_STATS', true, 'config_desc_SITE.SHOW_POST_STATS', global: true),
	'SITE.SHOW_BOARD_LIST'  => boolField('config_label_SITE.SHOW_BOARD_LIST', false, 'config_desc_SITE.SHOW_BOARD_LIST', global: true),
	'SITE.BOARD_LIST_STATS' => boolField('config_label_SITE.BOARD_LIST_STATS', true, 'config_desc_SITE.BOARD_LIST_STATS', global: true),
	'SITE.SHOW_GLOBAL_MESSAGE' => boolField('config_label_SITE.SHOW_GLOBAL_MESSAGE', true, 'config_desc_SITE.SHOW_GLOBAL_MESSAGE', global: true),
	'SITE.SHOW_BLOTTER'     => boolField('config_label_SITE.SHOW_BLOTTER', false, 'config_desc_SITE.SHOW_BLOTTER', global: true),
	'SITE.NEWS_PER_PAGE'    => intField('config_label_SITE.NEWS_PER_PAGE', 5, 'config_desc_SITE.NEWS_PER_PAGE', min: 1, global: true),
	'SITE.NAV_LINKS'        => arrayField('config_label_SITE.NAV_LINKS', [], 'config_desc_SITE.NAV_LINKS', global: true, map: true),
];
