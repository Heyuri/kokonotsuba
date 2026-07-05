<?php
/**
 * File upload rules, limits and allowed types (core). Module upload settings (animated GIF,
 * spoiler thumbnails, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, arrayField};

return [
	'_group' => 'Uploads',

	'ATTACHMENT_UPLOAD_LIMIT' => intField('Attachments per post', 1, 'How many files a user can attach to a single post.'),
	'MAX_KB'                  => intField('Max upload size (KB)', 9000, 'Maximum upload size in kilobytes.'),
	'STORAGE_LIMIT'           => intField('Storage limit', 0, 'Per-board storage limit (0 = unlimited).'),
	'STORAGE_MAX'             => intField('Total storage max', 300000, 'Total storage limit.'),

	'TEXTBOARD_ONLY'          => boolField('Textboard only', false, 'Completely disable all file features.'),
	'RESIMG'                  => boolField('Allow files in replies', true, 'Allow files to be attached to replies.'),
	'SHOW_IMGWH'              => boolField('Show image dimensions', true, 'Display the original width/height of the attachment.'),

	'PREVENT_DUPLICATE_FILE_UPLOADS' => boolField('Prevent duplicate uploads', false, 'Disallow the same file being posted twice.'),
	'DUPLICATE_FILE_TIME'     => intField('Duplicate file window (s)', 7200, 'Time a duplicate attachment cannot be re-uploaded.'),

	'VIDEO_EXT'               => stringField('Video extensions', 'WEBM|MP4', 'Pipe-separated filetypes loaded as video.'),
	'HTTP_UPLOAD_DIFF'        => intField('HTTP upload diff', 50, 'Upload timing tolerance.'),

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
	], 'JSON object of extension => mime-type.'),
];
