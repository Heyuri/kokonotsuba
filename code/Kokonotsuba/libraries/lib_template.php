<?php

namespace Kokonotsuba\libraries;

function bindThreadValuesToTemplate(string $threadUid,
	int $postOpNumber,
	int $postOpPostUid,
	int $boardUid,
	string $lastReplyTime,
	string $lastBumpTime,
	string $threadCreatedTime,
	string $formattedThreadCreatedTime): array {
	return [
		'{$THREAD_UID}' => $threadUid,
		'{$POST_OP_NUMBER}' => $postOpNumber,
		'{$POST_OP_POST_UID}' => $postOpPostUid,
		'{$BOARD_UID}' => $boardUid,
		'{$LAST_REPLY_TIME}' => $lastReplyTime,
		'{$LAST_BUMP_TIME}' => $lastBumpTime,
		'{$THREAD_CREATED_TIME}' => $threadCreatedTime,
		'{$FORMATTED_THREAD_CREATED_TIME}' => $formattedThreadCreatedTime,
		'{$MODULE_THREAD_CSS_CLASSES}' => '',
		'{$MODULE_THREAD_HEADER}' => ''
	];
}