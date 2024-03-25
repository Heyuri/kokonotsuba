<?php
class HookClass {
    // this is a singleton.
    // these functions should be disabled. and getInstance should be used insted.
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new Exception("Unserialization of AuthClass instances is not allowed.");}
    static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new HookClass();
        }
        return self::$instance;
    }

    
    private $hooks = [];

    public function addHook(string $hookName, callable $callback) {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }
        $this->hooks[$hookName][] = $callback;
    }

    public function executeHook(string $hookName, ...$params) {
        if (isset($this->hooks[$hookName])) {
            foreach ($this->hooks[$hookName] as $callback) {
                call_user_func_array($callback, $params);
            }
        }
    }
}