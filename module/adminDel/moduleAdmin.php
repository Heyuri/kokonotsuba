<?php

namespace Kokonotsuba\Modules\adminDel;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanFileOperationsTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\deletion\DeletedPost;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\generateModerateForm;
use function Kokonotsuba\libraries\getCsrfMetaTag;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\request\redirect;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
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
		$this->JANIMUTE_LENGTH = $this->getConfig('ModuleSettings.JANIMUTE_LENGTH');
		$this->JANIMUTE_REASON = $this->getConfig('ModuleSettings.JANIMUTE_REASON');

		$this->registerPostControlPair('onRenderPostAdminControls');
		$this->registerPostWidgetHook('onRenderPostWidget');
		$this->registerAdminHeaderHook('onGenerateModuleHeader');
		$this->registerAttachmentHook('onRenderAttachment');
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

	private function onRenderAttachment(string &$attachmentProperties, array &$attachment): void {
		$canRender = $this->canRenderAttachmentButton($attachment);

		$buttonHtml = '';
		if ($canRender) {
			$url = $this->generateDeleteAttachUrl($attachment['fileId'], $attachment['postUid']);
			$buttonHtml = ' <span class="adminFunctions adminDeleteFileFunction attachmentButton">[<a href="' . htmlspecialchars($url) . '" title="Delete file" data-action="deleteFile">DF</a>]</span>';
		}

		$attachmentProperties .= $this->renderAttachmentIndicator('deleteFile', $buttonHtml, !$canRender);
	}

	private function generateDeleteAttachUrl(int $fileId, int $postUid): string {
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

		// inject CSRF meta tag for JS
		$moduleHeader .= getCsrfMetaTag();

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
	}

	protected function handleModuleRequest(): void {
		// get post uid from request (POST body from JS, or query string from admin control forms)
		$postUid = $this->moduleContext->request->getParameter('post_uid');

		// validate + fetch post
		$post = $this->fetchValidatedPost($postUid);

		// whether the post has been deleted
		$isDeleted = !empty($post->getOpenFlag()) && !$post->isFileOnlyDeleted();

		// get board object
		$board = searchBoardArrayForBoard($post->getBoardUID());
		
		// get board uid
		$boardUID = $board->getBoardUID();

		// validate the post input for deletion
		validatePostInput($post, false, 404);

		// throw an error if the post was already deleted
		if ($isDeleted) {
			throw new BoardException('Post already deleted!');
		}

		// get action
		$action = $this->moduleContext->request->getParameter('action', null, '');
		
		switch ($action) {
			case 'del':
			case 'delete':
				$this->moduleContext->postService->removePosts([$post->getUid()], $this->moduleContext->currentUserId);
				$this->logAction('Deleted post No.'.$post->getNumber(), $boardUID);
				break;
			case 'delmute':
			case 'mute':
				$this->moduleContext->postService->removePosts([$post->getUid()], $this->moduleContext->currentUserId);
				$ip = $post->getIp();
				$starttime = $this->moduleContext->request->getRequestTime();
				$expires = $starttime + intval($this->JANIMUTE_LENGTH) * 60;
				$reason = $this->JANIMUTE_REASON;

				if ($ip) {
					$this->addBanEntry($this->getGlobalBanFilePath(), $ip, $starttime, $expires, $reason);
				}

				$this->logAction('Muted '.$ip.' and deleted post No.'.$post->getNumber() . ' ' . $board->getBoardTitle() . ' (' . $board->getBoardUID() . ')', GLOBAL_BOARD_UID);

				break;
			case 'attachmentDel':
				// get the file Id
				$fileId = $this->moduleContext->request->getParameter('fileId');
				
				// cast to int
				$fileId = (int)$fileId;

				// throw board exception if its null/empty/zero or isn't an integer
				if(empty($fileId) || !is_int($fileId)) {
					throw new BoardException("Invalid file ID supplied!");
				}

				// get the attachment to deleted
				$attachment = $post->getAttachmentById($fileId);

				if(!$attachment) {
					throw new BoardException(_T('attachment_not_found'));
				}

				$this->moduleContext->deletedPostsService->deleteFilesFromPosts([$attachment], $this->moduleContext->currentUserId);

				$this->logAction('Deleted file for post No.'.$post->getNumber(), $boardUID);
				break;
			default:
				throw new BoardException('ERROR: Invalid action.');
				break;
		}
		// Will be implemented later
		//deleteThreadCache($post['thread_uid']);

		// AJAX first: send JSON, flush to client, then rebuild in the background of this request.
		if ($this->moduleContext->request->isAjax()) {
			// if it was an attachment deletion then use the appropriate method to generate the url
			if($action === 'attachmentDel' && $fileId) {
				$deletionUrl = $this->getDeletionUrlForAttachment($fileId);
			}
			// otherwise just get the regular deletion url
			else {
				$deletionUrl = $this->getDeletionUrlForPost($post->getUid());
			}

			// Return JSON for AJAX requests
			header('Content-Type: application/json');
			echo json_encode([
				'success' => true,
				'is_op' => $post->isOp(),
				'deleted_link' => $deletionUrl
			]);
		
			// Let the client go on; we keep working server-side.
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_write_close();
			}
			if (function_exists('fastcgi_finish_request')) {
				fastcgi_finish_request();
			} else {
				// Best-effort flush for non-FPM SAPIs
				ob_flush();
				flush();
			}
		
			// ===== rebuild after the response has been sent =====
			$this->rebuildBoardForPost($board, $post);
			exit;
		}

		// Non-AJAX fallback: do the rebuild first, then redirect (unchanged)
		$this->rebuildBoardForPost($board, $post);

		// Fallback for non-JS users: redirect
		redirect($this->moduleContext->request->getReferer());
	}

	private function getDeletionUrlForPost(int $postUid): string {
		// fetch the deleted post by post uid
		$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowByPostUid($postUid);

		// now get the deleted post url and return
		return $this->getDeletionViewUrl($deletedPost);
	}

	private function getDeletionUrlForAttachment(int $fileId): string {
		// fetch the deleted post by file id
		$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowByFileId($fileId);

		// now get the deleted post url and return
		return $this->getDeletionViewUrl($deletedPost);
	}

	private function getDeletionViewUrl(DeletedPost $deletedPost): string {
		// get the deleted post id for the url
		$deletedPostId = $deletedPost->getDeletedPostId();

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
