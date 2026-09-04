<?php

namespace Kokonotsuba\Modules\edit;

require_once __DIR__ . '/editAttachments.php';
require_once __DIR__ . '/editPostFields.php';
require_once __DIR__ . '/editedPostRenderer.php';
require_once __DIR__ . '/postRevisionService.php';

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\board\board;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostWidgetListenerTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\textFormat;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\getOrCreateCsrfToken;
use function Kokonotsuba\libraries\html\buildTagSelectOptions;
use function Kokonotsuba\libraries\html\getPageForPostPosition;
use function Kokonotsuba\libraries\rebuildBoardsFromPosts;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\json\renderPrivateJsonPage;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;
use function Puchiko\strings\strlenUnicode;

/**
 * Reader-facing half of the edit module: editing your own post with its password.
 *
 * It is the moderator editor with one thing added and one thing taken away. Added is the post
 * password, which is the only thing standing in for an identity here — the same secret that
 * deletes a post, checked the same way. Taken away is the reach: a reader edits one post, on its
 * own board, while it is still young enough, and never a deleted one.
 *
 * The window is the moderator's window (static/js/module/postEdit.js registers both), so the two
 * cannot drift apart, and the plain form page below it is what people without JS get.
 */
class moduleMain extends abstractModuleMain {
	use AuditableTrait;
	use BanCheckpointTrait;
	use ModuleHeaderListenerTrait;
	use PostListenerTrait;
	use PostWidgetListenerTrait;

	/** GET pageName that answers with the post's editable fields as JSON. */
	private const FIELDS_PAGE = 'fields';

	/** The post menu action this module's entry carries; postEdit.js opens the window on it. */
	private const WIDGET_ACTION = 'editOwnPost';

	private editAttachments $attachments;
	private postRevisionService $revisions;

	public function getName(): string {
		return 'Post editing';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		// Nothing is hooked when the board has reader editing off, so no menu entry, no link and
		// no form ever appear — ModulePage() refuses on its own for anyone holding an old URL.
		if (!$this->userEditingEnabled()) {
			return;
		}

		$this->attachments = new editAttachments($this->moduleContext);

		$this->revisions = new postRevisionService(new postRevisionRepository(
			databaseConnection::getInstance(),
			$this->moduleContext->getTableName('POST_EDIT_REVISION_TABLE'),
			$this->moduleContext->getTableName('ACCOUNT_TABLE')
		));

		$this->listenPostWidget('onRenderPostWidget');
		$this->listenPost('onRenderPost');
		$this->listenModuleHeader('onGenerateModuleHeader');
	}

	// ─── Frontend hooks ───────────────────────────────────────────

	/**
	 * Add the edit entry to the post dropdown. The href is a working link on its own, so
	 * middle-click and JS-off both land on the form page.
	 */
	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		if (!$this->isReaderEditable($post)) {
			return;
		}

