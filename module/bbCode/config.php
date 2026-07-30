<?php
/**
 * Config schema for the bbCode module (namespace: modules.bbCode.*).
 * Read via $this->getModuleConfig('KEY').
 *
 * These get an editor group of their own rather than folding into "Content & formatting" — there
 * are enough of them to bury everything else in that group. '_module' is deliberately empty: the
 * group legend already says BBCode, so a module sub-header would just repeat it.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'BBCode',
	'_module' => '',

	'supportBold' => boolField('config_label_modules.bbCode.supportBold', true, ''),
	'supportSpoiler' => boolField('config_label_modules.bbCode.supportSpoiler', true, ''),
	'supportStrikeThrough' => boolField('config_label_modules.bbCode.supportStrikeThrough', true, ''),
	'supportHeading' => boolField('config_label_modules.bbCode.supportHeading', true, ''),
	'supportCode' => boolField('config_label_modules.bbCode.supportCode', true, ''),
	'supportCodeBlocks' => boolField('config_label_modules.bbCode.supportCodeBlocks', true, ''),
	'supportItalic' => boolField('config_label_modules.bbCode.supportItalic', true, ''),
	'supportUnderline' => boolField('config_label_modules.bbCode.supportUnderline', true, ''),
	'supportParagraph' => boolField('config_label_modules.bbCode.supportParagraph', true, ''),
	'supportSw' => boolField('config_label_modules.bbCode.supportSw', true, ''),
	'supportColor' => boolField('config_label_modules.bbCode.supportColor', true, ''),
	'supportColorBg' => boolField('config_label_modules.bbCode.supportColorBg', true, ''),
	'supportNeon' => boolField('config_label_modules.bbCode.supportNeon', true, ''),
	'supportTextShadow' => boolField('config_label_modules.bbCode.supportTextShadow', true, ''),
	'supportPartybus' => boolField('config_label_modules.bbCode.supportPartybus', true, ''),
	'supportEcho' => boolField('config_label_modules.bbCode.supportEcho', true, ''),
	'supportFontSize' => boolField('config_label_modules.bbCode.supportFontSize', true, ''),
	'supportPre' => boolField('config_label_modules.bbCode.supportPre', true, ''),
	'supportQuote' => boolField('config_label_modules.bbCode.supportQuote', true, ''),
	'supportRuby' => boolField('config_label_modules.bbCode.supportRuby', true, ''),
	'supportURL' => boolField('config_label_modules.bbCode.supportURL', false, ''),
	'supportEmail' => boolField('config_label_modules.bbCode.supportEmail', false, ''),
	'supportImg' => boolField('config_label_modules.bbCode.supportImg', false, ''),
	'supportScroll' => boolField('config_label_modules.bbCode.supportScroll', true, ''),
	'supportKao' => boolField('config_label_modules.bbCode.supportKao', true, ''),
];
