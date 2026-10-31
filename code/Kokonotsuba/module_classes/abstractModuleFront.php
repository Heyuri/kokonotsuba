<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\userRole;
use function Puchiko\strings\sanitizeStr;

/**
 * A module's part on the front page (module/{name}/moduleFront.php), loaded when the module is
 * on in the global ModuleList. Front page hooks:
 *
 * - FrontHeader(string &$headHtml)        tags in <head>
 * - FrontTopNav(string &$html)            above the board list
 * - FrontSideNav(string &$html)           the side nav slot
 * - FrontInfo(string &$html)              under the title (global message, blotter)
 * - FrontCacheDependencies(array &$files) files whose mtime makes cached visitor pages stale
 * - FrontStaffNavLinks(array &$entries)   staff nav entries, same shape as StaffNavLinks
 *
 * Visitors' pages are cached, so anything for staff only must be added through listenStaff().
 */
abstract class abstractModuleFront {
	public function __construct(
		protected readonly frontModuleContext $context,
		protected readonly string $moduleName,
	) {
		$this->initialize();
	}

	abstract public function initialize(): void;

	/**
	 * This module's page at ?module={name}, for visitors and staff alike.
	 *
	 * @return array{title: string, html: string}|null Null when the module has none (a 404).
	 */
	public function renderModulePage(): ?array {
		return null;
	}

	protected function listen(string $hook, callable $listener, int $priority = 0): void {
		$this->context->moduleEngine->addListener($hook, $listener, $priority);
	}

	/** A listener that only runs for a signed-in viewer of at least $role. */
	protected function listenStaff(string $hook, callable $listener, userRole $role = userRole::LEV_USER, int $priority = 0): void {
		$this->context->moduleEngine->addRoleProtectedListener($hook, $listener, $role, $priority);
	}

	/** A site config value by dot path. */
	protected function getConfig(string $key, mixed $default = null): mixed {
		$value = $this->context->siteConfig;

		foreach (explode('.', $key) as $segment) {
			if (!is_array($value) || !array_key_exists($segment, $value)) {
				return $default;
			}
			$value = $value[$segment];
		}

		return $value;
	}

	protected function getModuleConfig(string $key, mixed $default = null): mixed {
		return $this->getConfig("modules.{$this->moduleName}.{$key}", $default);
	}

	protected function includeStylesheet(string $cssFileName): void {
		moduleStylesheets::add($this->getConfig('STATIC_URL', '') . 'css/module/' . $cssFileName);
	}

	protected function scriptTag(string $jsFileName): string {
		return '<script src="' . sanitizeStr($this->getConfig('STATIC_URL', '') . 'js/module/' . $jsFileName) . '" defer></script>';
	}

	/** The first listed board, for links into board-only modes (overboard, admin pages). */
	protected function firstBoardUrl(): string {
		return $this->context->container->get('boardService')->getFirstListedBoardUrl();
	}
}
