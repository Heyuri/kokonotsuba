<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\board\board;
use Kokonotsuba\userRole;
use Kokonotsuba\module_classes\moduleEngine;

use function Puchiko\strings\sanitizeStr;

class deletedPostUIHooks {
	public function __construct(
		private moduleAdmin $moduleAdmin,
		private deletedPostUtility $deletedPostUtility,
		private string $modulePageUrl
	) {}

	public function runHooks(moduleEngine $moduleEngine, userRole $requiredRole): void {
		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post, true);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'ManagePostsControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post, false);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'AttachmentCssClass',
			function(&$postCssClasses, &$post, $isLiveFrontend) {
				$this->onRenderAttachmentCssClass($postCssClasses, $post, $isLiveFrontend);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'PostCssClass',
			function(&$postCssClasses, $post) {
				$this->onRenderPostCssClass($postCssClasses, $post);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'ModuleHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'ModeratePostWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderPostWidget($widgetArray, $post);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'ModerateAttachment',
			function(
				string &$attachmentProperties, 
				string &$attachmentImage, 
				string &$attachmentUrl, 
				array &$attachment
			) {
				$this->onRenderAttachment($attachmentProperties, $attachment);
			}
		);
	}

	private function onRenderLinksAboveBar(string &$linkHtml): void {
		// modify the "links above bar" html to have a [Deleted Posts] button
		$linkHtml .= '<li class="adminNavLink"><a title="Manage posts that have been deleted" href="' . htmlspecialchars($this->modulePageUrl) . '">Manage deleted posts</a></li>';
	}

	/**
	 * Run common deleted-post checks and execute a callback if needed.
	 *
	 * @param array $post
	 * @param callable $callback Receives the post array by reference
	 */
	private function handleDeletedPost(array &$post, callable $callback): void {
		// Skip if the post isn't deleted
		if (!$this->deletedPostUtility->isPostDeleted($post)) {
			return;
		}

		// Skip if viewing from the module page
		if ($this->deletedPostUtility->isModulePage()) {
			return;
		}

		// Call the custom callback for this post
		$callback($post);
	}

	private function onRenderPostAdminControls(string &$modFunc, array &$post, bool $noScript): void {
		$this->handleDeletedPost($post, function(array &$post) use (&$modFunc, $noScript) {
			// render indicator
			$modFunc .= $this->renderDeletedIndicator($post);

			// render the <a> button to take the user to the entry in the module
			$modFunc .= $this->deletedPostUtility->adminPostViewModuleButton($post, $noScript);
		});
	}

	private function renderDeletedIndicator(?array $post): string {
		// whether only the file was deleted
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		// render the [DELETED] indicator if the file wasn't deleted
		if (!$onlyFileDeleted) {
			return $this->renderIndicator('DELETED', "This post was deleted!");
		}

		// otherwise dont render any indicaztor
		return '';
	}

	private function renderIndicator(string $message, string $spanTitle): string {
		// return html
		return '<span class="warning" title="' . htmlspecialchars($spanTitle) . '">[' . htmlspecialchars($message) . ']</span>';
	}

	private function appendCssClassIf(string &$cssClasses, bool $condition, string $className): void {
		// don't bother if condition is not met
		if(!$condition) {
			return;
		}

		// append hidden css class
		// space separated on each side
		$cssClasses .= " $className ";
	}

	private function onRenderAttachmentCssClass(string &$attachmentCssClasses, array &$post, bool $isLiveFrontend): void {
		// return early if this isn't done from the live frontend
		if(!$isLiveFrontend) {
			return;
		}

		// is this being viewed from the module page?
		$isModulePage = $this->deletedPostUtility->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// Check if the post contains any deleted attachments
		if (empty($post['deleted_attachments'])) {
			return;
		}

		// Loop through the deleted_attachments to check 'open_flag' value
		foreach ($post['attachments'] as $attachment) {
			if ((!isset($attachment['deletedPostId']) && !$attachment['deletedPostId']) || !$attachment['isDeleted']) {
				// Handle the case where 'open_flag' is not 1
				return;
			}
		}

		$this->appendCssClassIf($attachmentCssClasses, true, 'deletedFile');
	}

	private function onRenderPostCssClass(string &$postCssClasses, array $post): void {
		// is this being viewed from the module page?
		$isModulePage = $this->deletedPostUtility->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether only the file was deleted
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		// whether the post is deleted or not
		$isPostDeleted = $this->deletedPostUtility->isPostDeleted($post) && $onlyFileDeleted === 0;

		$this->appendCssClassIf($postCssClasses, $isPostDeleted, 'deletedPost');
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the script header
		$this->moduleAdmin->includeScript('deletedPosts.js', $moduleHeader);
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
		// whether to render it
		if(!$this->deletedPostUtility->canRenderButton($post)) {
			return;
		}

		// generate view deleted url
		$deletedEntryUrl = $this->deletedPostUtility->generateViewDeletedPostUrl($post['deleted_post_id']);

		// build the widget entry
		$viewDeletedPostWidget = $this->moduleAdmin->buildWidgetEntry(
			$deletedEntryUrl, 
			'viewDeletedPost', 
			'View deleted post', 
			''
		);

		// add the widget to the array
		$widgetArray[] = $viewDeletedPostWidget;
	}

	private function onRenderAttachment(string &$attachmentProperties, array &$attachment): void {
		// return early if the attachment isn't deleted
		if((!$attachment['isDeleted'] && !$attachment['onlyFileDeleted']) || empty($attachment['deletedPostId'])) {
			return;
		}

		// append indicator to attachment
		$attachmentProperties .= $this->renderIndicator('FILE DELETED', 'This post\'s file was deleted!');

		// get deleted post id of the attachment
		$deletedPostId = $attachment['deletedPostId'] ?? null;

		// append view deleted attachment button
		$attachmentProperties .= $this->generateViewDelAttachmentButton($deletedPostId);
	}

	private function generateViewDelAttachmentButton(?int $deletedPostId): string {
		// return empty string if null
		if(!$deletedPostId) {
			return '';
		}
		
		// generate delete attachment url
		$deletedEntryUrl = $this->deletedPostUtility->generateViewDeletedPostUrl($deletedPostId);

		// button html
		$button = ' <span class="adminFunctions adminViewDeletedAttachment attachmentButton">[<a href="' . htmlspecialchars($deletedEntryUrl) . '" title="View deleted attachment">VF</a>]</span>';
	
		// return button
		return $button;
	}
}