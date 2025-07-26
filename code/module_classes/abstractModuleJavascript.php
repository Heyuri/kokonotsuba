<?php

namespace Kokonotsuba\ModuleClasses;

abstract class abstractModuleJavascript extends abstractModule {
    // Provide a default (empty) implementation
    abstract public function javascriptHookPoint(string &$urlList): void;

    // Provide a default (empty) implementation
    abstract public function cssHookPoint(string &$urlList): void;
}

