<?php

namespace Kokonotsuba\Modules\threadMod;

require_once __DIR__ . '/threadRole.php';
require_once __DIR__ . '/threadModPolicy.php';
require_once __DIR__ . '/threadModRepository.php';
require_once __DIR__ . '/threadModService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\OpeningPostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostCssClassListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistPostInsertedListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ThreadReplyListenerTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\ban\visitorTokenSigner;
use Kokonotsuba\thread\Thread;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\getOrCreateCsrfToken;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Kokonotsuba\libraries\rebuildBoardsFromPosts;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\json\renderJsonPage;
use function Puchiko\json\renderPrivateJsonPage;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

/**
 * Thread moderation by the people in the thread.
 */
class moduleMain extends abstractModuleMain {
	use AuditableTrait;
	use BanCheckpointTrait;
	use ModuleHeaderListenerTrait;
	use OpeningPostListenerTrait;
	use PostCssClassListenerTrait;
	use RegistBeginListenerTrait;
	use RegistPostInsertedListenerTrait;
	use ThreadReplyListenerTrait;

	public const CHECKPOINT = 'threadMod';

	/** What is typed after "## " in the name field to ask for the thread capcode. */
	public const CAPCODE_TRIGGER = 'OP';

	/** The same for a thread mod; either trigger signs with the capcode the poster's role holds. */
	public const MOD_CAPCODE_TRIGGER = 'Thread Mod';

	public const ACTION_DELETE = 'threadMod.delete';
	public const ACTION_UNDO = 'threadMod.undo';
	public const ACTION_GRANT = 'threadMod.grant';
	public const ACTION_REVOKE = 'threadMod.revoke';
	public const ACTION_TRANSFER = 'threadMod.transfer';

	private threadModService $service;
	private threadModPolicy $policy;
	private string $modulePageUrl;
	private bool $viewerIsStaff;

	public function getName(): string {
		return 'Thread moderation';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		$this->policy = new threadModPolicy((int)$this->getModuleConfig('MAX_DELETIONS_PER_THREAD', 0));

		$this->service = new threadModService(
			new threadModRepository(
				databaseConnection::getInstance(),
				$this->moduleContext->getTableName('THREAD_MOD_TABLE'),
				$this->moduleContext->getTableName('POST_TABLE'),
				$this->moduleContext->getTableName('DELETED_POSTS_TABLE')
			),
			$this->moduleContext->postRepository,
			$this->moduleContext->threadRepository,
			fn(): string => $this->moduleContext->banService->hashIncomingToken(),
			$this->moduleContext->cookieService,
			$this->moduleContext->request,
			$this->policy,
			(bool)$this->getModuleConfig('ALLOW_PASSWORD_OWNERSHIP', true)
		);

		$this->modulePageUrl = $this->getModulePageURL([], false);
		$this->viewerIsStaff = isActiveStaffSession();

		$this->registerBanCheckpoint(self::CHECKPOINT, _T('threadmod_checkpoint_label'));

		foreach ([
			self::ACTION_DELETE => _T('threadmod_log_type_delete'),
			self::ACTION_UNDO => _T('threadmod_log_type_undo'),
			self::ACTION_GRANT => _T('threadmod_log_type_grant'),
			self::ACTION_REVOKE => _T('threadmod_log_type_revoke'),
			self::ACTION_TRANSFER => _T('threadmod_log_type_transfer'),
		] as $key => $label) {
			$this->registerActionType($key, $label, 'post');
		}

		$this->listenModuleHeader('onGenerateModuleHeader');
		$this->listenPostCssClass('onPostCssClass');
		// last, after PostComment and BelowComment have finished with the values it wraps
		$this->listenThreadReply('onRenderReply', -100);
		$this->listenOpeningPost('onRenderOpeningPost');
		// ahead of the tripcode module, which would otherwise read "## OP" as a secure tripcode
		$this->listenRegistBegin('onRegistBegin', 10);
		$this->listenRegistPostInserted('onPostInserted');

		$this->moduleContext->moduleEngine->addListener('CapcodeFormats', function (array &$capcodes): void {
			$this->onCapcodeFormats($capcodes);
		});
	}

