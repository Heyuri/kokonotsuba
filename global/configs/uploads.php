<?php
/**
 * File upload rules, limits, allowed types and thumbnails.
 * Defaults preserve the historical board defaults.
 */

return [
	'_group' => 'Uploads',

	'ATTACHMENT_UPLOAD_LIMIT' => ['default' => 1, 'type' => 'int', 'label' => 'Attachments per post', 'desc' => 'How many files a user can attach to a single post.'],
	'MAX_KB'                  => ['default' => 9000, 'type' => 'int', 'label' => 'Max upload size (KB)', 'desc' => 'Maximum upload size in kilobytes.'],
	'STORAGE_LIMIT'           => ['default' => 0, 'type' => 'int', 'label' => 'Storage limit', 'desc' => 'Per-board storage limit (0 = unlimited).'],
	'STORAGE_MAX'             => ['default' => 300000, 'type' => 'int', 'label' => 'Total storage max', 'desc' => 'Total storage limit.'],

	'TEXTBOARD_ONLY'          => ['default' => 0, 'type' => 'int', 'label' => 'Textboard only', 'desc' => 'Completely disable all file features (1 = textboard).'],
	'RESIMG'                  => ['default' => 1, 'type' => 'int', 'label' => 'Allow files in replies', 'desc' => 'Allow files to be attached to replies (1 = on).'],
	'SHOW_IMGWH'              => ['default' => 1, 'type' => 'int', 'label' => 'Show image dimensions', 'desc' => 'Display the original width/height of the attachment (1 = on).'],

	'PREVENT_DUPLICATE_FILE_UPLOADS' => ['default' => false, 'type' => 'bool', 'label' => 'Prevent duplicate uploads', 'desc' => 'Disallow the same file being posted twice.'],
	'DUPLICATE_FILE_TIME'     => ['default' => 7200, 'type' => 'int', 'label' => 'Duplicate file window (s)', 'desc' => 'Time a duplicate attachment cannot be re-uploaded.'],

	'VIDEO_EXT'               => ['default' => 'WEBM|MP4', 'type' => 'string', 'label' => 'Video extensions', 'desc' => 'Pipe-separated filetypes loaded as video.'],
	'HTTP_UPLOAD_DIFF'        => ['default' => 50, 'type' => 'int', 'label' => 'HTTP upload diff', 'desc' => 'Upload timing tolerance.'],

	'ALLOW_UPLOAD_EXT' => [
		'default' => [
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
		],
		'type'  => 'array',
		'label' => 'Allowed upload types',
		'desc'  => 'JSON object of extension => mime-type.',
	],

	// animated gif
	'ModuleSettings.MAX_SIZE_FOR_ANIMATED_GIF' => ['default' => 2000, 'type' => 'int', 'label' => 'Max animated GIF size (KB)', 'desc' => 'Maximum file size for animated GIFs.'],

	// spoiler thumbnails
	'ModuleSettings.SPOILER_THUMB_W' => ['default' => 255, 'type' => 'int', 'label' => 'Spoiler thumb width', 'desc' => 'Width in pixels of the spoiler thumbnail.'],
	'ModuleSettings.SPOILER_THUMB_H' => ['default' => 255, 'type' => 'int', 'label' => 'Spoiler thumb height', 'desc' => 'Height in pixels of the spoiler thumbnail.'],
];
