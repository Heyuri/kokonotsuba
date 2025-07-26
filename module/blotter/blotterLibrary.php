<?php

namespace Kokonotsuba\Modules\blotter;

function getBlotterFileData(string $blotterPath): array {
	static $data = [];
	if (!empty($data)){
		return $data;
	}

	if (file_exists($blotterPath)) {
		$lines = file($blotterPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			// Assuming each line in the file is formatted as COMMENT<>DATE
			list($date, $comment, $uid) = explode('<>', $line);
			$data[] = [
				'date' => $date,
				'comment' => $comment,
				'uid' => $uid ?? 0,
				];
		}
	}

	usort($data, function($a, $b) {
		return strtotime($b['date']) - strtotime($a['date']);
	});

	return $data;
}