	/** The thread capcodes, drawn by the tripcode module like the staff ones. */
	private function onCapcodeFormats(array &$capcodes): void {
		$owner = sanitizeStr((string)$this->getModuleConfig('OWNER_CAPCODE_TEXT', 'OP'));
		$mod = sanitizeStr((string)$this->getModuleConfig('MOD_CAPCODE_TEXT', 'Thread Mod'));

		$capcodes[threadModPolicy::CAPCODE_OWNER] = ['capcodeHtml' => '<span class="capcode capcodeThreadOwner">%s ## ' . $owner . '</span>'];
		$capcodes[threadModPolicy::CAPCODE_MOD] = ['capcodeHtml' => '<span class="capcode capcodeThreadMod">%s ## ' . $mod . '</span>'];
	}

	public function getService(): threadModService {
		return $this->service;
	}

	// ─── Rendering ────────────────────────────────────────────────

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$this->includeStylesheet('threadMod.css', $moduleHeader);
		$moduleHeader .= '<meta name="threadModUrl" content="' . sanitizeStr($this->modulePageUrl) . '">';
		$moduleHeader .= '<meta name="threadModCapcodeTrigger" content="' . sanitizeStr(self::CAPCODE_TRIGGER) . '">';
		$moduleHeader .= '<meta name="threadModModCapcodeTrigger" content="' . sanitizeStr(self::MOD_CAPCODE_TRIGGER) . '">';
		$moduleHeader .= $this->generateTemplate('threadModToolsTemplate', $this->renderWindowForm());
		$this->includeScript('threadMod.js', $moduleHeader);
	}

	private function onPostCssClass(string &$postCssClasses, Post &$post): void {
		if (threadModService::isOpDeleted($post)) {
			$postCssClasses .= ' opDeleted';
		}
	}

	/**
	 * Collapse an OP-deleted reply to one line: a summary-only <details> goes below the comment as
	 * a child of the post ({$MODULE_POST_FOOTER}) and the CSS hides the post's other children until
	 * it is open. Plain HTML, so it works without script.
	 */
	private function onRenderReply(array &$templateValues, Post &$post, array &$threadPosts): void {
		$deletion = threadModService::deletionOf($post);

		if ($deletion === null) {
			return;
		}

		$label = $deletion['role']->isOwner() ? _T('threadmod_deleted_by_owner') : _T('threadmod_deleted_by_mod');
		$title = '';

		if ($this->viewerIsStaff && $deletion['hash'] !== '') {
			$title = ' title="' . sanitizeStr(_T('threadmod_deleted_by_token', substr($deletion['hash'], 0, visitorTokenSigner::DISPLAY_LENGTH))) . '"';
		}

		$position = '';

		if (!empty($templateValues['{$POST_POSITION_ENABLED}'])) {
			$position = '<span class="replyPosition">' . (int)($templateValues['{$POST_POSITION}'] ?? 0) . '</span> ';
		}

		$templateValues['{$MODULE_POST_FOOTER}'] = ($templateValues['{$MODULE_POST_FOOTER}'] ?? '') . '<details class="opDeletedDetails">'
			. '<summary class="opDeletedSummary"' . $title . '>' . $position
			. '<span class="opDeletedLabel">' . sanitizeStr($label) . '</span> '
			. '<span class="opDeletedToggle">[<span class="opDeletedShow">' . sanitizeStr(_T('threadmod_show')) . '</span>'
			. '<span class="opDeletedHide">' . sanitizeStr(_T('threadmod_hide')) . '</span>]</span>'
			. '</summary></details>';
	}

	/** A plain link to the tools page under every OP, for readers without script. */
	private function onRenderOpeningPost(array &$templateValues, Post &$post, array &$threadPosts, ?Thread $thread = null): void {
		$templateValues['{$POSTINFO_EXTRA}'] .= '<noscript><span class="threadModToolsLink">[<a href="'
			. $this->getToolsUrl($post->getThreadUid(), true) . '">' . sanitizeStr(_T('threadmod_tools')) . '</a>]</span></noscript>';
	}

	// ─── Posting ──────────────────────────────────────────────────

	/**
	 * "## OP" in the name, or the form's checkbox, asks for the thread capcode. Anyone the thread
	 * does not know is refused outright, like an unauthorised staff capcode.
	 */
	private function onRegistBegin(array &$registInfo): void {
		$typed = self::isTrigger((string)($registInfo['capcode_input'] ?? ''));
		$ticked = (string)$this->moduleContext->request->getParameter('opCapcode', 'POST', '') !== '';

		if (!$typed && !$ticked) {
			return;
		}

		if (!empty($registInfo['isThreadSubmit'])) {
			$role = threadRole::OWNER;
			$stripped = false;
		} else {
			$thread = $registInfo['thread'] ?? null;
			$op = $thread ? $thread->getOpeningPost() : null;

			if (!$op) {
				throw new BoardException(_T('threadmod_capcode_refused', self::CAPCODE_TRIGGER), 403);
			}

			$role = $this->service->resolveRole($op->getThreadUid(), $op);
			$stripped = $this->service->isStripped($op);
		}

		$capcode = $this->policy->capcodeFor($role, $stripped);

		if ($capcode === null) {
			throw new BoardException(_T('threadmod_capcode_refused', self::CAPCODE_TRIGGER), 403);
		}

		$registInfo['capcode'] = $capcode;
		$registInfo['capcode_input'] = '';

		// registRoute copies the "## X" text into the secure tripcode slot as well
		if (self::isTrigger((string)$registInfo['secure_tripcode_input'])) {
			$registInfo['secure_tripcode_input'] = '';
		}
	}

	private static function isTrigger(string $input): bool {
		$input = trim($input);

		return strcasecmp($input, self::CAPCODE_TRIGGER) === 0 || strcasecmp($input, self::MOD_CAPCODE_TRIGGER) === 0;
	}

	/** A new thread, or a reply in a thread this browser moderates, goes into the hint cookie. */
	private function onPostInserted(int $postUid, string $ip): void {
		if ($this->service->actorHash() === '') {
			return;
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid);

		if (!$post) {
			return;
		}

		$threadUid = $post->getThreadUid();

		if (isset($this->service->rememberedThreads()[$threadUid])) {
			return;
		}

		$role = $post->isOp() ? threadRole::OWNER : $this->service->resolveRole($threadUid);

		if ($role->isAtLeastMod()) {
			$this->service->rememberThreads([$threadUid => $role]);
		}
	}

	// ─── Routing ──────────────────────────────────────────────────

	public function ModulePage(): void {
		$request = $this->moduleContext->request;

		if ($request->isPost()) {
			$this->handleAction();
			return;
		}

		$pageName = (string)$request->getParameter('pageName', 'GET', '');

		match ($pageName) {
			'status' => $this->sendStatus(),
			'token' => renderPrivateJsonPage(['token' => getOrCreateCsrfToken()]),
			'state' => $this->sendToolsState(),
			'confirm' => $this->drawConfirmPage(),
			default => $this->drawToolsPage(),
		};
	}

	/**
	 * Which of the given threads this browser moderates, and remember them in the cookie.
	 *   ?mode=module&load=threadMod&pageName=status&thread_uids=uid1,uid2
	 */
	private function sendStatus(): void {
		$raw = (string)$this->moduleContext->request->getParameter('thread_uids', 'GET', '');
		$threadUids = array_slice(array_filter(explode(',', $raw)), 0, 100);

		$roles = $this->service->rolesForThreads($threadUids);
		$payload = [];

		foreach ($roles as $threadUid => $role) {
			if ($role->isAtLeastMod()) {
				$payload[$threadUid] = $role->key();
			}
		}

		if ($payload) {
			$this->service->rememberThreads($roles);
		}

		renderPrivateJsonPage(['roles' => $payload]);
	}

	private function handleAction(): void {
		$request = $this->moduleContext->request;

		requirePostWithCsrf($request);
		$this->assertNotBanned(self::CHECKPOINT);

		// a roster Revoke button submits only its roster key
		$action = $request->getParameter('rosterKey', 'POST', '') !== ''
			? 'revoke'
			: (string)$request->getParameter('action', 'POST', '');
		$typedPassword = (string)$request->getParameter('pwd', 'POST', '');
		$typedPassword = $typedPassword === '' ? null : $typedPassword;

		match ($action) {
			'delete' => $this->handleDelete($typedPassword),
			'undo' => $this->handleUndo($typedPassword),
			'grant' => $this->handleGrant($typedPassword),
			'revoke' => $this->handleRevoke($typedPassword),
			'transfer' => $this->handleTransfer($typedPassword),
			'claim' => $this->handleClaim($typedPassword),
			default => $this->fail(_T('threadmod_unknown_action'), 400),
		};
	}

	// ─── Actions ──────────────────────────────────────────────────

	/** The reply named by postUid, or by a post number on this board from the tools page. */
	private function requestedPost(): Post {
		$request = $this->moduleContext->request;
		$postUid = (int)$request->getParameter('postUid', 'POST', 0);

		if ($postUid <= 0) {
			$number = (int)$request->getParameter('postNumber', 'POST', 0);
			$uid = $number > 0 ? (int)$this->moduleContext->postRepository->resolvePostUidFromPostNumber($this->moduleContext->board, $number) : 0;
			$post = $uid > 0 ? $this->moduleContext->postRepository->getPostByUid($uid) : false;
		} else {
			$post = $this->moduleContext->postRepository->getPostByUid($postUid);
		}

		if (!$post || $post->isDeleted()) {
			$this->fail(_T('threadmod_post_not_found'), 404);
		}

		return $post;
	}

	/**
	 * @return array{0: Post, 1: threadRole, 2: bool} The opening post, the actor's role, stripped.
	 */
	private function situation(string $threadUid, ?string $typedPassword): array {
		$op = $this->service->openingPost($threadUid);

		if ($op === null) {
			$this->fail(_T('threadmod_post_not_found'), 404);
		}

		$stripped = $this->service->isStripped($op);

		if ($stripped) {
			$this->fail(_T('threadmod_stripped'), 403);
		}

		return [$op, $this->service->resolveRole($threadUid, $op, $typedPassword), false];
	}

	private function handleDelete(?string $typedPassword): void {
		$post = $this->requestedPost();
		$threadUid = $post->getThreadUid();
		[$op, $role, $stripped] = $this->situation($threadUid, $typedPassword);

		$capcode = $post->getCapcode();
		$allowed = $this->policy->canDelete(
			$role,
			$stripped,
			$post->isOp(),
			$capcode !== '' && !threadModPolicy::isThreadCapcode($capcode),
			$this->service->authorRole($post, $this->service->ownerHash($threadUid, $op)),
			threadModService::isOpDeleted($post),
			$this->service->deletionCount($threadUid)
		);

		if (!$allowed) {
			$this->refuse($role, $this->policy->capReached($this->service->deletionCount($threadUid)) ? _T('threadmod_cap_reached') : null);
		}

		$this->service->markDeleted($post, $role, $this->service->actorHash());
		$this->logAction(_T('threadmod_log_delete', $post->getNumber(), $role->key(), $op->getNumber()), $post->getBoardUID(), self::ACTION_DELETE, true);

		$this->finishPostChange($post, _T('threadmod_deleted', $post->getNumber()));
	}

	private function handleUndo(?string $typedPassword): void {
		$post = $this->requestedPost();
		$deletion = threadModService::deletionOf($post);

		if ($deletion === null) {
			$this->fail(_T('threadmod_not_deleted'), 409);
		}

		[$op, $role, $stripped] = $this->situation($post->getThreadUid(), $typedPassword);

		if (!$this->policy->canUndo($role, $stripped, $deletion['role'], $deletion['hash'], $this->service->actorHash())) {
			$this->refuse($role);
		}

		$this->service->clearDeleted($post);
		$this->logAction(_T('threadmod_log_undo', $post->getNumber(), $role->key(), $op->getNumber()), $post->getBoardUID(), self::ACTION_UNDO, true);

		$this->finishPostChange($post, _T('threadmod_undeleted', $post->getNumber()));
	}

	private function handleGrant(?string $typedPassword): void {
		$post = $this->requestedPost();
		$threadUid = $post->getThreadUid();
		[$op, $role, $stripped] = $this->situation($threadUid, $typedPassword);
		$actorHash = $this->service->actorHash();

		if (!$this->policy->canGrant($role, $stripped, $post->isOp(), $post->getVisitorTokenHash(), $actorHash)) {
			$this->refuse($role, $post->getVisitorTokenHash() === '' ? _T('threadmod_no_token') : null);
		}

		$this->service->grantMod($threadUid, $post, $actorHash);
		$this->logAction(_T('threadmod_log_grant', $post->getNumber(), $op->getNumber()), $post->getBoardUID(), self::ACTION_GRANT, true);

		$this->finish(_T('threadmod_granted', $post->getNumber()), $this->getToolsUrl($threadUid));
	}

	private function handleRevoke(?string $typedPassword): void {
		$request = $this->moduleContext->request;
		$threadUid = (string)$request->getParameter('threadUid', 'POST', '');
		$rosterKey = (string)$request->getParameter('rosterKey', 'POST', '');

		if (!preg_match('/^[a-f0-9]{64}$/', $rosterKey)) {
			$this->fail(_T('threadmod_unknown_action'), 400);
		}

		[$op, $role, $stripped] = $this->situation($threadUid, $typedPassword);
		$targetHash = $this->service->tokenHashForRosterKey($threadUid, $rosterKey);

		if ($targetHash === null) {
			$this->fail(_T('threadmod_not_a_mod'), 404);
		}
		$actorHash = $this->service->actorHash();

		if (!$this->policy->canRevoke($role, $stripped, $targetHash, $actorHash)) {
			$this->refuse($role);
		}

		if (!$this->service->revokeMod($threadUid, $targetHash, $actorHash, null)) {
			$this->fail(_T('threadmod_not_a_mod'), 404);
		}

		if ($actorHash !== '' && hash_equals($targetHash, $actorHash)) {
			$this->service->forgetThread($threadUid);
		}

		$this->logAction(_T('threadmod_log_revoke', substr($targetHash, 0, visitorTokenSigner::DISPLAY_LENGTH), $op->getNumber()), $op->getBoardUID(), self::ACTION_REVOKE, true);

		$this->finish(_T('threadmod_revoked'), $this->getToolsUrl($threadUid));
	}

	private function handleTransfer(?string $typedPassword): void {
		$post = $this->requestedPost();
		$threadUid = $post->getThreadUid();
		[$op, $role, $stripped] = $this->situation($threadUid, $typedPassword);
		$actorHash = $this->service->actorHash();

		if (!$this->policy->canTransfer($role, $stripped, $post->isOp(), $post->getVisitorTokenHash(), $actorHash)) {
			$this->refuse($role, $post->getVisitorTokenHash() === '' ? _T('threadmod_no_token') : null);
		}

		$this->moduleContext->transactionManager->run(function () use ($threadUid, $post, $actorHash): void {
			$this->service->transferOwnership($threadUid, $post, $actorHash);
		});

		$this->logAction(_T('threadmod_log_transfer', $post->getNumber(), $op->getNumber()), $post->getBoardUID(), self::ACTION_TRANSFER, true);

		$this->finish(_T('threadmod_transferred', $post->getNumber()), $this->getToolsUrl($threadUid));
	}

	/** Prove ownership with the OP's password from a browser that has no token for it. */
	private function handleClaim(?string $typedPassword): void {
		$threadUid = (string)$this->moduleContext->request->getParameter('threadUid', 'POST', '');
		[$op, $role, $stripped] = $this->situation($threadUid, $typedPassword);

		if (!$role->isAtLeastMod()) {
			$this->fail(_T('threadmod_wrong_password'), 403);
		}

		$this->service->rememberThreads([$threadUid => $role]);

		$this->finish(_T('threadmod_claimed'), $this->getToolsUrl($threadUid));
	}

	// ─── Responses ────────────────────────────────────────────────

	/** A policy refusal: a reason when there is a specific one, otherwise "not yours to do". */
	private function refuse(threadRole $role, ?string $reason = null): never {
		$this->fail($reason ?? ($role->isAtLeastMod() ? _T('threadmod_not_allowed') : _T('threadmod_not_a_mod_of_thread')), 403);
	}

	private function fail(string $message, int $statusCode): never {
		if ($this->moduleContext->request->isAjax()) {
			renderJsonErrorPage($message, $statusCode);
			exit;
		}

		throw new BoardException($message, $statusCode);
	}

	/** A hidden or revealed post: redraw its thread everywhere, hand the window the new markup. */
	private function finishPostChange(Post $post, string $message): void {
		rebuildBoardsFromPosts([$post->getUid()], $this->moduleContext->postService);

		if (!$this->moduleContext->request->isAjax()) {
			$this->redirectBack();
		}

		$fresh = $this->moduleContext->postRepository->getPostByUid($post->getUid());

		renderJsonPage([
			'success' => true,
			'message' => $message,
			'postUid' => $post->getUid(),
			'html' => $fresh ? $this->renderPost($fresh) : '',
		]);
	}

	private function finish(string $message, string $fallbackUrl): void {
		if ($this->moduleContext->request->isAjax()) {
			renderJsonPage(['success' => true, 'message' => $message]);
			return;
		}

		$this->redirectBack($fallbackUrl);
	}

	private function redirectBack(string $fallbackUrl = ''): never {
		$referer = $this->moduleContext->request->getReferer();
		redirect($referer !== '' ? $referer : ($fallbackUrl !== '' ? $fallbackUrl : $this->getConfig('LIVE_INDEX_FILE')));
		exit;
	}

	/** The post as the page the reader is on would draw it, so the window can swap it in place. */
	private function renderPost(Post $post): string {
		$threadView = $this->moduleContext->request->getParameter('threadView', 'POST', '1') !== '0';

		return threadModService::renderPostForWindow($this->moduleContext, $post, $this->viewerIsStaff, $threadView);
	}

	// ─── Pages ────────────────────────────────────────────────────

	public function getToolsUrl(string $threadUid, bool $forHtml = false): string {
		return $this->getModulePageURL(['threadUid' => $threadUid], $forHtml);
	}

	/**
	 * The thread's tools page: the roster and a delete-by-number form for the owner, a password
	 * form for anyone the thread does not recognise. The whole no-script path lives here.
	 */
	private function drawToolsPage(): void {
		$threadUid = (string)$this->moduleContext->request->getParameter('threadUid', 'GET', '');
		$op = $threadUid !== '' ? $this->service->openingPost($threadUid) : null;

		if ($op === null) {
			throw new BoardException(_T('thread_not_found'), 404);
		}

		$role = $this->service->resolveRole($threadUid, $op);
		$stripped = $this->service->isStripped($op);
		$active = $role->isAtLeastMod() && !$stripped;

		if ($active) {
			$this->service->rememberThreads([$threadUid => $role]);
		}

		$renderer = $this->moduleContext->adminPageRenderer;
		$common = [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$THREAD_UID}' => sanitizeStr($threadUid),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		];

		$claim = '';
		if (!$role->isAtLeastMod() && !$stripped && (bool)$this->getModuleConfig('ALLOW_PASSWORD_OWNERSHIP', true)) {
			$claim = $renderer->ParseBlock('THREAD_MOD_CLAIM', $common + [
				'{$PASSWORD_LABEL}' => sanitizeStr(_T('threadmod_password_label')),
				'{$PASSWORD_DESC}' => sanitizeStr(_T('threadmod_password_desc')),
				'{$CLAIM_LABEL}' => sanitizeStr(_T('threadmod_claim_button')),
			]);
		}

		$ownerButtons = '';
		$roster = '';
		if ($active && $role->isOwner()) {
			$ownerButtons = $renderer->ParseBlock('THREAD_MOD_OWNER_FORMS', [
				'{$GRANT_BUTTON}' => sanitizeStr(_T('threadmod_grant')),
				'{$TRANSFER_BUTTON}' => sanitizeStr(_T('threadmod_transfer')),
			]);

			$rows = '';
			foreach ($this->service->repository()->listActive($threadUid) as $row) {
				$key = threadModService::rosterKey($threadUid, (string)$row['visitor_token_hash']);
				$rows .= $renderer->ParseBlock('THREAD_MOD_ROSTER_ROW', [
					'{$ROLE}' => sanitizeStr(threadRole::fromStored($row['role'])->isOwner() ? _T('threadmod_role_owner') : _T('threadmod_role_mod')),
					'{$GRANTED_FROM}' => $row['granted_from_no'] ? 'No.' . (int)$row['granted_from_no'] : '',
					'{$GRANTED_AT}' => sanitizeStr((string)$row['granted_at']),
					'{$ROSTER_KEY}' => $key,
					'{$KEY_LABEL}' => substr($key, 0, visitorTokenSigner::DISPLAY_LENGTH),
					'{$REVOKE_LABEL}' => sanitizeStr(_T('threadmod_revoke')),
				]);
			}

			$roster = $rows === ''
				? '<h4>' . sanitizeStr(_T('threadmod_roster_title')) . '</h4><p>' . sanitizeStr(_T('threadmod_roster_empty')) . '</p>'
				: $renderer->ParseBlock('THREAD_MOD_ROSTER', [
					'{$ROSTER_TITLE}' => sanitizeStr(_T('threadmod_roster_title')),
					'{$TH_ROLE}' => sanitizeStr(_T('threadmod_th_role')),
					'{$TH_FROM}' => sanitizeStr(_T('threadmod_th_from')),
					'{$TH_SINCE}' => sanitizeStr(_T('threadmod_th_since')),
					'{$TH_BROWSER}' => sanitizeStr(_T('threadmod_th_browser')),
					'{$ROSTER_ROWS}' => $rows,
				]);
		}

		$modForms = '';
		if ($active) {
			$modForms = $renderer->ParseBlock('THREAD_MOD_MOD_FORMS', [
				'{$POST_LABEL}' => sanitizeStr(_T('threadmod_form_post')),
				'{$PICK_LABEL}' => sanitizeStr(_T('threadmod_form_pick')),
				'{$POST_OPTIONS}' => $this->replyOptions($threadUid),
				'{$PREVIEW_LABEL}' => sanitizeStr(_T('threadmod_form_preview')),
				'{$ACTION_LABEL}' => sanitizeStr(_T('threadmod_form_action')),
				'{$DELETE_BUTTON}' => sanitizeStr(_T('threadmod_delete')),
				'{$UNDO_BUTTON}' => sanitizeStr(_T('threadmod_undo')),
				'{$OWNER_BUTTONS}' => $ownerButtons,
				'{$COUNT_TEXT}' => sanitizeStr($this->countText($threadUid)),
			]);
		}

		$formRows = $claim . $modForms;
		$form = $formRows === '' ? '' : $renderer->ParseBlock('THREAD_MOD_FORM', $common + [
			'{$FORM_ROWS}' => $formRows,
			'{$ROSTER_SECTION}' => $roster,
		]);

		$this->drawPage('THREAD_MOD_TOOLS', [
			'{$TITLE}' => sanitizeStr(_T('threadmod_tools_title', $op->getNumber())),
			'{$ROLE_TEXT}' => sanitizeStr($this->roleText($role, $stripped)),
			'{$FORM_SECTION}' => $form,
			'{$BACK_URL}' => sanitizeStr($this->moduleContext->board->getBoardThreadURL($op->getNumber())),
			'{$BACK_LABEL}' => sanitizeStr(_T('threadmod_back')),
		]);
	}

	/** @return array<int, array{no: int, uid: int, label: string}> The picker's replies, oldest first. */
	private function replyChoices(string $threadUid): array {
		$hidden = _T('threadmod_option_hidden');
		$choices = [];

		foreach ($this->service->repository()->listReplies($threadUid) as $row) {
			$number = (int)$row['no'];
			$choices[] = [
				'no' => $number,
				'uid' => (int)$row['post_uid'],
				'label' => 'No.' . $number . (threadModService::isOpDeleted(new Post($row)) ? ' ' . $hidden : ''),
			];
		}

		return $choices;
	}

	private function replyOptions(string $threadUid): string {
		$html = '';

		foreach ($this->replyChoices($threadUid) as $choice) {
			$html .= '<option value="' . $choice['no'] . '" data-post-uid="' . $choice['uid'] . '">' . sanitizeStr($choice['label']) . '</option>';
		}

		return $html;
	}

	/** The deletion counter's line for a thread. */
	private function countText(string $threadUid): string {
		$max = (int)$this->getModuleConfig('MAX_DELETIONS_PER_THREAD', 0);
		$count = $this->service->deletionCount($threadUid);

		return $max > 0 ? _T('threadmod_count_capped', $count, $max) : _T('threadmod_count', $count);
	}

	/**
	 * The blank form threadMod.js clones into its window. It goes out in the module header, which
	 * static html carries too, so it holds nothing about the reader: no token, no replies, no
	 * roster. The window asks for those (pageName=state) when it opens.
	 */
	private function renderWindowForm(): string {
		$renderer = $this->moduleContext->adminPageRenderer;

		return $renderer->ParseBlock('THREAD_MOD_WINDOW', [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$FORM_ROWS}' => $renderer->ParseBlock('THREAD_MOD_MOD_FORMS', [
				'{$POST_LABEL}' => sanitizeStr(_T('threadmod_form_post')),
				'{$PICK_LABEL}' => sanitizeStr(_T('threadmod_form_pick')),
				'{$POST_OPTIONS}' => '',
				'{$PREVIEW_LABEL}' => sanitizeStr(_T('threadmod_form_preview')),
				'{$ACTION_LABEL}' => sanitizeStr(_T('threadmod_form_action')),
				'{$DELETE_BUTTON}' => sanitizeStr(_T('threadmod_delete')),
				'{$UNDO_BUTTON}' => sanitizeStr(_T('threadmod_undo')),
				'{$OWNER_BUTTONS}' => '<span class="threadModOwnerButtons" hidden>' . $renderer->ParseBlock('THREAD_MOD_OWNER_FORMS', [
					'{$GRANT_BUTTON}' => sanitizeStr(_T('threadmod_grant')),
					'{$TRANSFER_BUTTON}' => sanitizeStr(_T('threadmod_transfer')),
				]) . '</span>',
				'{$COUNT_TEXT}' => '',
			]),
			'{$ROSTER_TITLE}' => sanitizeStr(_T('threadmod_roster_title')),
			'{$ROSTER_EMPTY}' => sanitizeStr(_T('threadmod_roster_empty')),
			'{$TH_ROLE}' => sanitizeStr(_T('threadmod_th_role')),
			'{$TH_FROM}' => sanitizeStr(_T('threadmod_th_from')),
			'{$TH_SINCE}' => sanitizeStr(_T('threadmod_th_since')),
			'{$TH_BROWSER}' => sanitizeStr(_T('threadmod_th_browser')),
			'{$REVOKE_LABEL}' => sanitizeStr(_T('threadmod_revoke')),
		]);
	}

	/**
	 * What the tools window fills its blank form with: the replies to pick from, the counter,
	 * and for the owner the roster.
	 *   ?mode=module&load=threadMod&pageName=state&threadUid=uid
	 */
	private function sendToolsState(): void {
		$threadUid = (string)$this->moduleContext->request->getParameter('threadUid', 'GET', '');
		$op = $threadUid !== '' ? $this->service->openingPost($threadUid) : null;

		if ($op === null) {
			renderJsonErrorPage(_T('thread_not_found'), 404);
			return;
		}

		$role = $this->service->resolveRole($threadUid, $op);

		if ($this->service->isStripped($op)) {
			renderJsonErrorPage(_T('threadmod_stripped'), 403);
			return;
		}

		if (!$role->isAtLeastMod()) {
			renderJsonErrorPage(_T('threadmod_not_a_mod_of_thread'), 403);
			return;
		}

		$roster = [];
		if ($role->isOwner()) {
			foreach ($this->service->repository()->listActive($threadUid) as $row) {
				$key = threadModService::rosterKey($threadUid, (string)$row['visitor_token_hash']);
				$roster[] = [
					'role' => threadRole::fromStored($row['role'])->isOwner() ? _T('threadmod_role_owner') : _T('threadmod_role_mod'),
					'from' => $row['granted_from_no'] ? 'No.' . (int)$row['granted_from_no'] : '',
					'since' => (string)$row['granted_at'],
					'rosterKey' => $key,
					'keyLabel' => substr($key, 0, visitorTokenSigner::DISPLAY_LENGTH),
				];
			}
		}

		renderPrivateJsonPage([
			'title' => _T('threadmod_tools_title', $op->getNumber()),
			'role' => $role->key(),
			'countText' => $this->countText($threadUid),
			'replies' => $this->replyChoices($threadUid),
			'roster' => $roster,
		]);
	}

	private function roleText(threadRole $role, bool $stripped): string {
		if ($stripped) {
			return _T('threadmod_stripped');
		}

		return match ($role) {
			threadRole::OWNER => _T('threadmod_you_are_owner'),
			threadRole::MOD => _T('threadmod_you_are_mod'),
			threadRole::NONE => _T('threadmod_you_are_nobody'),
		};
	}

	/** A one-button confirmation for the no-script menu links. */
	private function drawConfirmPage(): void {
		$request = $this->moduleContext->request;
		$action = (string)$request->getParameter('action', 'GET', '');
		$postUid = (int)$request->getParameter('postUid', 'GET', 0);
		$post = $postUid > 0 ? $this->moduleContext->postRepository->getPostByUid($postUid) : false;

		if (!$post || !in_array($action, ['delete', 'undo', 'grant', 'transfer'], true)) {
			throw new BoardException(_T('threadmod_post_not_found'), 404);
		}

		$this->drawPage('THREAD_MOD_CONFIRM', [
			'{$TITLE}' => sanitizeStr(_T('threadmod_confirm_' . $action, $post->getNumber())),
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$ACTION}' => sanitizeStr($action),
			'{$POST_UID}' => $post->getUid(),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$PASSWORD_LABEL}' => sanitizeStr(_T('threadmod_password_label')),
			'{$PASSWORD_DESC}' => sanitizeStr(_T('threadmod_password_optional')),
			'{$SUBMIT_LABEL}' => sanitizeStr(_T('threadmod_confirm_button')),
			'{$BACK_LABEL}' => sanitizeStr(_T('threadmod_back')),
			'{$BACK_URL}' => sanitizeStr($this->moduleContext->board->getBoardThreadURL($post->getOpNumber() ?: $post->getNumber())),
		]);
	}

	private function drawPage(string $block, array $values): void {
		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock($block, $values);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $contentHtml,
			'{$PAGER}' => '',
		], false);
	}
}
