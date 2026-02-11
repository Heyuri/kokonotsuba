<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModule;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getRoleLevelFromSession;

abstract class abstractModuleAdmin extends abstractModule {
	abstract public function getRequiredRole(): userRole;
	
	public function authenticateRequest(): void {
		$roleLevelFromRequest = getRoleLevelFromSession();
		$requiredRoleLevel = $this->getRequiredRole();

		if($roleLevelFromRequest->isLessThan($requiredRoleLevel)) {
			throw new BoardException("You are not authorized to access this page!");
		}
		// Throw error if the user viewing the pagge isn't the minumum role
		
	}

	public function getModulePageURL(array $params = [], bool $forHtml = true, bool $useRequestUri = false): string {
		$params['moduleMode'] = 'admin';
		return parent::getModulePageURL($params, $forHtml, $useRequestUri);
	}

}