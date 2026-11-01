<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\userRole;

/**
 * The front page's module engine: loads module/{name}/moduleFront.php for every module on in the
 * global ModuleList and dispatches the front page hooks (see abstractModuleFront).
 */
final class frontModuleEngine {
	private readonly hookDispatcher $hookDispatcher;

	/** @var array<string, abstractModuleFront> */
	private array $modules = [];

	public function __construct(
		appContainer $container,
		templateEngine $templateEngine,
		frontPageLinks $links,
		array $siteConfig,
		private readonly staffAccountFromSession $staffAccountFromSession,
	) {
		$this->hookDispatcher = new hookDispatcher();
		$context = new frontModuleContext($container, $templateEngine, $links, $siteConfig, $staffAccountFromSession, $this);
		$modulePath = getBackendDir() . 'module/';

		foreach ((array)($siteConfig['ModuleList'] ?? []) as $moduleName => $isOn) {
			$frontFile = $modulePath . $moduleName . '/moduleFront.php';
			if (!$isOn || !is_file($frontFile)) {
				continue;
			}

			include_once $frontFile;
			$class = "\\Kokonotsuba\\Modules\\{$moduleName}\\moduleFront";
			if (!class_exists($class)) {
				continue;
			}

			if (is_dir($modulePath . $moduleName . '/templates')) {
				$templateEngine->addSearchPath($modulePath . $moduleName . '/templates');
			}
			$this->modules[$moduleName] = new $class($context, $moduleName);
		}
	}

	public function addListener(string $hook, callable $listener, int $priority = 0): void {
		$this->hookDispatcher->addListener($hook, $listener, $priority);
	}

	public function addRoleProtectedListener(string $hook, callable $listener, userRole $role, int $priority = 0): void {
		$this->hookDispatcher->addRoleProtectedListener($hook, $listener, $role, $this->staffAccountFromSession->getRoleLevel(), $priority);
	}

	public function dispatch(string $hook, array $parameters = []): void {
		$this->hookDispatcher->dispatch($hook, $parameters);
	}

	/** @return array{title: string, html: string}|null Null for an unknown module or one without a page. */
	public function renderModulePage(string $moduleName): ?array {
		return isset($this->modules[$moduleName]) ? $this->modules[$moduleName]->renderModulePage() : null;
	}
}
