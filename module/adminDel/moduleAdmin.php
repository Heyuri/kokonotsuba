<?php

namespace Kokonotsuba\Modules\adminDel;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanFileOperationsTrait;
use Kokonotsuba\module_classes\traits\listeners\MassModerateListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\renderers\widgetMenuPolicy;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\generateModerateForm;
use function Kokonotsuba\libraries\rebuildBoardsFromPosts;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
	use MassModerateListenerTrait;
	use AuditableTrait;
	use BanFileOperationsTrait;

	private readonly int $JANIMUTE_LENGTH;
	private readonly string $JANIMUTE_REASON;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_DELETE', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Deletion tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->JANIMUTE_LENGTH = $this->getModuleConfig('JANIMUTE_LENGTH');
		$this->JANIMUTE_REASON = $this->getModuleConfig('JANIMUTE_REASON');

		$this->registerPostControlPair('onRenderPostAdminControls');
		$this->registerPostWidgetHook('onRenderPostWidget');
		$this->registerAdminHeaderHook('onGenerateModuleHeader');
		$this->listenMassModerateTools('onMassModerateTools', 100);
		$this->listenProtected('ModerateAttachmentWidget', function(array &$widgetArray, array &$fileData) {
			$this->onRenderAttachmentWidget($widgetArray, $fileData);
		});
	}

	private function onRenderPostAdminControls(string &$modFunc, Post &$post, bool $noScript): void {
		// whether to render the admin controls or not
		if(!$this->canRenderButton($post)) {
			return;
		}
		
		$postUid = $post->getUid();
		$muteMinutes = $this->JANIMUTE_LENGTH;
		$plural = $muteMinutes == 1 ? '' : 's';

		// render delete button
		$modFunc .= generateModerateForm(
			$this->generateDeletionUrl('del', $postUid),
			'D',
			'Delete',
			'adminDeleteFunction',
			$noScript,
			true
		);

		// render delete and mute button
		$modFunc .= generateModerateForm(
			$this->generateDeletionUrl('delmute', $postUid),
			'DM',
			'Delete and mute for ' . $muteMinutes . ' minute' . $plural,
			'adminDeleteMuteFunction',
			$noScript,
			true,
		);
	}
	
	private function onMassModerateTools(array &$tools): void {
		$muteMinutes = $this->JANIMUTE_LENGTH;
		$plural = $muteMinutes == 1 ? '' : 's';

		$tools[] = $this->buildMassTool('del', 'Delete', [
			'group' => 'Deletion',
			'effect' => 'delete',
			'confirm' => _T('mass_moderate_confirm'),
			'priority' => 100,
		]);

		$tools[] = $this->buildMassTool('delmute', 'Delete & mute for ' . $muteMinutes . ' minute' . $plural, [
			'group' => 'Deletion',
			'effect' => 'delete',
			'confirm' => _T('mass_moderate_confirm'),
			'priority' => 90,
		]);
	}

	private function canRenderButton(Post $post): bool {
		// whether the post is deleted or not
		$openFlag = $post->getOpenFlag();

		// don't render anything if the post is already deleted
		if($openFlag && !$post->isFileOnlyDeleted()) {
			return false;
		}

		// all korrect
		// render!
		return true;
	}

	private function onRenderAttachmentWidget(array &$widgetArray, array &$fileData): void {
		if (!$this->canRenderAttachmentButton($fileData)) {
			return;
		}
		$url = $this->generateDeleteAttachUrl($fileData['fileId'], $fileData['postUid']);
		$widgetArray[] = $this->buildWidgetEntry($url, 'deleteFile', 'Delete file', '');
	}

	private function generateDeleteAttachUrl(int|string $fileId, int|string $postUid): string {
		// params
		$params = [
			'post_uid' => $postUid,
			'fileId' => $fileId,
			'action' => 'attachmentDel'
		];

		// then generate url
		$url = $this->getModulePageURL($params, false, true);

		// return
		return $url;
	}

	private function canRenderAttachmentButton(array $attachment): bool {
		// if the post has an attachment and its not already file-only deleted
		if(!empty($attachment)) {
			// this check needs to stay inside this if statement or else it'll read from disk for every post
			if(attachmentFileExists($attachment) && !$attachment['isDeleted']) {
				return true;
			} 
			// otherwise it doesnt exist - so dont render
			else {
				return false;
			}
		}

		// don't render
		return false;
	}

	private function generateDeletionUrl(string $action, int $postUid): string {
		// build parameters for the url
		$params = [
			'action' => $action,
			'post_uid' => $postUid
		];

		// generate the url
		$deletionUrl = $this->getModulePageURL($params, false, true);

		// return the url
		return $deletionUrl;
	}

	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		// whether to render the button
		if(!$this->canRenderButton($post)) {
			return;
		}

		// base module URL — params are carried as data-param-* attributes
		$baseUrl = $this->getModulePageURL([], false, true);
		$postUid = $post->getUid();

		// build the widget entry for deletion
		$deletionWidgets[] = $this->buildWidgetEntry(
			$baseUrl, 
			'delete', 
			'Delete', 
			'',
			['post_uid' => $postUid, 'action' => 'del']
		);

		// build the widget entry for muting
		$deletionWidgets[] = $this->buildWidgetEntry(
			$baseUrl, 
			'mute', 
			'Delete & Mute', 
			'',
			['post_uid' => $postUid, 'action' => 'delmute']
		);

		// add the widget to the array
		$widgetArray = array_merge($deletionWidgets, $widgetArray);
	}
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// can view deleted posts
		$canViewDeleted = $this->moduleContext->postRenderingPolicy->viewDeleted();

		// add requiredForAll js for the live frontend
		// this js will add the deletedPost/deletedFile classes and deletion indicator to posts on the livefrontend
		if($canViewDeleted) {
			// generate the url path of the deleted posts javascript
			$jsFileUrl = $this->generateJavascriptUrl('postDeletion.js');
		} 
		// otherwise, include the old js for post deletion
		// This just hides the post with css and shows small pop-ups indicating success or faliure
		else {
			// generate old deletion js url
			$jsFileUrl = $this->generateJavascriptUrl('basicPostDeletion.js');
		}

		// generate the script html for including the deleted posts js
		// defer
		$jsHtml = $this->generateScriptHtml($jsFileUrl, true);

		// then append it to the header
		$moduleHeader .= $jsHtml;

		// When deletedPosts module is active, emit a <template> containing the delete/mute widget
		// entries so that JS can re-inject them after a post is restored (without hardcoding labels
		// or URLs).  '__POSTUID__' is a placeholder that JS replaces with the real post_uid.
		if ($canViewDeleted) {
			$baseUrl = $this->getModulePageURL([], false, true);
			$moduleHeader .= '<template id="del-restore-tmpl">' . $this->widgetEntriesToHtml(widgetMenuPolicy::MENU_POST, [
				$this->buildWidgetEntry($baseUrl, 'delete', 'Delete',       '', ['post_uid' => '__POSTUID__', 'action' => 'del']),
				$this->buildWidgetEntry($baseUrl, 'mute',   'Delete & Mute', '', ['post_uid' => '__POSTUID__', 'action' => 'delmute']),
			]) . '</template>';

			// the same for a single file, which can be deleted again once it is restored.
			// this entry carries its parameters in the URL, so the placeholders sit there.
			$attachmentUrl = $this->generateDeleteAttachUrl('__FILEID__', '__POSTUID__');
			$moduleHeader .= '<template id="del-file-restore-tmpl">' . $this->widgetEntriesToHtml(widgetMenuPolicy::MENU_ATTACHMENT, [
				$this->buildWidgetEntry($attachmentUrl, 'deleteFile', 'Delete file', ''),
			]) . '</template>';
		}
	}

	/**
	 * Serialise widget entries to the anchor markup the widget menus read, minus the ones this
	 * board turns off - nothing else filters an entry the front-end clones back in.
	 */
	private function widgetEntriesToHtml(string $menu, array $entries): string {
		$entries = $this->getMenuPolicy()->filter($menu, $entries);

		$html = '';

		foreach ($entries as $w) {
			$paramAttrs = '';
			foreach ($w['params'] as $k => $v) {
				$paramAttrs .= ' data-param-' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
			}
			$html .= '<a href="' . htmlspecialchars($w['href']) . '" data-action="' . htmlspecialchars($w['action']) . '" data-label="' . htmlspecialchars($w['label']) . '" data-subMenu="' . htmlspecialchars($w['subMenu']) . '"' . $paramAttrs . '></a>';
		}

		return $html;
	}

	protected function handleModuleRequest(): void {
		// get action
		$action = $this->moduleContext->request->getParameter('action', null, '');

		// attachments are addressed by file ID, never by a selection
		if ($action === 'attachmentDel') {
			$this->handleAttachmentDeletion();
			return;
		}

		// the [Moderate] window sends post_uids[], the per-post controls a single post_uid
		$postUids = $this->getRequestedPostUids();

		match ($action) {
			'del', 'delete' => $this->handlePostDeletion($postUids, false),
			'delmute', 'mute' => $this->handlePostDeletion($postUids, true),
			default => throw new BoardException('ERROR: Invalid action.'),
		};
	}

	/**
	 * Delete every selected post, optionally muting the hosts behind them.
	 *
	 * The whole selection is one query to read, one soft-delete pass, one ban-file write and one
	 * rebuild per affected board — the cost of deleting fifty posts is the cost of deleting one
	 * plus rows, not fifty round trips.
	 */
	private function handlePostDeletion(array $postUids, bool $mute): void {
		$posts = $this->fetchRequestedPosts($postUids);

		// skip anything already deleted rather than failing the whole selection
		$targets = array_values(array_filter($posts, fn(Post $post) => $this->canRenderButton($post)));

		if (!$targets) {
			throw new BoardException('Post already deleted!');
		}

		$targetUids = array_map(fn(Post $post) => $post->getUid(), $targets);

		// One transaction for the whole action: the deletion service and logAction all join it,
		// so the request pays a single commit flush instead of one per write.
		$this->moduleContext->transactionManager->run(function () use ($targets, $targetUids, $mute): void {
			$this->moduleContext->postService->removePosts($targetUids, $this->moduleContext->currentUserId);

			if ($mute) {
				$this->muteHostsFromPosts($targets);
			}

			$this->logDeletions($targets, $mute);
		});

		// AJAX first: send JSON, flush to client, then rebuild in the background of this request.
		if ($this->moduleContext->request->isAjax()) {
			$firstPost = $targets[0];
			$deletedPostIds = $this->moduleContext->deletedPostsService->getDeletedPostIdsByPostUids($targetUids);

			$results = [];
			foreach ($deletedPostIds as $postUid => $deletedPostId) {
				$results[$postUid] = [
					'deleted_link' => $this->getDeletionViewUrl($deletedPostId),
					'deleted_post_id' => $deletedPostId,
				];
			}

			// The single-post keys stay for the per-post widgets, which know nothing of selections.
			sendAjaxAndDetach([
				'success' => true,
				'is_op' => $firstPost->isOp(),
				'deleted_link' => $results[$firstPost->getUid()]['deleted_link'] ?? '',
				'deleted_post_id' => $results[$firstPost->getUid()]['deleted_post_id'] ?? null,
				'results' => $results,
			]);

			// ===== rebuild after the response has been sent =====
			$this->rebuildAfterDeletion($targets);
			exit;
		}

		// Non-AJAX fallback: do the rebuild first, then redirect
		$this->rebuildAfterDeletion($targets);

		// Fallback for non-JS users: redirect
		redirect($this->moduleContext->request->getReferer());
	}

	/**
	 * Mute every distinct host in the selection with one read/write of the ban file.
	 */
	private function muteHostsFromPosts(array $posts): void {
		$startTime = $this->moduleContext->request->getRequestTime();
		$expires = $startTime + intval($this->JANIMUTE_LENGTH) * 60;

		$hosts = array_values(array_unique(array_filter(array_map(fn(Post $post) => $post->getIp(), $posts))));

		if (!$hosts) {
			return;
		}

		$this->addBanEntries($this->getGlobalBanFilePath(), $hosts, $startTime, $expires, $this->JANIMUTE_REASON);
	}

	/**
	 * One log line per board the selection touched, listing the post numbers it took with it.
	 */
	private function logDeletions(array $posts, bool $muted): void {
		$numbersByBoard = [];
		foreach ($posts as $post) {
			$numbersByBoard[$post->getBoardUID()][] = 'No.' . $post->getNumber();
		}

		foreach ($numbersByBoard as $boardUid => $numbers) {
			$this->logAction('Deleted post ' . implode(', ', $numbers), (int)$boardUid);
		}

		if (!$muted) {
			return;
		}

		$hosts = array_unique(array_filter(array_map(fn(Post $post) => $post->getIp(), $posts)));
		if ($hosts) {
			$this->logAction('Muted ' . implode(', ', $hosts), GLOBAL_BOARD_UID);
		}
	}

	/**
	 * A lone reply only invalidates its own page; anything wider is cheaper to rebuild whole,
	 * per board the selection reached into.
	 */
	private function rebuildAfterDeletion(array $posts): void {
		if (count($posts) === 1) {
			$post = $posts[0];
			$this->rebuildBoardForPost(searchBoardArrayForBoard($post->getBoardUID()), $post);
			return;
		}

		rebuildBoardsFromPosts(
			array_map(fn(Post $post) => $post->getUid(), $posts),
			$this->moduleContext->postService
		);
	}

	private function handleAttachmentDeletion(): void {
		$post = $this->fetchValidatedPost($this->moduleContext->request->getParameter('post_uid'));

		validatePostInput($post, false, 404);

		$fileId = (int)$this->moduleContext->request->getParameter('fileId');

		if (empty($fileId)) {
			throw new BoardException("Invalid file ID supplied!");
		}

		$attachment = $post->getAttachmentById($fileId);

		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$board = searchBoardArrayForBoard($post->getBoardUID());

		$this->moduleContext->transactionManager->run(function () use ($attachment, $post, $board): void {
			$this->moduleContext->deletedPostsService->deleteFilesFromPosts([$attachment], $this->moduleContext->currentUserId);
			$this->logAction('Deleted file for post No.' . $post->getNumber(), $board->getBoardUID());
		});

		if ($this->moduleContext->request->isAjax()) {
			$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowByFileId($fileId);

			sendAjaxAndDetach([
				'success' => true,
				'is_op' => $post->isOp(),
				'deleted_link' => $this->getDeletionViewUrl($deletedPost->getDeletedPostId()),
				'deleted_post_id' => $deletedPost->getDeletedPostId()
			]);

			$this->rebuildBoardForPost($board, $post);
			exit;
		}

		$this->rebuildBoardForPost($board, $post);

		redirect($this->moduleContext->request->getReferer());
	}

	private function getDeletionViewUrl(int $deletedPostId): string {
		// base url
		$baseUrl = $this->moduleContext->request->getCurrentUrlNoQuery();

		// parameters for the link
		$urlParameters = [
			'pageName' => 'viewMore',
			'deletedPostId' => $deletedPostId,
			'moduleMode' => 'admin',
			'mode' => 'module',
			'load' => 'deletedPosts'
		];

		// build the query parameters
		$queryParameters = http_build_query($urlParameters);

		// construct the link
		$viewUrl = $baseUrl . '?' . $queryParameters;

		// return the url
		return $viewUrl;
	}
}
