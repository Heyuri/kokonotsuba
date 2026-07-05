<?php
/**
 * Config schema for the bbCode module (namespace: modules.bbCode.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'BBCode',

	'supportBold' => boolField('BBCode: bold', true, ''),
	'supportSpoiler' => boolField('BBCode: spoiler', true, ''),
	'supportStrikeThrough' => boolField('BBCode: strikethrough', true, ''),
	'supportHeading' => boolField('BBCode: heading', true, ''),
	'supportCode' => boolField('BBCode: code', true, ''),
	'supportCodeBlocks' => boolField('BBCode: code blocks', true, ''),
	'supportItalic' => boolField('BBCode: italic', true, ''),
	'supportUnderline' => boolField('BBCode: underline', true, ''),
	'supportParagraph' => boolField('BBCode: paragraph', true, ''),
	'supportSw' => boolField('BBCode: strange-world AA', true, ''),
	'supportColor' => boolField('BBCode: color', true, ''),
	'supportColorBg' => boolField('BBCode: background color', true, ''),
	'supportNeon' => boolField('BBCode: neon', true, ''),
	'supportTextShadow' => boolField('BBCode: text shadow', true, ''),
	'supportPartybus' => boolField('BBCode: partybus', true, ''),
	'supportEcho' => boolField('BBCode: echo', true, ''),
	'supportFontSize' => boolField('BBCode: font size', true, ''),
	'supportPre' => boolField('BBCode: pre', true, ''),
	'supportQuote' => boolField('BBCode: quote', true, ''),
	'supportRuby' => boolField('BBCode: ruby', true, ''),
	'supportURL' => boolField('BBCode: URL', false, ''),
	'supportEmail' => boolField('BBCode: email', false, ''),
	'supportImg' => boolField('BBCode: img', false, ''),
	'supportScroll' => boolField('BBCode: scroll', true, ''),
	'supportKao' => boolField('BBCode: kao', true, ''),
];
