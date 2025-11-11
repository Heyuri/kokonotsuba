<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\Root\Constants\userRole;
use moduleEngine;

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
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);

		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'Post',
			function(&$arrLabels, $post, $threadPosts, $board) {
				$this->onRenderPost($arrLabels, $post);
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
			'ThreadCssClass',
			function(&$threadCssClasses, $thread) {
				$this->onRenderThreadCssClass($threadCssClasses, $thread);
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
	}

	private function onRenderLinksAboveBar(string &$linkHtml): void {
		// modify the "links above bar" html to have a [Deleted Posts] button
		$linkHtml .= '<li class="adminNavLink"><a title="Manage posts that have been deleted" href="' . htmlspecialchars($this->modulePageUrl) . '">Manage deleted posts</a></li>';
	}

	private function onRenderPostAdminControls(string &$modFunc, array &$post): void {
		// whether the post is deleted or not
		$isPostDeleted = $this->deletedPostUtility->isPostDeleted($post);

		// don't bother if the post isn't deleted
		if(!$isPostDeleted) {
			return;
		}

		// is this being viewed from the module page?
		$isModulePage = $this->deletedPostUtility->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether only the file was deleted
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		// render the <a> button to take the user to the entry in the module
		$modFunc .= $this->deletedPostUtility->adminPostViewModuleButton($post);

		// render the [DELETED] indicator
		$modFunc .= $this->renderDeletionIndicator($onlyFileDeleted);
	}

	private function renderDeletionIndicator(bool $onlyFileDeleted): string {
		// generate message for file-only
		if($onlyFileDeleted) {
			// message for within the square brackets
			$message = "FILE DELETED";

			// the title
			$spanTitle = "This post's file was deleted";
		} 
		// default - post was simply deleted
		else {
			$message = "DELETED";

			$spanTitle = "This post was deleted";
		}

		// return html
		return '<span class="warning" title="' . htmlspecialchars($spanTitle) . '">[' . htmlspecialchars($message) . ']</span>';
	}

	private function onRenderPost(array &$templateValues, array $post): void {
		// whether the post is deleted or not
		$isPostDeleted = $this->deletedPostUtility->isPostDeleted($post);

		// don't bother if the post isn't deleted
		if(!$isPostDeleted) {
			return;
		}

		// OK - this post is deleted. Proceed

		// Append the staff note to the comment
		if(isset($post['deleted_note'])) {
			$templateValues['{$COM}'] .= $this->renderStaffNoteOnPost($post);
		}
	}

	private function renderStaffNoteOnPost(array $post): string {
		// get the note
		$note = $post['deleted_note'] ?? '';

		// sanitize the note
		$sanitizedNote = sanitizeStr($note);

		// convert new lines to break lines
		$sanitizedNote = nl2br($sanitizedNote);

		// generate the string
		$noteHtml = '<br><br><small class="noteOnPost warning" title="This is a note left by staff"> ' . $sanitizedNote . ' </small>';

		// return the generate message
		return $noteHtml;
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

		// whether the post is deleted or not
		$isPostDeleted = $this->deletedPostUtility->isPostDeleted($post);

		// whether only the file was deleted
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		$this->appendCssClassIf($attachmentCssClasses, $isPostDeleted && $onlyFileDeleted, 'deletedFile');
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
	
	private function onRenderThreadCssClass(string &$threadCssClasses, array $thread): void {
		// is this being viewed from the module page?
		$isModulePage = $this->deletedPostUtility->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether the thread is deleted or not
		$isThreadDeleted = !empty($thread['thread_deleted']) && empty($thread['thread_attachment_deleted']);

		$this->appendCssClassIf($threadCssClasses, $isThreadDeleted, 'deletedPost');
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the script header
		$jsHtml = $this->moduleAdmin->generateScriptHeader('deletedPosts.js', true);

		// then append it to the header
		$moduleHeader .= $jsHtml;
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
			'Moderate'
		);

		// add the widget to the array
		$widgetArray[] = $viewDeletedPostWidget;
	}
}