<?php
/**
 * File upload rules, limits and allowed types (core). Module upload settings (animated GIF,
 * spoiler thumbnails, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, arrayField};

return [
	'_group' => 'Uploads',

	'ATTACHMENT_UPLOAD_LIMIT' => intField('Attachments per post', 1, 'config_desc_ATTACHMENT_UPLOAD_LIMIT'),
	'MAX_KB'                  => intField('Max upload size (KB)', 9000, 'config_desc_MAX_KB'),
	'STORAGE_LIMIT'           => intField('Storage limit', 0, 'config_desc_STORAGE_LIMIT'),
	'STORAGE_MAX'             => intField('Total storage max', 300000, 'config_desc_STORAGE_MAX'),

	'TEXTBOARD_ONLY'          => boolField('Textboard only', false, 'config_desc_TEXTBOARD_ONLY'),
	'RESIMG'                  => boolField('Allow files in replies', true, 'config_desc_RESIMG'),
	'SHOW_IMGWH'              => boolField('Show image dimensions', true, 'config_desc_SHOW_IMGWH'),

	'PREVENT_DUPLICATE_FILE_UPLOADS' => boolField('Prevent duplicate uploads', false, 'config_desc_PREVENT_DUPLICATE_FILE_UPLOADS'),
	'DUPLICATE_FILE_TIME'     => intField('Duplicate file window (s)', 7200, 'config_desc_DUPLICATE_FILE_TIME'),

	'VIDEO_EXT'               => arrayField('Video extensions', ['webm', 'mp4'], 'config_desc_VIDEO_EXT'),
	'HTTP_UPLOAD_DIFF'        => intField('HTTP upload diff', 50, 'config_desc_HTTP_UPLOAD_DIFF'),

	'ALLOW_UPLOAD_EXT' => arrayField('Allowed upload types', [
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
