<?php
/**
 * Flooding and rate-limit controls.
 * Defaults preserve the historical board defaults.
 */

return [
	'_group' => 'Flooding & rate limits',

	'RENZOKU'  => ['default' => 0, 'type' => 'int', 'label' => 'Post interval (s)', 'desc' => 'Minimum seconds between posts (0 = off).'],
	'RENZOKU2' => ['default' => 0, 'type' => 'int', 'label' => 'Image post interval (s)', 'desc' => 'Minimum seconds between image posts (0 = off).'],

	'ModuleSettings.RENZOKU3'                        => ['default' => 30, 'type' => 'int', 'label' => 'Seconds between new threads', 'desc' => 'Minimum wait before a poster can start another thread.'],
	'ModuleSettings.SAME_COMMENT_TIME_WINDOW'        => ['default' => 10, 'type' => 'int', 'label' => 'Same-comment window (s)', 'desc' => 'Seconds between posts that can share the same comment.'],
	'ModuleSettings.SAME_THREAD_COMMENT_TIME_WINDOW' => ['default' => 10, 'type' => 'int', 'label' => 'Same OP-comment window (s)', 'desc' => 'Seconds between OP posts that can share the same comment (0 = off).'],
	'ModuleSettings.ALLOWED_COMMENT_REPETITIONS'     => ['default' => 5,  'type' => 'int', 'label' => 'Allowed comment repetitions', 'desc' => 'How many identical comments are allowed in the window before older ones are pruned.'],
	'ModuleSettings.THREAD_REPLY_TIME_LIMIT'         => ['default' => 0,  'type' => 'int', 'label' => 'Thread reply time limit (h)', 'desc' => 'Maximum thread age (hours) allowed for replies (0 = off).'],
];
