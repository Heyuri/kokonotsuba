<?php
class hookClass {
    private $hooks = [];

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