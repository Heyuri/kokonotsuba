<?php

namespace Kokonotsuba\Modules\edit;

require_once __DIR__ . '/editAttachments.php';
require_once __DIR__ . '/editPostFields.php';
require_once __DIR__ . '/editedPostRenderer.php';
require_once __DIR__ . '/postRevisionService.php';

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\html\getPageForPostPosition;
use function Kokonotsuba\libraries\rebuildBoardsFromPosts;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;
use function Kokonotsuba\libraries\html\buildTagSelectOptions;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;

	/** GET pageName that answers with the post's editable fields as JSON. */
	private const FIELDS_PAGE = 'fields';

	/** GET pageName that draws a post's edit history. */
	private const REVISIONS_PAGE = 'revisions';

	private editAttachments $attachments;
	private postRevisionService $revisions;

	/**
	 * The module lets in whoever may do the least of what it offers.
	 *
	 * Reading a post's edit history is not editing it, and the two are separate capabilities, so
	 * the gate here is the lower of them and every path below asks for the one it needs.
	 */
	public function getRequiredRole(): userRole {
		$editRole = $this->getEditRole();
		$viewRole = $this->getViewRevisionsRole();

		return $viewRole->isLessThan($editRole) ? $viewRole : $editRole;
	}

	private function getEditRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_EDIT_POST', userRole::LEV_MODERATOR);
	}

	private function getViewRevisionsRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_VIEW_POST_REVISIONS', userRole::LEV_JANITOR);
	}

	private function getRestoreRevisionsRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_RESTORE_POST_REVISIONS', userRole::LEV_MODERATOR);
	}

	private function holdsRole(userRole $role): bool {
		return !getRoleLevelFromSession()->isLessThan($role);
	}

	/** Refuse a request from someone the module let in for the other half of it. */
	private function assertRole(userRole $role): void {
		if (!$this->holdsRole($role)) {
			throw new BoardException(_T('post_revision_no_permission'), 403);
		}
	}

	public function getName(): string {
		return 'Mod editing tools';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->attachments = new editAttachments($this->moduleContext);

		$this->revisions = new postRevisionService(new postRevisionRepository(
			databaseConnection::getInstance(),
			$this->moduleContext->getTableName('POST_EDIT_REVISION_TABLE'),
			$this->moduleContext->getTableName('ACCOUNT_TABLE')
		));

		// Registered by hand rather than through registerAdminHeaderHook() and
		// registerSimplePostWidget(), which gate on the module's own role - and that is now the
		// lower of two. Only somebody who may edit needs the edit window's form and script.
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getEditRole(),
			'ModuleAdminHeader',
			function(string &$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getEditRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, Post &$post) {
				$url = $this->getModulePageURL(['postUid' => $post->getUid()], false, true);
				$widgetArray[] = $this->buildWidgetEntry($url, 'editPost', _T('edit_post'), '');
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getViewRevisionsRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, Post &$post) {
				$widgetArray[] = $this->buildWidgetEntry(
					$this->getRevisionsUrl($post->getUid(), false),
					'viewPostRevisions',
					_T('view_post_revisions'),
					''
				);
			}
		);

		// noscript fallback: link to edit form page
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getEditRole(),
			'PostAdminControls',
			function(string &$modControlSection, Post &$post) {
				$url = $this->getModulePageURL(['postUid' => $post->getUid()], false, true);
				$modControlSection .= generateModerateButton($url, 'E', _T('edit_post'), 'adminEditFunction', true);
			}
		);
	}

	/** This module's history page for a post. */
	private function getRevisionsUrl(int $postUid, bool $forHtml = true): string {
		return $this->getModulePageURL(['postUid' => $postUid, 'pageName' => self::REVISIONS_PAGE], $forHtml, true);
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// include the post edit js for the mod tool
		$this->includeScript('postEdit.js', $moduleHeader);

		// Render empty create form
		$postEditFormTemplate = $this->moduleContext->adminPageRenderer->ParseBlock('POST_EDIT_FORM', $this->buildFormValues());

		// append the form template to the module header so it's available on the page (but hidden until triggered)
		$moduleHeader .= $this->generateTemplate('postEditFormTemplate', $postEditFormTemplate);
	}

	/**
	 * Template values for POST_EDIT_FORM, filled in twice: blank for the <template> the window
	 * clones, and populated for the plain page. The attachment row is drawn out of the blank one
	 * because the window builds the list itself from the fields response.
	 */
	private function buildFormValues(?Post $post = null): array {
		return [
			'{$POST_UID}' => $post?->getUid() ?? 0,
			'{$POST_NUMBER}' => $post?->getNumber() ?? 0,
			'{$NAME}' => $post ? sanitizeStr($post->getName()) : '',
			'{$COMMENT}' => $post ? sanitizeStr(editPostFields::commentToEditableText($post->getComment(), $post->getTextFormat())) : '',
			'{$SUBJECT}' => $post ? sanitizeStr($post->getSubject()) : '',
			'{$EMAIL}' => $post ? sanitizeStr($post->getEmail()) : '',
			'{$FORM_NAME}' => _T('form_name'),
			'{$FORM_EMAIL}' => _T('form_email'),
			'{$FORM_TOPIC}' => _T('form_topic'),
			'{$FORM_COMMENT}' => _T('form_comment'),
			'{$FORM_TAG}' => _T('form_tag'),
			'{$FORM_ATTACHMENTS}' => sanitizeStr(_T('edit_attachments')),
			'{$ATTACHMENTS_DESCRIPTION}' => _T('edit_attachments_description'),
			'{$NO_ATTACHMENTS_TEXT}' => sanitizeStr(_T('edit_attachments_none')),
			'{$SHOW_ATTACHMENTS}' => $post !== null && $this->attachments->enabledFor($post),
			'{$ATTACHMENT_LIST}' => $post ? $this->attachments->renderList($post) : '',
			'{$TAG_SELECT}' => buildTagSelectOptions($this->getConfig('TAGS', []), $post?->getTag() ?? ''),
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput()
		];
	}

	private function editPost(
		Post $post,
		?string $name,
		?string $comment,
		?string $subject,
		?string $email,
		?string $tag
	): void {
		// parameters to update in the query
		$updatePostParameters = [
			'name' => $name,
			'com' => $comment,
			'sub' => $subject,
			'email' => $email,
			'tag' => $tag
		];

		// store the comment the way this post's rows are stored
		if($comment !== null) {
			$updatePostParameters['com'] = editPostFields::editableTextToComment($comment, $post->getTextFormat());
		}

		// Filter out null values
		$updatePostParameters = array_filter($updatePostParameters, function($v) { return $v !== null; });

		// update the post in database
		$this->moduleContext->postRepository->updatePost($post->getUid(), $updatePostParameters);
	}

	/**
	 * The post as the edit form wants it: the stored values, with a legacy comment's <br> turned
	 * back into newlines so the textarea shows lines rather than markup.
	 *
	 * textFormat rides along because these values are stored text, not markup: the window falls
	 * back on them to patch the page when the board's template has no post block to render, and
	 * has to know whether it is holding HTML or something to put in as text.
	 *
	 * @param Post $post The post being edited.
	 * @return array<string, mixed> JSON payload.
	 */
	private function buildFieldsPayload(Post $post): array {
		return [
			'postUid' => $post->getUid() ?? '',
			'postNumber' => $post->getNumber() ?? '',
			'postUserName' => $post->getName() ?? '',
			'postEmail' => $post->getEmail() ?? '',
			'subject' => $post->getSubject() ?? '',
			'comment' => editPostFields::commentToEditableText($post->getComment() ?? '', $post->getTextFormat()),
			'tag' => $post->getTag() ?? '',
			'textFormat' => $post->getTextFormat()->value,
		] + $this->attachments->payload($post);
	}

	/** Fetch a post, or throw if the uid does not name one. */
	private function getPost(int $postUid): Post {
		$post = $this->moduleContext->postRepository->getPostByUID(
			$postUid,
			$this->moduleContext->postRenderingPolicy->viewDeleted()
		);

		validatePostInput($post, false);

		return $post;
	}

	/** Answer the window's request for the values to put in its fields. */
	private function sendFields(int $postUid): void {
		$this->assertRole($this->getEditRole());

		sendAjaxAndDetach($this->buildFieldsPayload($this->getPost($postUid)));
		exit;
	}

	/**
	 * Send the user back to the post they just edited.
	 *
	 * The edit form is reachable from mod pages that list posts from every board, so the redirect
	 * has to be built from the edited post's own board rather than the one this request happens to
	 * be served from — otherwise editing a post on another board drops you on the current board.
	 */
	private function redirect(?Post $post): void {
		// no post to go back to, so fall back to the board this request was served from
		if($post === null) {
			redirect($this->moduleContext->board->getBoardURL());
			return;
		}

		// the board the edited post was made to
		$board = searchBoardArrayForBoard($post->getBoardUID()) ?? $this->moduleContext->board;

		$postNumber = $post->getNumber();

		// fallback redirect to the board if the post number isn't available for some reason
		if(!$postNumber) {
			redirect($board->getBoardURL());
			return;
		}

		// replies live under their thread, so link the thread and anchor the post within it
		$threadNumber = $post->getOpNumber() ?: $postNumber;
		$page = getPageForPostPosition($post->getObjectivePosition(), $board->getConfigValue('REPLIES_PER_PAGE', 200));

		redirect($board->getBoardThreadURL($threadNumber, $postNumber, false, $page));
	}

	/**
	 * Whether the page the edit was made from shows a single thread.
	 *
	 * The window sends its own context because this request is made against the module URL, which
	 * carries none: the same post is marked up differently in a thread than in an index listing.
	 */
	private function isThreadViewRequest(): bool {
		return $this->moduleContext->request->getParameter('threadView', 'POST', '1') !== '0';
	}

	private function handleEditRequest(int $postUid): void {
		$this->assertRole($this->getEditRole());

		// the post as it stands after the edit, used for the response and the redirect
		$editedPost = null;

		// worked out before the transaction opens: an upload that will be refused should be
		// refused before anything about the post has moved
		$attachmentPlan = $this->attachments->plan($this->getPost($postUid));

		// wrap in transaction to ensure data integrity
		$this->moduleContext->transactionManager->run(function() use ($postUid, $attachmentPlan, &$editedPost) {
			// check the post exists before touching it, and read how its text is stored
			$post = $this->getPost($postUid);

			// get the parameters
			$name = $this->moduleContext->request->getParameter('postUserName', 'POST');
			$comment = $this->moduleContext->request->getParameter('comment', 'POST');
			$subject = $this->moduleContext->request->getParameter('subject', 'POST');
			$email = $this->moduleContext->request->getParameter('postEmail', 'POST');
			$tag = $this->moduleContext->request->getParameter('tag', 'POST');

			// what the post said before this edit, so the history has something to show
			$this->revisions->record($post, (int)$this->moduleContext->currentUserId);

			// handle the edit
			$this->editPost($post, $name, $comment, $subject, $email, $tag);

			$this->attachments->commit($post, $attachmentPlan, (int)$this->moduleContext->currentUserId);

			// read the post back so the response and the redirect describe what was actually saved
			$editedPost = $this->getPost($postUid);
		});

		// log the edit action
		$this->logAction(
			"Edited post No.{$editedPost->getNumber()}",
			$editedPost->getBoardUID() ?? GLOBAL_BOARD_UID,
			actionType::POST_EDIT
		);

		// send json data back if it's a js request
		if($this->moduleContext->request->isAjax()) {
			// built first: rendering a post rewrites its comment in place with the quote links,
			// and the fields the window falls back on have to stay as they are stored
			$payload = $this->buildFieldsPayload($editedPost);

			$renderer = new editedPostRenderer($this->moduleContext);
			$payload['html'] = $renderer->render($editedPost, $this->isThreadViewRequest());

			// answer the window before rebuilding: the page it edits is already up to date from
			// this payload, and an OP edit rebuilds every static page of the board
			sendAjaxAndDetach($payload);
			rebuildBoardsFromPosts([$postUid], $this->moduleContext->postService);
			exit;
		}

		// rebuild the board html of the post
		rebuildBoardsFromPosts([$postUid], $this->moduleContext->postService);

		// redirect back to the post after edit
		$this->redirect($editedPost);
	}

	private function handleEditPage(int $postUid): void {
		// get post details for widget
		$post = $this->getPost($postUid);

		// page content
		$pageContent = $this->moduleContext->adminPageRenderer->ParseBlock('POST_EDIT_FORM', $this->buildFormValues($post));

		// render the edit form with post details
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageContent], true);
	}

	// ─── Edit history ─────────────────────────────────────────────

	/**
	 * A post's edit history, newest first.
	 *
	 * Each entry is the post as it stood before one edit, so restoring the newest undoes the last
	 * edit. A restore is an edit of its own and records its own revision, which is what keeps the
	 * history a record rather than a trap.
	 */
	private function drawRevisionsPage(int $postUid): void {
		$this->assertRole($this->getViewRevisionsRole());

		$post = $this->getPost($postUid);
		$canRestore = $this->holdsRole($this->getRestoreRevisionsRole());

		$listHtml = '';
		foreach ($this->revisions->getRevisionsForPost($postUid) as $revision) {
			$listHtml .= $this->renderRevision($revision, $canRestore);
		}

		$pageContent = $this->moduleContext->adminPageRenderer->ParseBlock('POST_REVISIONS', [
			'{$PAGE_TITLE}' => sanitizeStr(_T('post_revisions_title', (string)$post->getNumber())),
			'{$POST_UID}' => $postUid,
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$REVISION_LIST}' => $listHtml,
			'{$NO_REVISIONS_TEXT}' => sanitizeStr(_T('post_revisions_none')),
		]);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageContent], true);
	}

	/** One revision, with the values it holds and the button that puts them back. */
	private function renderRevision(array $revision, bool $canRestore): string {
		$editor = $revision['edited_by_username'] ?? null;

		return $this->moduleContext->adminPageRenderer->ParseBlock('POST_REVISION_ENTRY', [
			'{$REVISION_ID}' => (int)$revision['id'],
			'{$REVISION_HEADING}' => sanitizeStr(_T(
				'post_revision_heading',
				strip_tags($this->moduleContext->postDateFormatter->formatFromDateString((string)($revision['edited_at'] ?? '')))
			)),
			'{$REVISION_BY}' => sanitizeStr(_T('post_revision_by', $editor ?: _T('post_revision_by_poster'))),
			'{$CAN_RESTORE}' => $canRestore,
			'{$RESTORE_LABEL}' => sanitizeStr(_T('post_revision_restore')),
			'{$RESTORE_TITLE}' => sanitizeStr(_T('post_revision_restore_title')),
			'{$REVISION_FIELDS}' => $this->renderRevisionFields($revision),
		]);
	}

	/** The revision's stored values, one labelled row each. */
	private function renderRevisionFields(array $revision): string {
		$labels = [
			'name' => 'form_name',
			'email' => 'form_email',
			'sub' => 'form_topic',
			'com' => 'form_comment',
			'tag' => 'form_tag',
		];

		$html = '';

		foreach ($labels as $field => $labelKey) {
			$value = (string)($revision[$field] ?? '');

			$html .= $this->moduleContext->adminPageRenderer->ParseBlock('POST_REVISION_FIELD', [
				'{$FIELD_LABEL}' => sanitizeStr(_T($labelKey)),
				'{$FIELD_VALUE}' => $value === ''
					? '<i>' . sanitizeStr(_T('post_revision_empty')) . '</i>'
					: nl2br(sanitizeStr($value)),
			]);
		}

		return $html;
	}

	/**
	 * Put a revision's values back on its post.
	 *
	 * The post as it stands is recorded first, so a restore can itself be undone, and the board
	 * is rebuilt afterwards exactly as an ordinary edit rebuilds it.
	 */
	private function handleRestoreRequest(int $postUid): void {
		$this->assertRole($this->getRestoreRevisionsRole());

		$revisionId = (int)$this->moduleContext->request->getParameter('revisionId', 'POST', 0);
		$revision = $revisionId > 0 ? $this->revisions->getRevisionById($revisionId) : false;

		if (!$revision || (int)$revision['post_uid'] !== $postUid) {
			throw new BoardException(_T('post_revision_not_found'), 404);
		}

		$restoredPost = null;

		$this->moduleContext->transactionManager->run(function() use ($postUid, $revision, &$restoredPost) {
			$post = $this->getPost($postUid);

			$this->revisions->record($post, (int)$this->moduleContext->currentUserId);
			$this->moduleContext->postRepository->updatePost($postUid, $this->revisions->valuesOf($revision));

			$restoredPost = $this->getPost($postUid);
		});

		$this->logAction(
			"Restored post No.{$restoredPost->getNumber()} from revision #{$revisionId}",
			$restoredPost->getBoardUID() ?? GLOBAL_BOARD_UID,
			actionType::POST_EDIT
		);

		rebuildBoardsFromPosts([$postUid], $this->moduleContext->postService);

		redirect($this->getRevisionsUrl($postUid, false));
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $this->moduleContext->request->getParameter('postUid');

		// validate post uid
		validatePostInput($postUid);

		// handle the main edit requests
		if($this->moduleContext->request->isPost()) {
			requirePostWithCsrf($this->moduleContext->request);

			if($this->moduleContext->request->getParameter('action', 'POST', '') === 'restoreRevision') {
				$this->handleRestoreRequest((int)$postUid);
				return;
			}

			$this->handleEditRequest($postUid);
		}
		// the window asking for the values to fill its fields with
		else if($this->moduleContext->request->getParameter('pageName', 'GET', '') === self::FIELDS_PAGE) {
			$this->sendFields($postUid);
		}
		// the post's edit history
		else if($this->moduleContext->request->getParameter('pageName', 'GET', '') === self::REVISIONS_PAGE) {
			$this->drawRevisionsPage((int)$postUid);
		}
		// otherwise just render the form
		else {
			$this->assertRole($this->getEditRole());
			$this->handleEditPage($postUid);
		}
	}
}
