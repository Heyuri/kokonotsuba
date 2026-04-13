<?php

use Kokonotsuba\cookie\cookieService;

/* 
* 
* This file is for initializing cookies 
* 
*/

$cookieService = new cookieService($_COOKIE);

// initialize the cookie value if it doesn't exist
if(!$cookieService->has('viewDeletedPosts')) {
	$cookieService->set('viewDeletedPosts', '1', time() + (86400 * 30), '/');
}