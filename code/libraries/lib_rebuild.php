<?php
/*
* Library for triggering boards to rebuild their HTML pages
*/

// rebuild all boards' html pages
function rebuildAllBoards() {
    $boardIO = boardIO::getInstance();
    $allBoards = $boardIO->getAllRegularBoards();

    // rebuild boards
    foreach($allBoards as $board) {
        $board->rebuildBoard(true);
    }

}

// rebuild selected boards from board uid array
function rebuildBoardsByUIDs(array $boardUIDs) {
    $boardIO = boardIO::getInstance();
    $boardsToRebuild = $boardIO->getBoardsFromUIDs($boardUIDs);

    if(!$boardsToRebuild) return; // no boards found

    // rebuild boards
    foreach($boardsToRebuild as $board) {
        $board->rebuildBoard(true);;
    }

}