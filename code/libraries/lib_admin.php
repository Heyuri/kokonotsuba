<?php
//This file contains functions for koko management mode and related features

function getCurrentStorageSizeFromSelectedBoards(array $boards) {
	$FileIO = PMCLibrary::getFileIOInstance();
	$totalBoardsStorageSize = 0;

	foreach($boards as $board) {
		$totalBoardsStorageSize += $FileIO->getCurrentStorageSize($board);
	}
	return $totalBoardsStorageSize;
}

/* So threads can be locked without repeating the same code */
function lockThread(array $thread): void {
	// get singleton instances
	$PIO = PIOPDO::getInstance();
	$threadSingleton = threadSingleton::getInstance();

	// get OP
	$opPost = $threadSingleton->fetchPostsFromThread($thread['thread_uid'])[0];

	// lock
	$flags = new FlagHelper($opPost['status']);
	$flags->toggle('stop');
	$PIO->setPostStatus($opPost['post_uid'], $flags->toString());	
}