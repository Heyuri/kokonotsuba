<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\generateModerateForm;
use function Kokonotsuba\libraries\getCsrfMetaTag;

/**
 * Trait for thread-level toggle modules (lock, sticky, autosage).
 *
 * Provides shared hook registration, button rendering, widget rendering,
 * and module header handling for modules that toggle a flag on a thread's OP.
 *
 * Requires the using class to extend abstractModuleAdmin.
 */
trait ToggleActionTrait {
	abstract protected function getToggleFlagKey(): string;
	abstract protected function getToggleActiveLabel(): string;
	abstract protected function getToggleInactiveLabel(): string;
	abstract protected function getToggleActiveTitle(): string;
	abstract protected function getToggleInactiveTitle(): string;
	abstract protected function getToggleCssClass(): string;
	abstract protected function getToggleActionName(): string;
	abstract protected function getToggleJsFile(): string;
	abstract protected function getToggleUrlParams(Post $post): array;

	protected function shouldRegisterThreadAdminControls(): bool {
		return true;
	}

	protected function registerToggleHooks(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsThreadControls',
			function(string &$modControlSection, Post &$post) {
				$this->renderToggleButton($modControlSection, $post, false);
			}
		);

		if ($this->shouldRegisterThreadAdminControls()) {
			$this->moduleContext->moduleEngine->addRoleProtectedListener(
				$this->getRequiredRole(),
				'ThreadAdminControls',
				function(string &$modControlSection, Post &$post) {
					$this->renderToggleButton($modControlSection, $post, true);
				}
			);
		}

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateThreadWidget',
			function(array &$widgetArray, Post &$post) {
				$this->onRenderToggleWidget($widgetArray, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onToggleModuleHeader($moduleHeader);
			}
		);
	}

	protected function renderToggleButton(string &$modfunc, Post $post, bool $noScript): void {
		$isActive = $post->getFlags()->value($this->getToggleFlagKey());
		$url = $this->generateToggleActionUrl($post);

		$modfunc .= generateModerateForm(
			$url,
			$isActive ? $this->getToggleActiveLabel() : $this->getToggleInactiveLabel(),
			$isActive ? $this->getToggleActiveTitle() : $this->getToggleInactiveTitle(),
			$this->getToggleCssClass(),
			$noScript
		);
	}

	protected function onRenderToggleWidget(array &$widgetArray, Post &$post): void {
		$isActive = $post->getFlags()->value($this->getToggleFlagKey());
		$url = $this->getModulePageURL([], false, true);
		$label = $isActive ? $this->getToggleActiveTitle() : $this->getToggleInactiveTitle();

		$widgetArray[] = $this->buildWidgetEntry(
			$url,
			$this->getToggleActionName(),
			$label,
			'',
			['post_uid' => $post->getUid()]
		);
	}

	protected function generateToggleActionUrl(Post $post): string {
		return $this->getModulePageURL(
			$this->getToggleUrlParams($post),
			false,
			true
		);
	}

	protected function onToggleModuleHeader(string &$moduleHeader): void {
		$moduleHeader .= getCsrfMetaTag();
		$this->includeScript($this->getToggleJsFile(), $moduleHeader);
	}
}
