<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\post\Post;

/**
 * Trait for post-level admin modules (ban, delete, anti-spam, etc.).
 *
 * Provides convenience methods for registering common role-protected
 * hook listeners used by post moderation modules.
 *
 * Requires the using class to extend abstractModuleAdmin.
 */
trait PostControlHooksTrait {
	/**
	 * Register both ManagePostsControls and PostAdminControls hooks
	 * with the same method, passing false/true for the noScript parameter.
	 *
	 * The target method signature must be: (string &$modfunc, Post &$post, bool $noScript): void
	 */
	protected function registerPostControlPair(string $methodName): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsControls',
			function(string &$modControlSection, Post &$post) use ($methodName) {
				$this->$methodName($modControlSection, $post, false);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, Post &$post) use ($methodName) {
				$this->$methodName($modControlSection, $post, true);
			}
		);
	}

	protected function registerPostWidgetHook(string $methodName): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, Post &$post) use ($methodName) {
				$this->$methodName($widgetArray, $post);
			}
		);
	}

	protected function registerLinksAboveBarHook(string $methodName): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) use ($methodName) {
				$this->$methodName($linkHtml);
			}
		);
	}

	protected function registerAdminHeaderHook(string $methodName): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) use ($methodName) {
				$this->$methodName($moduleHeader);
			}
		);
	}

	protected function registerThreadControlPair(string $methodName): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsThreadControls',
			function(string &$modControlSection, Post &$post) use ($methodName) {
				$this->$methodName($modControlSection, $post, false);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ThreadAdminControls',
			function(string &$modControlSection, Post &$post) use ($methodName) {
				$this->$methodName($modControlSection, $post, true);
			}
		);
	}

	protected function registerThreadWidgetHook(string $methodName): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateThreadWidget',
			function(array &$widgetArray, Post &$post) use ($methodName) {
				$this->$methodName($widgetArray, $post);
			}
		);
	}

	protected function registerAttachmentHook(string $methodName): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateAttachment',
			function(
				string &$attachmentProperties,
				string &$attachmentImage,
				string &$attachmentUrl,
				array &$attachment
			) use ($methodName) {
				$this->$methodName($attachmentProperties, $attachment);
			}
		);
	}

	protected function listenProtected(string $hookName, callable $callback): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			$hookName,
			$callback
		);
	}
}
