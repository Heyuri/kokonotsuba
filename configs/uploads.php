<?php
/**
 * File upload rules, limits and allowed types (core). Module upload settings (animated GIF,
 * spoiler thumbnails, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, arrayField};

return [
	'_group' => 'Uploads',

	'ATTACHMENT_UPLOAD_LIMIT' => intField('config_label_ATTACHMENT_UPLOAD_LIMIT', 1, 'config_desc_ATTACHMENT_UPLOAD_LIMIT'),
	'MAX_KB'                  => intField('config_label_MAX_KB', 9000, 'config_desc_MAX_KB'),
	'STORAGE_LIMIT'           => intField('config_label_STORAGE_LIMIT', 0, 'config_desc_STORAGE_LIMIT'),
	'STORAGE_MAX'             => intField('config_label_STORAGE_MAX', 300000, 'config_desc_STORAGE_MAX'),

	'TEXTBOARD_ONLY'          => boolField('config_label_TEXTBOARD_ONLY', false, 'config_desc_TEXTBOARD_ONLY'),
	'RESIMG'                  => boolField('config_label_RESIMG', true, 'config_desc_RESIMG'),
	'SHOW_IMGWH'              => boolField('config_label_SHOW_IMGWH', true, 'config_desc_SHOW_IMGWH'),

	'PREVENT_DUPLICATE_FILE_UPLOADS' => boolField('config_label_PREVENT_DUPLICATE_FILE_UPLOADS', false, 'config_desc_PREVENT_DUPLICATE_FILE_UPLOADS'),
	'DUPLICATE_FILE_TIME'     => intField('config_label_DUPLICATE_FILE_TIME', 7200, 'config_desc_DUPLICATE_FILE_TIME'),

	'VIDEO_EXT'               => arrayField('config_label_VIDEO_EXT', ['webm', 'mp4'], 'config_desc_VIDEO_EXT'),
	'HTTP_UPLOAD_DIFF'        => intField('config_label_HTTP_UPLOAD_DIFF', 50, 'config_desc_HTTP_UPLOAD_DIFF'),

	'ALLOW_UPLOAD_EXT' => arrayField('config_label_ALLOW_UPLOAD_EXT', [
		'gif'  => 'image/gif',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'bmp'  => 'image/bmp',
		'webp' => 'image/webp',
		'swf'  => 'application/x-shockwave-flash',
		'webm' => 'video/webm',
		'mp4'  => 'video/mp4',
		'mp3'  => 'audio/mpeg',
	], 'config_desc_ALLOW_UPLOAD_EXT'),
];
