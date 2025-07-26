<?php
/*
* Library for triggering boards to rebuild their HTML pages
*/

// rebuild all boards' html pages
function rebuildAllBoards(): void {
    $allBoards = GLOBAL_BOARD_ARRAY;

    // rebuild boards
    foreach($allBoards as $board) {
        $board->rebuildBoard(true);
    }

}

// rebuild selected boards from board uid array
function rebuildBoardsByArray(array $boardsToRebuild): void {
    // rebuild boards
    foreach($boardsToRebuild as $board) {
        $board->rebuildBoard(true);
    }

}