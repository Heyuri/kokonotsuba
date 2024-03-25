<?php
enum roles{
        case Admin;
        case Mod;
        case janitor;
        case noAuth; // user was never logged in.
}

class AuthClass {
    // this is a singleton.
    // these functions should be disabled. and getInstance should be used insted.
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new Exception("Unserialization of AuthClass instances is not allowed.");}
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new AuthClass();
        }
        return self::$instance;
    }
    
    private roles $role = roles::noAuth;

    public function isAdmin(){
        return $this->role == roles::Admin;
    }
    public function isMod(){
        return $this->role == roles::Mod;
    }
    public function isJanitor(){
        return $this->role == roles::janitor;
    }
    //person dose not have special status.
    public function isNotAuth(){
        return $this->role == roles::noAuth;
    }
}