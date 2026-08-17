<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Closure;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\module_classes\traits\IndicatorTrait;
use Kokonotsuba\renderers\widgetMenuPolicy;

use function Kokonotsuba\libraries\_T;

class deletedPostUIHooks {
	use IndicatorTrait;

	// submenu the deletion entries are grouped under, in both the post and attachment menus
	private const DELETION_SUBMENU = 'Deletion';

	public function __construct(
		private Closure $includeScript,
		private Closure $buildWidgetEntry,
		private deletedPostUtility $deletedPostUtility,
		private string $modulePageUrl,
		private userRole $purgeRole,
		private widgetMenuPolicy $menuPolicy
	) {}

	public function runHooks(moduleEngine $moduleEngine, userRole $requiredRole): void {
		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'LinksAboveBar',
			function(string &$linkHtml) {
				$linkHtml .= '<li class="adminNavLink"><a title="' . htmlspecialchars(_T('admin_nav_deleted_posts_title')) . '" href="' . htmlspecialchars($this->modulePageUrl) . '">' . htmlspecialchars(_T('admin_nav_deleted_posts')) . '</a></li>';
			}
		);

		// The same destination in the sticky staff nav (module/staffNav), which builds from data
		// rather than the markup above.
		$moduleEngine->addRoleProtectedListener(
			$requiredRole,
			'StaffNavLinks',
			function(array &$entries) {
				$entries[] = [
					'key' => 'deletedPosts',
					'label' => _T('admin_nav_deleted_posts'),
					'url' => $this->modulePageUrl,
					'title' => _T('admin_nav_deleted_posts_title'),
					'group' => '',
				];
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
			'ModerateAttachmentWidget',
			function(array &$widgetArray, array &$attachment) {
				$this->onRenderAttachmentWidget($widgetArray, $attachment);
			}
		);

		// purging sits behind the higher role, not the one for merely seeing deleted posts
		$moduleEngine->addRoleProtectedListener(
			$this->purgeRole,
			'ModerateAttachmentWidget',
			function(array &$widgetArray, array &$attachment) {
				$this->onRenderAttachmentPurgeWidget($widgetArray, $attachment);
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

	/**
	 * Serialise an array of widget entry arrays (as returned by buildWidgetEntry) to
	 * anchor HTML, matching the format used by postWidget.php's buildWidgetMenuHtml.
	 *
	 * Entries disabled for this board are dropped here: the renderers filter the menus they build,
	 * but nothing filters a <template> the front-end clones back in.
	 */
	private function widgetEntriesToHtml(array $entries): string {
		$entries = $this->menuPolicy->filter(widgetMenuPolicy::MENU_POST, $entries);

		$html = '';
		foreach ($entries as $w) {
			$href    = htmlspecialchars($w['href'] ?? '');
			$action  = htmlspecialchars($w['action'] ?? '');
			$label   = htmlspecialchars($w['label'] ?? '');
			$subMenu = htmlspecialchars($w['subMenu'] ?? '');
			$params  = $w['params'] ?? [];
			$paramAttrs = '';
			foreach ($params as $k => $v) {
				$paramAttrs .= ' data-param-' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
			}
			$html .= '<a href="' . $href . '" data-action="' . $action . '" data-label="' . $label . '" data-subMenu="' . $subMenu . '"' . $paramAttrs . '></a>';
		}
		return $html;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		($this->includeScript)('deletedPosts.js', $moduleHeader);

		// Template for dynamic Deletion submenu injection after a post is deleted live.
		// JS replaces the '__DPID__' placeholder with the actual deleted-post ID.
		$templateEntries = [
			($this->buildWidgetEntry)('#', 'viewDeletedPost', 'View entry', self::DELETION_SUBMENU),
			($this->buildWidgetEntry)($this->modulePageUrl, 'restoreDeletedPost', 'Restore post', self::DELETION_SUBMENU, ['deletedPostId' => '__DPID__', 'action' => 'restore']),
			($this->buildWidgetEntry)($this->modulePageUrl, 'purgeDeletedPost',   'Purge post',   self::DELETION_SUBMENU, ['deletedPostId' => '__DPID__', 'action' => 'purge']),
		];
		$moduleHeader .= '<template id="dp-widget-tmpl">' . $this->widgetEntriesToHtml($templateEntries) . '</template>';
	}

	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		// whether to render it
		if(!$this->deletedPostUtility->canRenderButton($post)) {
			return;
		}

		$deletedPostId = $post->getDeletedPostId();

		// View entry (GET navigation) under the "Deletion" submenu
		$deletedEntryUrl = $this->deletedPostUtility->generateViewDeletedPostUrl($deletedPostId);
		$widgetArray[] = ($this->buildWidgetEntry)(
			$deletedEntryUrl,
			'viewDeletedPost',
			'View entry',
			self::DELETION_SUBMENU
		);

		// Restore action (POST with CSRF) under the "Deletion" submenu
		$widgetArray[] = ($this->buildWidgetEntry)(
			$this->modulePageUrl,
			'restoreDeletedPost',
			'Restore post',
			self::DELETION_SUBMENU,
			['deletedPostId' => $deletedPostId, 'action' => 'restore']
		);

		// Purge action (POST with CSRF) under the "Deletion" submenu
		$widgetArray[] = ($this->buildWidgetEntry)(
			$this->modulePageUrl,
			'purgeDeletedPost',
			'Purge post',
			self::DELETION_SUBMENU,
			['deletedPostId' => $deletedPostId, 'action' => 'purge']
		);
	}

	/**
	 * View and restore entries for a file deleted on its own, under the same "Deletion" submenu
	 * the post widget uses.
	 */
	private function onRenderAttachmentWidget(array &$widgetArray, array &$attachment): void {
		// per file: only the one with a deletion record of its own has an entry to show or restore
		// from ('onlyFileDeleted' is a post-level flag, so it can't tell files apart)
		$deletedPostId = $attachment['deletedPostId'] ?? null;

		if (empty($attachment['isDeleted']) || !$deletedPostId) {
			return;
		}

		$deletedEntryUrl = $this->deletedPostUtility->generateViewDeletedPostUrl($deletedPostId);

		$widgetArray[] = ($this->buildWidgetEntry)(
			$deletedEntryUrl,
			'viewDeletedAttachment',
			'View entry',
			self::DELETION_SUBMENU
		);

		// fileId and postUid are unused by the restore itself - they're what the JS needs to put the
		// file's delete entry back once it is no longer deleted
		$widgetArray[] = ($this->buildWidgetEntry)(
			$this->modulePageUrl,
			'restoreDeletedFile',
			'Restore file',
			self::DELETION_SUBMENU,
			[
				'deletedPostId' => $deletedPostId,
				'action' => 'restoreAttachment',
				'fileId' => $attachment['fileId'],
				'postUid' => $attachment['postUid'],
			]
		);
	}

	/**
	 * Offer to destroy a single deleted file. Addressed by file ID, since a file deleted with its
	 * whole post has no deletion record of its own to name.
	 */
	private function onRenderAttachmentPurgeWidget(array &$widgetArray, array &$attachment): void {
		// only files already in purgatory can be purged
		if(empty($attachment['isDeleted'])) {
			return;
		}

		$fileId = $attachment['fileId'] ?? null;

		if(!$fileId) {
			return;
		}

		$widgetArray[] = ($this->buildWidgetEntry)(
			$this->modulePageUrl,
			'purgeDeletedFile',
			'Purge file',
			self::DELETION_SUBMENU,
			['fileId' => $fileId, 'action' => 'purgeFile']
		);
	}

	private function onRenderAttachmentIndicator(string &$fileInfoBar, array &$attachment): void {
		// whether this specific attachment is deleted (per-attachment flag AND post-level file-only flag)
		$isDeleted = $attachment['isDeleted'] && $attachment['onlyFileDeleted'];

		// always render [FILE DELETED] indicator (hidden when not deleted, JS toggles visibility)
		$fileInfoBar .= $this->renderIndicator('fileDeleted', '[FILE DELETED]', 'warning', !$isDeleted, 'This post\'s file was deleted!');
	}



}