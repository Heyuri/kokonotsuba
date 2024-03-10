<?php
class HookClass {
    private static $instance = null;
    private $hooks = [];

    // this is a singleton.
    // these functions should be disabled. and getInstance should be used insted.
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new HookClass();
        }
        return self::$instance;
    }

    public function addHook($hookName, $callback) {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }
        $this->hooks[$hookName][] = $callback;
    }

    public function executeHook($hookName, ...$params) {
        if (isset($this->hooks[$hookName])) {
            foreach ($this->hooks[$hookName] as $callback) {
                call_user_func_array($callback, $params);
            }
        }
    }
}