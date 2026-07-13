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

	'supportBold' => boolField('Bold', true, ''),
	'supportSpoiler' => boolField('Spoiler', true, ''),
	'supportStrikeThrough' => boolField('Strikethrough', true, ''),
	'supportHeading' => boolField('Heading', true, ''),
	'supportCode' => boolField('Code', true, ''),
	'supportCodeBlocks' => boolField('Code blocks', true, ''),
	'supportItalic' => boolField('Italic', true, ''),
	'supportUnderline' => boolField('Underline', true, ''),
	'supportParagraph' => boolField('Paragraph', true, ''),
	'supportSw' => boolField('Strange-world AA', true, ''),
	'supportColor' => boolField('Color', true, ''),
	'supportColorBg' => boolField('Background color', true, ''),
	'supportNeon' => boolField('Neon', true, ''),
	'supportTextShadow' => boolField('Text shadow', true, ''),
	'supportPartybus' => boolField('Partybus', true, ''),
	'supportEcho' => boolField('Echo', true, ''),
	'supportFontSize' => boolField('Font size', true, ''),
	'supportPre' => boolField('Pre', true, ''),
	'supportQuote' => boolField('Quote', true, ''),
	'supportRuby' => boolField('Ruby', true, ''),
	'supportURL' => boolField('URL', false, ''),
	'supportEmail' => boolField('Email', false, ''),
	'supportImg' => boolField('Img', false, ''),
	'supportScroll' => boolField('Scroll', true, ''),
	'supportKao' => boolField('Kao', true, ''),
];
