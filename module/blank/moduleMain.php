<?php

namespace Kokonotsuba\Modules\blank;

use Kokonotsuba\module_classes\abstractModuleMain;

class moduleMain extends abstractModuleMain {
    public function getName(): string {
        return 'Blank Module';
    }

    public function getVersion(): string  {
        return 'VER. 9001';
    }

    public function initialize(): void {
    }
}