<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModule;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\requirePostWithCsrf;

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

	/**
	 * Enforce POST method + CSRF token, then dispatch to handleModuleRequest().
	 * Modules that perform state-changing actions should override 
	 * handleModuleRequest() instead of ModulePage() to get automatic protection.
	 */
	public function dispatchModuleRequest(): void {
		requirePostWithCsrf($this->moduleContext->request);
		$this->handleModuleRequest();
	}

	/**
	 * Override this in modules that need POST+CSRF protection.
	 * Called by dispatchModuleRequest() after validation has passed.
	 */
	protected function handleModuleRequest(): void {}

	public function getModulePageURL(array $params = [], bool $forHtml = true, bool $useRequestUri = false): string {
		$params['moduleMode'] = 'admin';
		return parent::getModulePageURL($params, $forHtml, $useRequestUri);
	}

}