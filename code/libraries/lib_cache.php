<?php
/*
* Caching library for Kokonotsuba!
* Functions related to html caching will go here
*/

function getThreadCacheArray(int $board_uid): array {
	// Access the threadCacheSingleton instance
    $threadCacheSingleton = threadCacheSingleton::getInstance();

    // Static variable to cache the result
    static $cachedThreadsFromBoard = [];
	static $cachedThreadArray = [];

	if(!empty($cachedThreadArray)) return $cachedThreadArray;

    // If data is not cached, fetch and cache it
    if (empty($cachedThreadsFromBoard)) {
        $cachedThreadsFromBoard = $threadCacheSingleton->getAllThreadCachesFromBoard($board_uid);
    }

    // If the cached threads are empty, return an empty array
    if (empty($cachedThreadsFromBoard)) {
        return [];
    }

    // Build an array with thread_uid as the key for quick look-up
    
    foreach ($cachedThreadsFromBoard as $cachedThread) {
        $thread_uid = $cachedThread->getThreadUid();
        $cachedThreadArray[$thread_uid] = $cachedThread;
    }

	return $cachedThreadArray;
}


function createThreadCache(int $boardUid, string $threadUid, string $threadHtml, string $threadIndexHtml): void {
	$threadCacheSingleton = threadCacheSingleton::getInstance();

	// validation
	if(empty($threadUid)) {
		throw new Exception("Empty thread_uid for thread cache");
	}

	if(empty($threadHtml) || empty($threadIndexHtml)) {
		throw new Exception("Empty html data for thread cache");
	}

	$threadCacheSingleton->insertThreadCache($boardUid, $threadUid, $threadHtml, $threadIndexHtml);
}

function deleteThreadCache(string $thread_uid): void {
	$threadCacheSingleton = threadCacheSingleton::getInstance();

	if(empty($thread_uid)) {
		throw new Exception("Empty thread uid for thread cache removal");
	}

	$threadCacheSingleton->deleteThreadCacheByThreadUid($thread_uid);
}

function updateThreadCache(string $thread_uid): void {
	$threadCacheSingleton = threadCacheSingleton::getInstance();

	if(empty($thread_uid)) {
		throw new Exception("Empty thread uid for thread cache removal");
	}


}