<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Closure;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\module_classes\traits\IndicatorTrait;

use function Kokonotsuba\libraries\_T;

class deletedPostUIHooks {
	use IndicatorTrait;
	public function __construct(
		private Closure $includeScript,
		private Closure $buildWidgetEntry,
		private deletedPostUtility $deletedPostUtility,
		private string $modulePageUrl
	) {}

	public function runHooks(moduleEngine $moduleEngine, userRole $requiredRole): void {
		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'LinksAboveBar',
			function(string &$linkHtml) {
				$linkHtml .= '<li class="adminNavLink"><a title="' . htmlspecialchars(_T('admin_nav_deleted_posts_title')) . '" href="' . htmlspecialchars($this->modulePageUrl) . '">' . htmlspecialchars(_T('admin_nav_deleted_posts')) . '</a></li>';
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'PostAdminControls',
			function(string &$modControlSection, Post &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post, true);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'ManagePostsControls',
			function(string &$modControlSection, Post &$post) {
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
			function(&$postCssClasses, Post $post) {
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
			function(array &$widgetArray, Post &$post) {
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

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'ModerateAttachmentIndicator',
			function(string &$fileInfoBar, array &$attachment) {
				$this->onRenderAttachmentIndicator($fileInfoBar, $attachment);
			}
		);
	}

	/**
	 * Run common deleted-post checks and execute a callback if needed.
	 *
	 * @param Post $post
	 * @param callable $callback Receives the post by reference
	 */
	private function handleDeletedPost(Post &$post, callable $callback): void {
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

	private function onRenderPostAdminControls(string &$modFunc, Post &$post, bool $noScript): void {
		// Skip if viewing from the module page
		if ($this->deletedPostUtility->isModulePage()) {
			return;
		}

		$isDeleted = $this->deletedPostUtility->isPostDeleted($post);
		$onlyFileDeleted = $post->isFileOnlyDeleted() ?? 0;

		// Always render the [DELETED] indicator container (hidden by default, like toggle indicators)
		$showIndicator = $isDeleted && !$onlyFileDeleted;
		$modFunc .= $this->renderIndicator('deleted', '[DELETED]', 'warning', !$showIndicator, 'This post was deleted!');

		// Render the View button only for actually deleted posts
		if ($isDeleted) {
			$modFunc .= $this->deletedPostUtility->adminPostViewModuleButton($post, $noScript);
		}
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

	private function onRenderAttachmentCssClass(string &$attachmentCssClasses, Post &$post, bool $isLiveFrontend): void {
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
		if (empty($post->getDeletedAttachments())) {
			return;
		}

		// Loop through the deleted_attachments to check 'open_flag' value
		foreach ($post->getAttachments() as $attachment) {
			if ((!isset($attachment['deletedPostId']) && !$attachment['deletedPostId']) || !$attachment['isDeleted']) {
				// Handle the case where 'open_flag' is not 1
				return;
			}
		}

		$this->appendCssClassIf($attachmentCssClasses, true, 'deletedFile');
	}

	private function onRenderPostCssClass(string &$postCssClasses, Post $post): void {
		// is this being viewed from the module page?
		$isModulePage = $this->deletedPostUtility->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether only the file was deleted
		$onlyFileDeleted = $post->isFileOnlyDeleted();

		// whether the post is deleted or not
		$isPostDeleted = $this->deletedPostUtility->isPostDeleted($post) && !$onlyFileDeleted;

		$this->appendCssClassIf($postCssClasses, $isPostDeleted, 'deletedPost');
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the script header
		($this->includeScript)('deletedPosts.js', $moduleHeader);
	}

	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		// whether to render it
		if(!$this->deletedPostUtility->canRenderButton($post)) {
			return;
		}

		// generate view deleted url
		$deletedEntryUrl = $this->deletedPostUtility->generateViewDeletedPostUrl($post->getDeletedPostId());

		// build the widget entry
		$viewDeletedPostWidget = ($this->buildWidgetEntry)(
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
		if(!$attachment['isDeleted'] && !$attachment['onlyFileDeleted']) {
			return;
		}

		// get deleted post id of the attachment
		$deletedPostId = $attachment['deletedPostId'] ?? null;

		// append view deleted attachment button
		$attachmentProperties .= $this->generateViewDelAttachmentButton($deletedPostId);
	}

	private function onRenderAttachmentIndicator(string &$fileInfoBar, array &$attachment): void {
		// whether this specific attachment is deleted (per-attachment flag AND post-level file-only flag)
		$isDeleted = $attachment['isDeleted'] && $attachment['onlyFileDeleted'];

		// always render [FILE DELETED] indicator (hidden when not deleted, JS toggles visibility)
		$fileInfoBar .= $this->renderIndicator('fileDeleted', '[FILE DELETED]', 'warning', !$isDeleted, 'This post\'s file was deleted!');
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