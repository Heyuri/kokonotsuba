<?php

namespace Kokonotsuba\Modules\blank;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\userRole;

class moduleMain extends abstractModuleMain {
    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.BLANK_AUTH_LEVEL', userRole::LEV_MODERATOR); 
    }

    public function getName(): string {
        return 'Blank Module';
    }

    public function getVersion(): string  {
        return 'VER. 9001';
    }

    public function initialize(): void {
    }
}