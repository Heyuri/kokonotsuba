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

/**
 * Toggle a status flag for a thread (applies to OP post).
 */
function toggleThreadStatus(string $flag, array $thread): FlagHelper {
	// get thread singleton instance
	$threadSingleton = threadSingleton::getInstance();

	// fetch OP post of thread
	$opPost = $threadSingleton->fetchPostsFromThread($thread['thread_uid'])[0];

	// delegate to post toggler
	return togglePostStatus($flag, $opPost);
}

/**
 * Toggle a status flag for a single post.
 */
function togglePostStatus(string $flag, array $post): FlagHelper {
	// Get singleton instance to interact with the post database
	$PIO = PIOPDO::getInstance();

	// Create helper with current status
	$flags = new FlagHelper($post['status']);

	// Toggle the specified flag
	$flags->toggle($flag);

	// Save the updated status back to the post
	$PIO->setPostStatus($post['post_uid'], $flags->toString());

	// Return updated flags
	return $flags;
}

function getThreadStatus(string $thread_uid) {
	// get thread singleton instance
	$threadSingleton = threadSingleton::getInstance();
	
	// get op
	$post = $threadSingleton->fetchPostsFromThread($thread_uid)[0];

	// return flag
	$flags = new FlagHelper($post['status']);

	return $flags;
}


/**
* Check if the account session role is at least a janitor
*/
function isActiveStaffSession(): bool {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();
	
	return $roleLevel->isStaff();
}

/**
 * Check if account session has a valid user role
 */

function isLoggedIn() {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();
	
	return $roleLevel->isAtLeast(\Kokonotsuba\Root\Constants\userRole::LEV_USER);
}