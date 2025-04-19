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