		$widgetArray[] = $this->buildWidgetEntry(
			$this->getEditFormUrl($post->getUid()),
			self::WIDGET_ACTION,
			_T('edit_own_post'),
			''
		);
	}

	/**
	 * The dropdown toggle is JS-only, so mirror the entry as a plain link for readers without
	 * scripting, the same way the report module does.
	 */
	private function onRenderPost(array &$templateValues, Post &$post, array &$threadPosts, board &$board, bool &$adminMode): void {
		if (!$this->isReaderEditable($post)) {
			return;
		}

		$templateValues['{$POSTINFO_EXTRA}'] .= ' <span class="editLinkContainer no-js-only">[<a href="'
			. $this->getEditFormUrl($post->getUid(), true) . '" title="' . sanitizeStr(_T('edit_own_post_title')) . '">'
			. sanitizeStr(_T('edit_own_post')) . '</a>]</span>';
	}

	/**
	 * Ship the window's script and its form markup.
	 *
	 * The form carries no usable CSRF token: this hook also runs while static HTML is generated,
	 * and a token baked into a cached page would be wrong for everyone who later reads it, so the
	 * fields response hands the window a fresh one when it opens.
	 */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$this->includeScript('postEdit.js', $moduleHeader);

		$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock(
			'USER_POST_EDIT_FORM',
			$this->buildFormValues()
		);

		$moduleHeader .= $this->generateTemplate('userPostEditFormTemplate', $formHtml);
	}

	// ─── Routing ──────────────────────────────────────────────────

	public function ModulePage(): void {
		if (!$this->userEditingEnabled()) {
			throw new BoardException(_T('edit_own_disabled'), 403);
		}

		$request = $this->moduleContext->request;
		$postUid = $request->getParameter('postUid');

		validatePostInput($postUid);

		if ($request->isPost()) {
			requirePostWithCsrf($request);
			$this->handleEditRequest((int)$postUid);
			return;
		}

		if ((string)$request->getParameter('pageName', 'GET', '') === self::FIELDS_PAGE) {
			$this->sendFields((int)$postUid);
			return;
		}

		$this->drawEditFormPage((int)$postUid);
	}

	/**
	 * The values the window puts in its fields, plus a token for the form it cloned.
	 *
	 * Everything here is already on the page the reader is looking at, so no password is asked
	 * for yet — that is checked when the edit is actually submitted.
	 */
	private function sendFields(int $postUid): void {
		$post = $this->getEditablePost($postUid);

		renderPrivateJsonPage($this->buildFieldsPayload($post) + ['csrfToken' => getOrCreateCsrfToken()]);
	}

	/** The plain edit form, for readers without JS. */
	private function drawEditFormPage(int $postUid): void {
		$post = $this->getEditablePost($postUid);

		$this->assertEditable($post);

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('USER_POST_EDIT_FORM', $this->buildFormValues(
			$post->getUid(),
			$post->getNumber(),
			sanitizeStr($post->getName()),
			sanitizeStr($post->getEmail()),
			sanitizeStr($post->getSubject()),
			sanitizeStr(editPostFields::commentToEditableText($post->getComment(), $post->getTextFormat())),
			buildTagSelectOptions($this->getConfig('TAGS', []), $post->getTag()),
			getCsrfHiddenInput(),
			$this->withinAttachmentWindow($post) && $this->attachments->enabledFor($post),
			$this->attachments->renderList($post)
		));

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $contentHtml,
			'{$PAGER}' => '',
		], false);
	}

	// ─── Saving an edit ───────────────────────────────────────────

	private function handleEditRequest(int $postUid): void {
		$request = $this->moduleContext->request;

		$post = $this->getEditablePost($postUid);

		$this->assertEditable($post);
		$this->assertPasswordMatches($post);

		$name = (string)$request->getParameter('postUserName', 'POST', '');
		$email = (string)$request->getParameter('postEmail', 'POST', '');
		$subject = (string)$request->getParameter('subject', 'POST', '');
		$comment = (string)$request->getParameter('comment', 'POST', '');
		$tag = (string)$request->getParameter('tag', 'POST', '');

		$this->assertFieldLengths($name, $email, $subject, $comment);

		// worked out before anything is written: an upload this board would refuse has to be
		// refused while the post is still untouched
		$attachmentPlan = $this->planAttachments($post);

		$this->assertLeavesSomethingToRead($comment, $attachmentPlan['remaining']);

		$name = $this->resolveName($name);

		// the post as it stands after the edit, used for the response and the redirect
		$editedPost = null;

		$this->moduleContext->transactionManager->run(function() use ($post, $postUid, $name, $email, $subject, $comment, $tag, $attachmentPlan, &$editedPost) {
			// what the post said before this edit, filed against nobody: the poster made it
			$this->revisions->record($post, null);

			$this->moduleContext->postRepository->updatePost($postUid, [
				'name' => $name,
				'email' => $email,
				'sub' => $subject,
				'com' => editPostFields::editableTextToComment($comment, $post->getTextFormat()),
				'tag' => $tag,
			]);

			$this->attachments->commit($post, $attachmentPlan, null);

			// read the post back so the response and the redirect describe what was actually saved
			$editedPost = $this->getEditablePost($postUid);
		});

		$this->logAction("Edited own post No.{$editedPost->getNumber()}", $editedPost->getBoardUID(), actionType::POST_EDIT);

		if ($this->moduleContext->request->isAjax()) {
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

		rebuildBoardsFromPosts([$postUid], $this->moduleContext->postService);

		$this->redirectToPost($editedPost);
	}

	// ─── Authorization ────────────────────────────────────────────

	/**
	 * Whether this post may be edited by a reader at all, ban and age aside from the password.
	 *
	 * Checked before the form is drawn as well as before a save, so someone too late to edit is
	 * told so rather than being handed a form that will refuse them.
	 */
	private function assertEditable(Post $post): void {
		if (!$this->isReaderEditable($post)) {
			$this->fail(_T('edit_own_notplaintext'), 403);
		}

		// Editing is posting by another name: a ban that stops one stops the other, or a banned
		// poster would simply edit the post they are banned for.
		$this->assertNotBanned(banCheckpoint::POST, $post->getBoardUID());

		if (!$this->withinHours((int)$this->getModuleConfig('USER_EDIT_TIME_LIMIT', 1), $post)) {
			$this->fail(_T('edit_own_timeexceeded'), 403);
		}
	}

	/**
	 * Check the password the reader typed against the one the post was made with.
	 *
	 * An empty field falls back to the password cookie, exactly as deleting a post does, so the
	 * usual case of editing from the browser that made the post needs nothing typed at all.
	 */
	private function assertPasswordMatches(Post $post): void {
		$password = (string)$this->moduleContext->request->getParameter('pwd', 'POST', '');

		if ($password === '') {
			$password = (string)$this->moduleContext->cookieService->get('pwdc', '');
		}

		$passwordHash = $post->getPassword();

		// A post with no password stored belongs to nobody: there is nothing to prove.
		if ($passwordHash === '' || $password === '' || !password_verify($password, $passwordHash)) {
			$this->fail(_T('edit_own_wrongpassword'), 403);
		}
	}

	/**
	 * An edit may not empty a post out.
	 *
	 * The post form refuses a submission with neither text nor a file, so an edit that would
	 * leave one behind is refused for the same reason - counting the files the edit itself adds
	 * and drops, not the ones the post happens to carry right now.
	 *
	 * @param int $remainingAttachments Files the post is left with once the edit is applied.
	 */
	private function assertLeavesSomethingToRead(string $comment, int $remainingAttachments): void {
		if (trim($comment) === '' && $remainingAttachments === 0) {
			$this->fail(_T('regist_withoutcomment'), 400);
		}
	}

	/**
	 * What the edit does to the post's files, or nothing at all once that window has closed.
	 *
	 * Attachments have their own, shorter window: the text of an old post is still the poster's
	 * to fix, but swapping the file out from under a thread people have replied to is not the
	 * same act, so it stops much sooner.
	 */
	private function planAttachments(Post $post): array {
		if (!$this->withinAttachmentWindow($post)) {
			$this->assertNoAttachmentChangeRequested();

			return ['remove' => [], 'uploads' => [], 'remaining' => count($this->attachments->current($post))];
		}

		return $this->attachments->plan($post);
	}

	/** A form submitted before the window closed, or hand-written, is told why it is refused. */
	private function assertNoAttachmentChangeRequested(): void {
		$request = $this->moduleContext->request;

		$removals = $request->getParameter(editAttachments::REMOVE_FIELD, 'POST', []);
		$upload = $request->getFile(editAttachments::UPLOAD_FIELD);
		$uploaded = isset($upload['tmp_name']) && is_array($upload['tmp_name'])
			? count(array_filter($upload['tmp_name']))
			: 0;

		if ($removals || $uploaded > 0) {
			$this->fail(_T('edit_attachment_timeexceeded'), 403);
		}
	}

	/** Whether the post is still young enough for its files to be changed by its poster. */
	private function withinAttachmentWindow(Post $post): bool {
		if (!$this->attachments->enabledFor($post)) {
			return false;
		}

		return $this->withinHours((int)$this->getModuleConfig('ATTACHMENT_EDIT_TIME_LIMIT', 1), $post);
	}

	/**
	 * Whether a post is younger than a limit in hours. 0 hours is no limit, and a timestamp that
	 * will not parse lets the edit through rather than locking the post forever.
	 */
	private function withinHours(int $hours, Post $post): bool {
		if ($hours <= 0) {
			return true;
		}

		$postTime = strtotime($post->getRoot());

		return $postTime === false || time() - $postTime <= $hours * 3600;
	}

	/** A blank name takes the board's default, exactly as it would at post time. */
	private function resolveName(string $name): string {
		if ($name !== '' && !preg_match("/^[ |　|]*$/", $name)) {
			return $name;
		}

		if (!$this->getConfig('ALLOW_NONAME', true)) {
			$this->fail(_T('regist_withoutname'), 400);
		}

		return (string)$this->getConfig('DEFAULT_NONAME', '');
	}

	/** Hold an edit to the same field limits the post form enforces. */
	private function assertFieldLengths(string $name, string $email, string $subject, string $comment): void {
		$inputMax = (int)$this->getConfig('INPUT_MAX', 100);

		if (strlenUnicode($name) > $inputMax) {
			$this->fail(_T('regist_nametoolong'), 400);
		}

		if (strlenUnicode($email) > $inputMax) {
			$this->fail(_T('regist_emailtoolong'), 400);
		}

		if (strlenUnicode($subject) > $inputMax) {
			$this->fail(_T('regist_topictoolong'), 400);
		}

		if (strlenUnicode($comment) > (int)$this->getConfig('COMM_MAX', 5000)) {
			$this->fail(_T('regist_commenttoolong'), 400);
		}
	}

	// ─── Helpers ──────────────────────────────────────────────────

	private function userEditingEnabled(): bool {
		return (bool)$this->getModuleConfig('ALLOW_USER_EDIT', true);
	}

	/**
	 * Whether this post's text is safe to hand a reader an editor for.
	 *
	 * A pre-refactor row holds render-ready HTML in every text column, and a raw-HTML one is
	 * staff-authored markup on purpose: in both, whatever was typed into the form reaches the
	 * page as markup. That is fine for a moderator and is exactly what must not be true of a
	 * reader, so only posts stored as what the poster typed are theirs to edit. The default
	 * edit window is days wide, so in practice this only ever excludes posts already too old.
	 */
	private function isReaderEditable(Post $post): bool {
		return $post->getTextFormat() === textFormat::PLAIN_TEXT;
	}

	/**
	 * Fetch a post a reader is allowed to edit.
	 *
	 * Deleted posts are never fetched: a reader has no business pulling one back out, and the
	 * deleted-post viewer is a staff page.
	 */
	private function getEditablePost(int $postUid): Post {
		$post = $this->moduleContext->postRepository->getPostByUid($postUid);

		validatePostInput($post, false);

		if ($post->isDeleted()) {
			throw new BoardException(_T('post_not_found'), 404);
		}

		return $post;
	}

	/**
	 * The post as the edit form wants it: the stored values, with a legacy comment's <br> turned
	 * back into newlines so the textarea shows lines rather than markup.
	 *
	 * @return array<string, mixed> JSON payload.
	 */
	private function buildFieldsPayload(Post $post): array {
		return [
			'postUid' => $post->getUid(),
			'postNumber' => $post->getNumber(),
			'postUserName' => $post->getName(),
			'postEmail' => $post->getEmail(),
			'subject' => $post->getSubject(),
			'comment' => editPostFields::commentToEditableText($post->getComment(), $post->getTextFormat()),
			'tag' => $post->getTag(),
			'textFormat' => $post->getTextFormat()->value,
		] + $this->attachmentPayload($post);
	}

	/**
	 * The attachment half of the fields response.
	 *
	 * A post past the attachment window still edits its text, so the window is told to leave the
	 * attachment row out rather than offer one that the save would refuse.
	 */
	private function attachmentPayload(Post $post): array {
		if (!$this->withinAttachmentWindow($post)) {
			return ['attachments' => [], 'attachmentLimit' => 0, 'canEditAttachments' => false];
		}

		return $this->attachments->payload($post);
	}

	/**
	 * Template values for USER_POST_EDIT_FORM. Filled in twice: blank for the <template> the
	 * window clones, and populated for the no-JS page.
	 */
	private function buildFormValues(
		int $postUid = 0,
		int $postNumber = 0,
		string $name = '',
		string $email = '',
		string $subject = '',
		string $comment = '',
		?string $tagSelect = null,
		?string $csrfInput = null,
		bool $showAttachments = false,
		string $attachmentList = ''
	): array {
		return [
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$CSRF_TOKEN}' => $csrfInput ?? '<input type="hidden" name="csrf_token" value="">',
			'{$POST_UID}' => $postUid,
			'{$POST_NUMBER}' => $postNumber,
			'{$NAME}' => $name,
			'{$EMAIL}' => $email,
			'{$SUBJECT}' => $subject,
			'{$COMMENT}' => $comment,
			'{$TAG_SELECT}' => $tagSelect ?? buildTagSelectOptions($this->getConfig('TAGS', [])),
			'{$FORM_ATTACHMENTS}' => sanitizeStr(_T('edit_attachments')),
			'{$ATTACHMENTS_DESCRIPTION}' => _T('edit_attachments_description'),
			'{$NO_ATTACHMENTS_TEXT}' => sanitizeStr(_T('edit_attachments_none')),
			'{$SHOW_ATTACHMENTS}' => $showAttachments,
			'{$ATTACHMENT_LIST}' => $attachmentList,
			'{$FORM_TITLE}' => sanitizeStr(_T('edit_own_form_title')),
			'{$FORM_NAME}' => sanitizeStr(_T('form_name')),
			'{$FORM_EMAIL}' => sanitizeStr(_T('form_email')),
			'{$FORM_TOPIC}' => sanitizeStr(_T('form_topic')),
			'{$FORM_COMMENT}' => sanitizeStr(_T('form_comment')),
			'{$FORM_TAG}' => sanitizeStr(_T('form_tag')),
			'{$FORM_PASSWORD}' => sanitizeStr(_T('edit_own_password')),
			'{$PASSWORD_HINT}' => sanitizeStr(_T('edit_own_password_hint')),
			'{$SUBMIT_TEXT}' => sanitizeStr(_T('edit_own_submit')),
		];
	}

	/** URL of this module's edit form for a post, on the board the reader is on. */
	private function getEditFormUrl(int $postUid, bool $forHtml = false): string {
		return $this->getModulePageURL(['postUid' => $postUid], $forHtml);
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

	/** Edit rejected: JSON error for the window, a board exception for the plain form. */
	private function fail(string $message, int $statusCode): void {
		if ($this->moduleContext->request->isAjax()) {
			renderJsonErrorPage($message, $statusCode);
			exit;
		}

		throw new BoardException($message, $statusCode);
	}

	/** Send the reader back to the post they just edited. */
	private function redirectToPost(Post $post): void {
		$board = searchBoardArrayForBoard($post->getBoardUID()) ?? $this->moduleContext->board;
		$postNumber = $post->getNumber();

		if (!$postNumber) {
			redirect($board->getBoardURL());
			return;
		}

		// replies live under their thread, so link the thread and anchor the post within it
		$threadNumber = $post->getOpNumber() ?: $postNumber;
		$page = getPageForPostPosition($post->getObjectivePosition(), $board->getConfigValue('REPLIES_PER_PAGE', 200));

		redirect($board->getBoardThreadURL($threadNumber, $postNumber, false, $page));
	}
}
