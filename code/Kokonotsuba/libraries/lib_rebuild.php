<?php
/*
* Library for triggering boards to rebuild their HTML pages
*/

namespace Kokonotsuba\libraries;

use Kokonotsuba\post\postRepository;
use Kokonotsuba\post\postService;

// rebuild all boards' html pages
function rebuildAllBoards(): void {
    $allBoards = GLOBAL_BOARD_ARRAY;

    // rebuild boards
    foreach($allBoards as $board) {
        $board->rebuildBoard(true);
    }

}

// rebuild selected boards from board uid array
function rebuildBoardsByArray(array $boardsToRebuild, bool $logRebuild = false): void {
    // rebuild boards
    foreach($boardsToRebuild as $board) {
        $board->rebuildBoard($logRebuild);
    }

}

/**
 * Rebuild boards that contain the given post UIDs
 *
 * @param array $postUids Array of post UIDs that were affected by an action
 * @param postRepository $postRepository Repository to fetch post data and associated board UIDs
 *
 * @return void
 */
function rebuildBoardsFromPosts(array $postUids, postService $postService, bool $logRebuild = false): void {
    // get board UIDs from post UIDs
    $boardUids = $postService->getBoardUidsFromPostUids($postUids);
	
    // get boards from board UIDs
    $boards = getBoardsByUIDs($boardUids);
	
    // rebuild boards
    rebuildBoardsByArray($boards, $logRebuild);
}