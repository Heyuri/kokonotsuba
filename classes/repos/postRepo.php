<?php

class postRepoClass {
    // this is a singleton.
    // these functions should be disabled. and getInstance should be used insted.
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new postRepoClass();
        }
        return self::$instance;
    }
    public function loadPosts($conf ,$threadID){

    }
    public function savePost($conf ,$threadID, $post){
    
    }
    public function loadPostByID($conf ,$threadID, $postID){
        
    }
}