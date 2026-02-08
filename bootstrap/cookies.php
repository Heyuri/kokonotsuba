<?php

/* 
* 
* This file is for initializing cookies 
* 
*/

// initialize the cookie value if it doesn't exist
if(!isset($_COOKIE['viewDeletedPosts'])) {
    setcookie('viewDeletedPosts', '1', time() + (86400 * 30), "/");
}