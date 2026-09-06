<?php

namespace Kokonotsuba\Modules\hostNotes;

require_once __DIR__ . '/hostNoteRepository.php';
require_once __DIR__ . '/hostNoteService.php';
require_once __DIR__ . '/hostNotePolicy.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\ban\visitorTokenSigner;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\ManagePostsHostPanelListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\generatePostUrl;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\getUsernameFromSession;
use function Kokonotsuba\libraries\modIdToColorHex;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Puchiko\json\renderJsonPage;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Puchiko\strings\newLinesToBreakLines;
use function Puchiko\strings\sanitizeStr;

/**
 * Staff notes filed against a host instead of a post.
 *
 * The same shape as the notes module - a widget on the post menu, a list under every post the
 * note applies to, edit and delete in place - except that what a note is attached to is an
 * address or a ban-style wildcard range, so one note follows a poster across their posts and
 * across boards. Notes are drawn apart from post notes under their own header.
 */
class moduleAdmin extends abstractModuleAdmin {
	use ManagePostsHostPanelListenerTrait;
	use PostControlHooksTrait;

	private hostNoteService $hostNoteService;
	private hostNotePolicy $hostNotePolicy;

	/** Filing a note needs the address; reading one under a post does not. */
	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_LEAVE_HOST_NOTE', userRole::LEV_MODERATOR);
	}

	private function getViewRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_VIEW_HOST_NOTE', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Host notes management mod tool';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		$hostNoteRepository = new hostNoteRepository(
			databaseConnection::getInstance(),
			$this->moduleContext->getTableName('HOST_NOTE_TABLE'),
			$this->moduleContext->getTableName('ACCOUNT_TABLE')
		);

		$this->hostNoteService = new hostNoteService($hostNoteRepository, $this->moduleContext->transactionManager);

		$this->hostNotePolicy = new hostNotePolicy(
			$this->getConfig('AuthLevels', []),
			getRoleLevelFromSession(),
			$this->moduleContext->currentUserId
		);

		$this->hostNotePolicy->setHostNoteService($this->hostNoteService);

		$this->registerAdminHeaderHook('onGenerateModuleHeader');
		$this->registerSimplePostWidget('postUid', 'leaveHostNote', _T('leave_host_note'));

		// noscript fallback: link to the host note form page
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, Post &$post) {
				$url = $this->getModulePageURL(['postUid' => $post->getUid()], false, true);
				$modControlSection .= generateModerateButton($url, 'H', _T('leave_host_note'), 'adminHostNoteFunction', true);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getViewRole(),
			'PostsPrefetch',
			function(array $posts) {
				$this->hostNoteService->warmAddresses(array_map(
					static fn(Post $post): string => $post->getIp(),
					array_filter($posts, static fn($post): bool => $post instanceof Post)
				));
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getViewRole(),
			'BelowComment',
			function(string &$belowComment, Post &$post, array &$threadPosts, bool &$adminMode) {
				$this->renderHostNotesOnPost($belowComment, $post, $adminMode);
			}
		);

		$this->listenManagePostsHostPanel('onRenderManagePostsPanel');
	}

	// ─── Admin UI hooks ───────────────────────────────────────────

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$this->includeScript('hostNotes.js', $moduleHeader);

		$moduleHeader .= $this->generateTemplate('hostNoteCreateFormTemplate', $this->renderCreateFormBlock('', '', 0));
		$moduleHeader .= $this->generateTemplate('hostNoteEditFormTemplate', $this->renderEditFormBlock(0, ''));
		$moduleHeader .= $this->generateTemplate('hostNoteEntryTemplate', $this->generateNoteEntryHtml());
	}

	/**
	 * The panel between the manage-posts filter and the results: every note on whatever the page
	 * is filtered on, and a box to add another. A host filter and a browser filter each get their
	 * own panel, since they are separate targets.
	 */
	private function onRenderManagePostsPanel(string &$html, string $filteredIp, bool $canViewIp, string $filteredTokenHash): void {
		if (!$canViewIp || !$this->hostNotePolicy->canViewHostNote()) {
			return;
		}

		if ($filteredIp !== '') {
			$hostNotes = $this->hostNoteService->getNotesForPattern($filteredIp);

			$html .= $this->renderNotesPanel(
				_T('host_notes_panel_title', $filteredIp, count($hostNotes)),
				'ipPattern',
				$filteredIp,
				$hostNotes,
				_T('add_host_note')
			);
		}

		if ($filteredTokenHash !== '') {
			$browserNotes = $this->hostNoteService->getNotesForVisitorToken($filteredTokenHash);
			$label = $this->tokenLabel($filteredTokenHash);

			$html .= $this->renderNotesPanel(
				_T('browser_notes_panel_title', $label, count($browserNotes)),
				'visitorTokenHash',
				$filteredTokenHash,
				$browserNotes,
				_T('add_browser_note')
			);
		}
	}

	/**
	 * One panel of notes on a target, with the box to add another.
	 *
	 * The whole panel is a single form, so the delete button on each note submits through it with
	 * a formaction. That URL carries action=deleteNote as a GET parameter, which the request
	 * object resolves ahead of the POST body's action=addNote, so the two never collide.
	 *
	 * @param string   $title      Panel heading.
	 * @param string   $targetName Name of the hidden field naming the target: ipPattern or visitorTokenHash.
	 * @param string   $target     The host pattern or token hash the notes hang off.
	 * @param array[]  $notes      Note rows to list.
	 * @param string   $addLabel   Wording on the add button.
	 */
	private function renderNotesPanel(string $title, string $targetName, string $target, array $notes, string $addLabel): string {
		$noteListHtml = '';
		foreach ($notes as $note) {
			$noteListHtml .= $this->renderNote($note, $this->currentUrl());
		}

		$isBrowserPanel = $targetName === 'visitorTokenHash';

		$addFormHtml = $this->hostNotePolicy->canLeaveHostNote()
			? $this->moduleContext->adminPageRenderer->ParseBlock('HOST_NOTES_PANEL_FORM', [
				'{$TARGET_NAME}' => sanitizeStr($targetName),
				'{$TARGET_VALUE}' => sanitizeStr($target),
				'{$RETURN_URL}' => sanitizeStr($this->currentUrl()),
				'{$NOTE_FIELD_ID}' => 'hostNotesPanelNote_' . sanitizeStr($targetName),
				'{$FORM_NOTE}' => sanitizeStr(_T('host_note_form_note')),
				'{$HOST_NOTE_VISIBILITY_DESCRIPTION}' => $this->visibilityDescription($isBrowserPanel),
				'{$ADD_LABEL}' => $addLabel,
			])
			: '';

		return $this->moduleContext->adminPageRenderer->ParseBlock('HOST_NOTES_PANEL', [
			'{$PANEL_TITLE}' => sanitizeStr($title),
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$NOTE_LIST}' => $noteListHtml,
			'{$NO_NOTES_TEXT}' => sanitizeStr(_T('host_notes_none')),
			'{$ADD_FORM}' => $addFormHtml,
			'{$CAN_LEAVE_NOTE}' => $addFormHtml !== '',
		]);
	}

	private function renderHostNotesOnPost(string &$belowComment, Post &$post, bool $adminMode): void {
		// only run the method on the live frontend
		if (!$adminMode) {
			return;
		}

		$belowComment .= $this->renderNoteGroup(
			$this->hostNoteService->getNotesForAddress($post->getIp()),
			'hostNotesContainer',
			_T('host_notes_header')
		);

		// A browser note follows the token hash rather than the address, so it is drawn apart.
		$belowComment .= $this->renderNoteGroup(
			$this->hostNoteService->getNotesForVisitorToken($post->getVisitorTokenHash()),
			'browserNotesContainer',
			_T('browser_notes_header')
		);
	}

	/**
	 * One labelled block of notes, or nothing at all when there are none.
	 *
	 * @param array[] $notes     Note rows.
	 * @param string  $className Wrapper class.
	 * @param string  $header    Heading above the block.
	 */
	private function renderNoteGroup(array $notes, string $className, string $header): string {
		if (!$notes) {
			return '';
		}

		$notesHtml = '';
		foreach ($notes as $note) {
			$notesHtml .= $this->renderNote($note);
		}

		return '<div class="hostNotesContainer ' . $className . '"><span class="hostNotesHeader">'
			. sanitizeStr($header) . '</span>' . $notesHtml . '</div>';
	}

	// ─── Rendering ────────────────────────────────────────────────

	private function generateNoteEntryHtml(
		?int $noteId = 0,
		?string $noteText = '',
		?string $accountName = '',
		?int $accountId = 0,
		?string $noteTimestamp = '',
		string $returnUrl = ''
	): string {
		$urlParams = $returnUrl !== '' ? ['returnUrl' => $returnUrl] : [];

		return $this->moduleContext->adminPageRenderer->ParseBlock('HOST_NOTE_ENTRY_HTML', [
			'{$NOTE_ID}' => $noteId,
			'{$NOTE_TEXT}' => $noteText,
			'{$ACCOUNT_NAME}' => $accountName,
			'{$NOTE_TIMESTAMP}' => $this->moduleContext->postDateFormatter->formatFromDateString($noteTimestamp),
			'{$NOTE_TITLE_TEXT}' => _T('host_note_title_text'),
			// assume its true for the template purposes
			'{$CAN_MODIFY_NOTE}' => $noteId ? $this->hostNotePolicy->canModifyHostNote($noteId) : true,
			'{$NOTE_DELETION_URL}' => $this->getModulePageURL(['action' => 'deleteNote', 'noteId' => $noteId] + $urlParams),
			'{$NOTE_EDIT_URL}' => $this->getModulePageURL(['modPage' => 'editNoteForm', 'noteId' => $noteId] + $urlParams),
			'{$EDIT_NOTE_TITLE}' => _T('edit_host_note'),
			'{$DELETE_NOTE_TITLE}' => _T('delete_host_note'),
			'{$MOD_COLOR}' => modIdToColorHex($accountId),
		]);
	}

	/**
	 * One note row, from a repository row.
	 *
	 * @param array  $note      Note row.
	 * @param string $returnUrl Where edit and delete should come back to, if not the post.
	 */
	private function renderNote(array $note, string $returnUrl = ''): string {
		$sanitizedNote = newLinesToBreakLines(sanitizeStr((string) ($note['note_text'] ?? '')));

		return $this->generateNoteEntryHtml(
			(int) ($note['id'] ?? 0),
			$sanitizedNote,
			(string) ($note['note_added_by_username'] ?? ''),
			(int) ($note['added_by'] ?? 0),
			(string) ($note['note_submitted'] ?? ''),
			$returnUrl
		);
	}

	/**
	 * The create form: both targets carried as hidden values, picked between with a radio.
	 *
	 * Neither is typed in. The window fills them from the post it was opened on, and the plain
	 * page below it is rendered with the post's own already in place, so nothing here is ever a
	 * host somebody guessed at.
	 *
	 * @param string $ipPattern The post's host, empty for the <template> the window clones.
	 * @param string $tokenHash The post's browser token hash, empty when it kept none.
	 */
	private function renderCreateFormBlock(string $ipPattern, string $tokenHash, int $postUid): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('HOST_NOTE_CREATE_FORM', [
			'{$IP_PATTERN}' => sanitizeStr($ipPattern),
			'{$VISITOR_TOKEN_HASH}' => sanitizeStr($tokenHash),
			'{$VISITOR_TOKEN_LABEL}' => sanitizeStr($this->tokenLabel($tokenHash)),
			'{$HAS_BROWSER_TARGET}' => $tokenHash !== '',
			'{$POST_UID}' => $postUid,
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$FORM_TITLE}' => sanitizeStr(_T('host_note_form_title')),
			'{$FORM_TARGET}' => sanitizeStr(_T('host_note_form_target')),
			'{$FORM_TARGET_HOST}' => sanitizeStr(_T('host_note_form_target_host')),
			'{$FORM_TARGET_BROWSER}' => sanitizeStr(_T('host_note_form_target_browser')),
			'{$FORM_NOTE}' => sanitizeStr(_T('host_note_form_note')),
			'{$SUBMIT_TEXT}' => sanitizeStr(_T('host_note_form_submit')),
			'{$HOST_NOTE_TARGET_DESCRIPTION}' => _T('host_note_target_description'),
			'{$HOST_NOTE_VISIBILITY_DESCRIPTION}' => _T('host_note_visibility_description'),
			'{$BROWSER_NOTE_VISIBILITY_DESCRIPTION}' => _T('browser_note_visibility_description'),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		]);
	}

	private function renderEditFormBlock(int $noteId, string $noteText, bool $isBrowserNote = false, string $returnUrl = ''): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('HOST_NOTE_EDIT_FORM', [
			'{$NOTE_ID}' => $noteId,
			'{$NOTE_TEXT}' => sanitizeStr($noteText),
			'{$RETURN_URL}' => sanitizeStr($returnUrl),
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$FORM_TITLE}' => sanitizeStr(_T('host_note_edit_form_title')),
			'{$FORM_NOTE}' => sanitizeStr(_T('host_note_form_note')),
			'{$SUBMIT_TEXT}' => sanitizeStr(_T('host_note_form_submit')),
			'{$HOST_NOTE_VISIBILITY_DESCRIPTION}' => $this->visibilityDescription($isBrowserNote),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		]);
	}

	/** How much of a token hash staff are shown, empty when there is none. */
	private function tokenLabel(string $tokenHash): string {
		return $tokenHash === '' ? '' : substr($tokenHash, 0, visitorTokenSigner::DISPLAY_LENGTH);
	}

	/** Who a note will show under, which is not the same sentence for a browser as for a host. */
	private function visibilityDescription(bool $isBrowserNote): string {
		return _T($isBrowserNote ? 'browser_note_visibility_description' : 'host_note_visibility_description');
	}

	private function outputFormPage(string $formHtml): void {
		echo $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $formHtml],
			true
		);
	}

	// ─── Requests ─────────────────────────────────────────────────

	/**
	 * The host a request is about: the pattern typed into the form, or the one belonging to the
	 * post the widget was opened from.
	 */
	private function resolveIpPattern(): string {
		$ipPattern = trim((string) $this->moduleContext->request->getParameter('ipPattern', default: ''));

		if ($ipPattern === '') {
			$postUid = (int) $this->moduleContext->request->getParameter('postUid', default: 0);
			$ipPattern = $postUid > 0
				? (string) $this->moduleContext->postRepository->resolveHostFromPostUid($postUid)
				: '';
		}

		if ($ipPattern === '') {
			throw new BoardException(_T('host_note_no_host'));
		}

		// The same character set ban patterns are held to, so a note can never be filed on
		// something that is not an address or a range.
		if (!preg_match('/^[\d\.\*\:a-fA-F]+$/', $ipPattern)) {
			throw new BoardException(_T('host_note_bad_host'));
		}

		return $ipPattern;
	}

	/**
	 * The user token a request names, or an empty string when it names none.
	 *
	 * A token hash is a truncated hash, so anything outside that alphabet is refused rather than
	 * quietly filed against a target nothing will ever match.
	 */
	private function requestedTokenHash(): string {
		$tokenHash = trim((string) $this->moduleContext->request->getParameter('visitorTokenHash', default: ''));

		if ($tokenHash === '') {
			return '';
		}

		if (!preg_match('/^[0-9a-f]{1,32}$/i', $tokenHash)) {
			throw new BoardException(_T('browser_note_bad_hash'));
		}

		return strtolower($tokenHash);
	}

	/**
	 * Whether the note being filed follows the browser rather than the host.
	 *
	 * The create form carries both targets and a radio saying which one it means, so a token hash
	 * riding along in the body is not on its own an answer. The manage-posts panel sends no radio
	 * at all - its two panels are already separate forms - so there the target it names is it.
	 */
	private function wantsBrowserNote(string $tokenHash): bool {
		$choice = (string) $this->moduleContext->request->getParameter('noteTarget', 'POST', '');

		return $choice === '' ? $tokenHash !== '' : $choice === 'browser';
	}

	/**
	 * Where a non-AJAX action should land afterwards.
	 *
	 * A returnUrl is only honoured as a site-relative path - never a scheme or a '//' host - so
	 * the form cannot be pointed somewhere else.
	 */
	private function resolveRedirectUrl(): string {
		$returnUrl = $this->requestedReturnUrl();
		if ($returnUrl !== '') {
			return $returnUrl;
		}

		$postUid = (int) $this->moduleContext->request->getParameter('postUid', default: 0);
		if ($postUid > 0) {
			return generatePostUrl($postUid, $this->moduleContext->postRepository);
		}

		return $this->moduleContext->board->getBoardURL(true);
	}

	/** The request's returnUrl, or an empty string when it is missing or not site-relative. */
	private function requestedReturnUrl(): string {
		$returnUrl = (string) $this->moduleContext->request->getParameter('returnUrl', default: '');
		return str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, '//') ? $returnUrl : '';
	}

	/** The manage-posts path as it stands, so the panel's forms come back to the same filter. */
	private function currentUrl(): string {
		$path = (string) $this->moduleContext->request->getServer('SCRIPT_NAME', '');
		$query = http_build_query($this->moduleContext->request->allGet());
		return $query === '' ? $path : $path . '?' . $query;
	}

	private function handleResponse(
		int $noteId,
		?string $note = null,
		?string $ipPattern = null,
		?int $addedBy = null,
		?string $addedAt = null,
		bool $isDeletion = false,
		bool $isEdit = false
	): void {
		if ($addedAt) {
			$addedAt = strip_tags($this->moduleContext->postDateFormatter->formatFromDateString($addedAt));
		}

		if ($this->moduleContext->request->isAjax()) {
			sendAjaxAndDetach([
				'note' => $note,
				'note_id' => $noteId,
				'ip_pattern' => $ipPattern,
				'added_by' => getUsernameFromSession(),
				'added_at' => $addedAt,
				'is_deletion' => $isDeletion,
				'is_edit' => $isEdit,
				'is_add' => !$isDeletion && !$isEdit,
				'mod_color' => $addedBy ? modIdToColorHex($addedBy) : null,
				'deletion_url' => $isDeletion ? null : $this->getModulePageURL(['action' => 'deleteNote', 'noteId' => $noteId], false),
				'edit_url' => $isDeletion ? null : $this->getModulePageURL(['modPage' => 'editNoteForm', 'noteId' => $noteId], false),
			]);
			exit;
		}

		redirect($this->resolveRedirectUrl());
	}

	private function handleAddNoteRequest(): void {
		$noteText = (string) $this->moduleContext->request->getParameter('note', 'POST', '');
		$tokenHash = $this->requestedTokenHash();

		if ($this->wantsBrowserNote($tokenHash)) {
			if ($tokenHash === '') {
				throw new BoardException(_T('host_note_no_browser_target'));
			}

			$noteId = $this->hostNoteService->addTokenNote($tokenHash, $noteText, (int) $this->moduleContext->currentUserId);
			$target = $this->tokenLabel($tokenHash);
		} else {
			$target = $this->resolveIpPattern();
			$noteId = $this->hostNoteService->addNote($target, $noteText, (int) $this->moduleContext->currentUserId);
		}

		$this->handleResponse(
			(int) $noteId,
			$noteText,
			$target,
			$this->moduleContext->currentUserId,
			date('Y-m-d H:i:s')
		);
	}

	private function handleEditNoteRequest(int $noteId): void {
		if (!$this->hostNotePolicy->canModifyHostNote($noteId)) {
			throw new BoardException(_T('host_note_no_permission'));
		}

		$newNoteText = (string) $this->moduleContext->request->getParameter('noteText', 'POST', '');
		$this->hostNoteService->editNote($noteId, $newNoteText);

		$this->handleResponse(
			$noteId,
			$newNoteText,
			null,
			$this->moduleContext->currentUserId,
			date('Y-m-d H:i:s'),
			false,
			true
		);
	}

	private function handleDeleteNoteRequest(int $noteId): void {
		if (!$this->hostNotePolicy->canModifyHostNote($noteId)) {
			throw new BoardException(_T('host_note_no_permission'));
		}

		$this->hostNoteService->deleteNote($noteId);

		$this->handleResponse($noteId, null, null, null, null, true);
	}

	private function handleActionRoute(string $action, ?int $noteId): void {
		if ($action === 'addNote') {
			$this->handleAddNoteRequest();
		} elseif ($action === 'editNote' && $noteId !== null) {
			$this->handleEditNoteRequest($noteId);
		} elseif ($action === 'deleteNote' && $noteId !== null) {
			$this->handleDeleteNoteRequest($noteId);
		} else {
			throw new BoardException(_T('invalid_action'));
		}
	}

	/**
	 * What the widget's form needs before it is filled in: the post's host, and the notes
	 * already standing against it. Read-only, so it answers a plain GET.
	 */
	private function renderHostInfo(): void {
		$ipPattern = $this->resolveIpPattern();
		$postUid = (int) $this->moduleContext->request->getParameter('postUid', default: 0);
		$post = $postUid > 0 ? $this->moduleContext->postRepository->getPostByUid($postUid) : null;
		$tokenHash = $post ? $post->getVisitorTokenHash() : '';

		renderJsonPage([
			'ip_pattern' => $ipPattern,
			'visitor_token_hash' => $tokenHash,
			'visitor_token_label' => $this->tokenLabel($tokenHash),
			'note_count' => count($this->hostNoteService->getNotesForAddress($ipPattern))
				+ count($this->hostNoteService->getNotesForVisitorToken($tokenHash)),
		]);
	}

	private function renderEditNoteForm(int $noteId): void {
		$note = $this->hostNoteService->getNoteById($noteId);
		if (!$note) {
			throw new BoardException(_T('host_note_not_found'));
		}

		$this->outputFormPage($this->renderEditFormBlock(
			$noteId,
			(string) ($note['note_text'] ?? ''),
			(string) ($note['visitor_token_hash'] ?? '') !== '',
			$this->requestedReturnUrl()
		));
	}

	private function handleModPages(?int $noteId): void {
		$modPage = $this->moduleContext->request->getParameter('modPage');

		if ($modPage === 'hostInfo') {
			$this->renderHostInfo();
		} elseif ($modPage === 'editNoteForm' && $noteId !== null) {
			$this->renderEditNoteForm($noteId);
		} else {
			// Without JS there is no window to fill the form in, so both targets are resolved
			// here from the post the link came off.
			$postUid = (int) $this->moduleContext->request->getParameter('postUid', default: 0);

			$this->outputFormPage($this->renderCreateFormBlock(
				$this->resolveIpPattern(),
				$this->resolvePostTokenHash($postUid),
				$postUid
			));
		}
	}

	/** The browser token hash recorded on a post, empty when it kept none or there is no post. */
	private function resolvePostTokenHash(int $postUid): string {
		if ($postUid <= 0) {
			return '';
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid);

		return $post ? $post->getVisitorTokenHash() : '';
	}

	public function ModulePage() {
		$noteId = $this->moduleContext->request->getParameter('noteId');
		$noteId = $noteId !== null && $noteId !== '' ? (int) $noteId : null;

		if ($this->moduleContext->request->hasParameter('action')) {
			requirePostWithCsrf($this->moduleContext->request);
			$this->handleActionRoute((string) $this->moduleContext->request->getParameter('action'), $noteId);
			return;
		}

		$this->handleModPages($noteId);
	}
}
