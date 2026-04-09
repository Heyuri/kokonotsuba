<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\getPageOfThread;
use function Kokonotsuba\libraries\validatePostInput;

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

	/**
	 * Register a simple post widget that generates a URL from the post and appends a single widget entry.
	 *
	 * @param callable(Post): string|string $urlGenerator A callable returning the widget URL, or a string
	 *        URL parameter name (e.g. 'postUid') to auto-generate via getModulePageURL.
	 * @param string $action   Internal action identifier used by JavaScript.
	 * @param string $label    Display text shown in the widget menu.
	 */
	protected function registerSimplePostWidget(callable|string $urlGenerator, string $action, string $label): void {
		if (is_string($urlGenerator)) {
			$paramName = $urlGenerator;
			$urlGenerator = fn(Post $post) => $this->getModulePageURL([$paramName => $post->getUid()], false, true);
		}

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, Post &$post) use ($urlGenerator, $action, $label) {
				$widgetArray[] = $this->buildWidgetEntry($urlGenerator($post), $action, $label, '');
			}
		);
	}

	protected function registerLinksAboveBarHook(string $title, string $href, string $textContent): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) use ($title, $href, $textContent) {
				$linkHtml .= '<li class="adminNavLink"><a title="' . htmlspecialchars($title) . '" href="' . htmlspecialchars($href) . '">' . htmlspecialchars($textContent) . '</a></li>';
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

	protected function renderAttachmentIndicator(string $indicatorClass, string $buttonHtml, bool $isHidden): string {
		$hiddenClass = $isHidden ? ' indicatorHidden' : '';
		return '<span class="indicator indicator-' . $indicatorClass . $hiddenClass . '">' . $buttonHtml . '</span>';
	}

	protected function renderAttachmentButton(string $url, string $functionClass, string $title, string $label): string {
		return ' <span class="adminFunctions admin' . $functionClass . 'Function attachmentButton">[<a href="' . htmlspecialchars($url) . '" title="' . htmlspecialchars($title) . '">' . $label . '</a>]</span>';
	}

	protected function renderAttachmentForm(string $url, string $functionClass, string $title, string $label): string {
		return \Kokonotsuba\libraries\generateAttachmentForm($url, $functionClass, $title, $label);
	}

	protected function canDeleteAttachment(array $attachment): bool {
		return !empty($attachment) && attachmentFileExists($attachment) && !$attachment['isDeleted'];
	}

	/**
	 * Validate a post UID and fetch the corresponding Post.
	 * Throws BoardException if the UID is invalid or the post doesn't exist.
	 */
	protected function fetchValidatedPost(mixed $postUid, bool $withAttachments = true): Post {
		validatePostInput($postUid);
		$post = $this->moduleContext->postRepository->getPostByUid($postUid, $withAttachments);
		validatePostInput($post, false);
		return $post;
	}

	/**
	 * Rebuild the board intelligently: full rebuild for OP posts,
	 * single-page rebuild for replies.
	 */
	protected function rebuildBoardForPost($board, Post $post): void {
		if ($post->isOp()) {
			$board->rebuildBoard();
		} else {
			$thread_uid = $post->getThreadUid();
			$threads = $this->moduleContext->threadService->getThreadListFromBoard($board);
			$pageToRebuild = getPageOfThread($thread_uid, $threads, $board->getConfigValue('PAGE_DEF', 15));
			$pageToRebuild = min($pageToRebuild, $this->getConfig('STATIC_HTML_UNTIL'));
			$board->rebuildBoardPage($pageToRebuild);
		}
	}

	/**
	 * Generate a URL linking to the deleted posts viewer for a given file ID.
	 */
	protected function getDeletedLinkForFile(int $fileId): string {
		$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowByFileId($fileId);
		$deletedPostId = $deletedPost['deleted_post_id'];
		$baseUrl = $this->moduleContext->request->getCurrentUrlNoQuery();

		$urlParameters = [
			'pageName' => 'viewMore',
			'deletedPostId' => $deletedPostId,
			'moduleMode' => 'admin',
			'mode' => 'module',
			'load' => 'deletedPosts'
		];

		return $baseUrl . '?' . http_build_query($urlParameters);
	}
}
