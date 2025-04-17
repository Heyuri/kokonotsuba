<?php
//post lib

function applyRoll(&$com, &$email){
	$com = "$com\n<p class=\"roll\">[NUMBER: ".rand(1,10000)."]</p>";
	$email = preg_replace('/^roll( *)/i', '', $email);
}

/* Catch impersonators and modify name to display such */ 
function catchFraudsters(&$name) {
	if (preg_match('/[◆◇♢♦⟡★]/u', $name)) $name .= " (fraudster)";
}

function searchBoardArrayForBoard($boards, $targetBoardUID) {
	foreach ($boards as $board) {
		if ($board->getBoardUID() === intval($targetBoardUID)) {
			return $board;
		}
	}
}

function createBoardStoredFilesFromArray($posts) {
	$boardIO = boardIO::getInstance();

	$boards = $boardIO->getAllBoards();
	$files = [];
	foreach($posts as $post) {
		$board = searchBoardArrayForBoard($boards, $post['boardUID']);

		$files[] = new boardStoredFile($post['tim'], $post['ext'], $board);
	}
	return $files;
}